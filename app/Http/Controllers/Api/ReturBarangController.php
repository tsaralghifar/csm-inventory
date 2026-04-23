<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseOrder;
use App\Models\ReturBarang;
use App\Models\ReturBarangItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturBarangController extends Controller
{
    // GET /retur-barang
    public function index(Request $request)
    {
        $query = ReturBarang::with(['purchaseOrder', 'warehouse', 'creator'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->status)    $query->where('status', $request->status);
        if ($request->search)    $query->where('retur_number', 'ilike', "%{$request->search}%");
        if ($request->date_from) $query->whereDate('retur_date', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('retur_date', '<=', $request->date_to);

        $data = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /retur-barang
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id'             => 'required|exists:purchase_orders,id',
            'warehouse_id'                  => 'required|exists:warehouses,id',
            'vendor_name'                   => 'required|string|max:255',
            'vendor_contact'                => 'nullable|string|max:255',
            'retur_date'                    => 'required|date',
            'alasan'                        => 'nullable|string',
            'notes'                         => 'nullable|string',
            'items'                         => 'required|array|min:1',
            'items.*.item_id'               => 'nullable|exists:items,id',
            'items.*.purchase_order_item_id'=> 'nullable|exists:purchase_order_items,id',
            'items.*.nama_barang'           => 'required|string|max:255',
            'items.*.part_number'           => 'nullable|string|max:100',
            'items.*.kode_unit'             => 'nullable|string|max:100',
            'items.*.tipe_unit'             => 'nullable|string|max:100',
            'items.*.qty'                   => 'required|numeric|min:0.01',
            'items.*.satuan'                => 'required|string|max:50',
            'items.*.harga_satuan'          => 'nullable|numeric|min:0',
            'items.*.jenis'                 => 'required|in:returnable,non_returnable',
            'items.*.alasan_item'           => 'nullable|string',
        ]);

        $retur = DB::transaction(function () use ($validated, $request) {
            $retur = ReturBarang::create([
                'retur_number'      => ReturBarang::generateNumber(),
                'purchase_order_id' => $validated['purchase_order_id'],
                'warehouse_id'      => $validated['warehouse_id'],
                'vendor_name'       => $validated['vendor_name'],
                'vendor_contact'    => $validated['vendor_contact'] ?? null,
                'retur_date'        => $validated['retur_date'],
                'alasan'            => $validated['alasan'] ?? null,
                'notes'             => $validated['notes'] ?? null,
                'status'            => 'draft',
                'created_by'        => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                ReturBarangItem::create([
                    'retur_barang_id'        => $retur->id,
                    'item_id'                => $item['item_id'] ?? null,
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'nama_barang'            => $item['nama_barang'],
                    'part_number'            => $item['part_number'] ?? null,
                    'kode_unit'              => $item['kode_unit'] ?? null,
                    'tipe_unit'              => $item['tipe_unit'] ?? null,
                    'qty'                    => $item['qty'],
                    'satuan'                 => $item['satuan'],
                    'harga_satuan'           => $item['harga_satuan'] ?? 0,
                    'jenis'                  => $item['jenis'],
                    'alasan_item'            => $item['alasan_item'] ?? null,
                ]);
            }

            return $retur->load('items.item', 'purchaseOrder', 'warehouse', 'creator');
        });

        return response()->json(['success' => true, 'data' => $retur, 'message' => 'Retur berhasil dibuat'], 201);
    }

    // GET /retur-barang/{id}
    public function show(ReturBarang $returBarang)
    {
        return response()->json([
            'success' => true,
            'data'    => $returBarang->load(
                'items.item',
                'items.purchaseOrderItem',
                'purchaseOrder.items',
                'warehouse',
                'creator',
                'confirmer'
            ),
        ]);
    }

    // POST /retur-barang/{id}/confirm
    // Konfirmasi: kurangi stok untuk item returnable, tandai salah beli untuk non_returnable
    public function confirm(ReturBarang $returBarang, Request $request)
    {
        if ($returBarang->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Retur ini sudah dikonfirmasi sebelumnya.']);
        }

        DB::transaction(function () use ($returBarang, $request) {
            foreach ($returBarang->items as $returItem) {
                if ($returItem->jenis === 'returnable') {
                    // Kurangi stok dari gudang
                    if ($returItem->item_id) {
                        $stock = ItemStock::firstOrCreate(
                            ['item_id' => $returItem->item_id, 'warehouse_id' => $returBarang->warehouse_id],
                            ['qty' => 0, 'qty_reserved' => 0]
                        );

                        $qtyBefore = (float) $stock->qty;
                        $qty       = (float) $returItem->qty;

                        if ($qtyBefore < $qty) {
                            throw ValidationException::withMessages([
                                'qty' => "Stok tidak cukup untuk retur barang \"{$returItem->nama_barang}\". Stok: {$qtyBefore}, Retur: {$qty}",
                            ]);
                        }

                        $stock->qty          = $qtyBefore - $qty;
                        $stock->last_updated = now();
                        $stock->save();

                        // Catat mutasi stok
                        $prefix = 'RET';
                        $prefixFull = $prefix . '-' . now()->format('Ymd') . '-';
                        $lastRef = StockMovement::lockForUpdate()
                            ->where('reference_no', 'like', "{$prefixFull}%")
                            ->orderByRaw('CAST(SUBSTRING(reference_no FROM ' . (strlen($prefixFull) + 1) . ') AS INTEGER) DESC')
                            ->value('reference_no');
                        $lastNumber = $lastRef ? (int) substr($lastRef, strlen($prefixFull)) : 0;
                        $refNo = $prefixFull . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

                        StockMovement::create([
                            'reference_no'     => $refNo,
                            'type'             => 'out',
                            'item_id'          => $returItem->item_id,
                            'from_warehouse_id'=> $returBarang->warehouse_id,
                            'qty'              => $qty,
                            'qty_before'       => $qtyBefore,
                            'qty_after'        => $stock->qty,
                            'price'            => $returItem->harga_satuan,
                            'po_number'        => $returBarang->purchaseOrder->po_number,
                            'notes'            => "Retur ke vendor: {$returBarang->vendor_name} | {$returBarang->retur_number}",
                            'movement_date'    => $returBarang->retur_date,
                            'moveable_type'    => ReturBarang::class,
                            'moveable_id'      => $returBarang->id,
                            'created_by'       => $request->user()->id,
                        ]);
                    }
                } else {
                    // non_returnable: tandai barang sebagai salah beli di master item
                    if ($returItem->item_id) {
                        Item::where('id', $returItem->item_id)->update([
                            'is_salah_beli'    => true,
                            'salah_beli_notes' => "Ditandai via Retur {$returBarang->retur_number}: " . ($returItem->alasan_item ?? $returBarang->alasan ?? '-'),
                        ]);
                    }
                }
            }

            $returBarang->update([
                'status'       => 'confirmed',
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'data'    => $returBarang->fresh()->load('items.item', 'purchaseOrder', 'warehouse', 'creator', 'confirmer'),
            'message' => 'Retur berhasil dikonfirmasi. Stok telah diperbarui.',
        ]);
    }
}