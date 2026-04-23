<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemStock;
use App\Models\MaterialRequest;
use App\Models\PermintaanMaterial;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuratJalanController extends Controller
{
    public function index(Request $request)
    {
        $query = SuratJalan::with(['purchaseOrder', 'warehouse', 'creator'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->status)    $query->where('status', $request->status);
        if ($request->search)    $query->where('sj_number', 'ilike', "%{$request->search}%");
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('created_at', '<=', $request->date_to);

        $data = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id'        => 'nullable|exists:purchase_orders,id',
            'material_request_id'      => 'nullable|exists:material_requests,id',
            'permintaan_material_id'   => 'nullable|exists:permintaan_material,id',
            'warehouse_id'             => 'required|exists:warehouses,id',
            'vendor_name'              => 'nullable|string|max:255',
            'driver_name'              => 'nullable|string|max:255',
            'vehicle_plate'            => 'nullable|string|max:50',
            'received_date'            => 'required|date',
            'notes'                    => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.nama_barang'      => 'required|string|max:255',
            'items.*.kode_unit'        => 'nullable|string',
            'items.*.tipe_unit'        => 'nullable|string',
            'items.*.qty_ordered'      => 'required|numeric|min:0.01',
            'items.*.qty_received'     => 'required|numeric|min:0',
            'items.*.satuan'           => 'required|string',
            'items.*.harga_satuan'     => 'nullable|numeric|min:0',
            'items.*.masuk_stok'       => 'boolean',
            'items.*.item_id'          => 'nullable|exists:items,id',
            'items.*.keterangan'       => 'nullable|string',
        ]);

        $sj = DB::transaction(function () use ($validated, $request) {
            $sj = SuratJalan::create([
                'sj_number'                => SuratJalan::generateNumber(),
                'purchase_order_id'        => $validated['purchase_order_id'] ?? null,
                'material_request_id'      => $validated['material_request_id'] ?? null,
                'permintaan_material_id'   => $validated['permintaan_material_id'] ?? null,
                'warehouse_id'             => $validated['warehouse_id'],
                'created_by'               => $request->user()->id,
                'status'                   => 'draft',
                'vendor_name'              => $validated['vendor_name'] ?? null,
                'driver_name'              => $validated['driver_name'] ?? null,
                'vehicle_plate'            => $validated['vehicle_plate'] ?? null,
                'received_date'            => $validated['received_date'],
                'notes'                    => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                // Auto-resolve item_id jika tidak dikirim dari frontend
                if (empty($item['item_id']) && !empty($item['nama_barang'])) {
                    $foundItem = \App\Models\Item::where(fn($q) => $q
                        ->where('name', 'ilike', $item['nama_barang'])
                        ->orWhere('part_number', 'ilike', $item['nama_barang']))
                        ->first();
                    if ($foundItem) {
                        $item['item_id'] = $foundItem->id;
                    }
                }

                SuratJalanItem::create(array_merge($item, [
                    'surat_jalan_id' => $sj->id,
                    'masuk_stok'     => $item['masuk_stok'] ?? true,
                    'harga_satuan'   => $item['harga_satuan'] ?? 0,
                ]));
            }

            return $sj->load('items', 'warehouse', 'creator');
        });

        return response()->json(['success' => true, 'data' => $sj, 'message' => 'Surat Jalan berhasil dibuat'], 201);
    }

    public function show(SuratJalan $suratJalan)
    {
        return response()->json([
            'success' => true,
            'data'    => $suratJalan->load('items.item', 'warehouse', 'creator', 'receiver', 'purchaseOrder', 'materialRequest'),
        ]);
    }

    public function receive(Request $request, SuratJalan $suratJalan)
    {
        if ($suratJalan->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Surat Jalan sudah pernah dikonfirmasi']);
        }

        $request->validate([
            'received_by_name' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($suratJalan, $request) {
            $itemIndex = 1;
            foreach ($suratJalan->items as $sjItem) {
                if (!$sjItem->masuk_stok || $sjItem->qty_received <= 0) continue;

                // Auto-resolve item_id jika kosong, cari dari master item berdasarkan nama
                if (!$sjItem->item_id) {
                    $foundItem = \App\Models\Item::where(fn($q) => $q
                        ->where('name', 'ilike', $sjItem->nama_barang)
                        ->orWhere('part_number', 'ilike', $sjItem->nama_barang))
                        ->first();
                    if ($foundItem) {
                        $sjItem->item_id = $foundItem->id;
                        $sjItem->save();
                    } else {
                        \Illuminate\Support\Facades\Log::warning("SuratJalan receive: item tidak ditemukan untuk '{$sjItem->nama_barang}' di SJ {$suratJalan->sj_number}");
                        continue;
                    }
                }

                $stock = ItemStock::firstOrCreate(
                    ['item_id' => $sjItem->item_id, 'warehouse_id' => $suratJalan->warehouse_id],
                    ['qty' => 0, 'qty_reserved' => 0]
                );

                $qtyBefore = $stock->qty;
                $newPrice = (float) ($sjItem->harga_satuan ?? 0);

                // Hitung Average Cost (AVCO)
                if ($newPrice > 0) {
                    $oldAvg = (float) $stock->avg_price;
                    $newAvg = $qtyBefore > 0
                        ? (($qtyBefore * $oldAvg) + ($sjItem->qty_received * $newPrice)) / ($qtyBefore + $sjItem->qty_received)
                        : $newPrice;

                    $stock->avg_price = $newAvg;

                    // Update harga di master item
                    \App\Models\Item::where('id', $sjItem->item_id)
                        ->update(['price' => $newAvg]);

                    // Catat riwayat harga
                    \App\Models\ItemPriceHistory::create([
                        'item_id'          => $sjItem->item_id,
                        'warehouse_id'     => $suratJalan->warehouse_id,
                        'purchase_price'   => $newPrice,
                        'avg_price_before' => $oldAvg,
                        'avg_price_after'  => $newAvg,
                        'qty_received'     => $sjItem->qty_received,
                        'reference_no'     => $suratJalan->sj_number,
                        'source_type'      => 'surat_jalan',
                        'created_by'       => $request->user()->id,
                        'transaction_date' => $suratJalan->received_date,
                    ]);
                }

                $stock->increment('qty', $sjItem->qty_received);
                $stock->save(); // simpan avg_price

                StockMovement::create([
                    'item_id'          => $sjItem->item_id,
                    'to_warehouse_id'  => $suratJalan->warehouse_id,
                    'type'             => 'in',
                    'qty'              => $sjItem->qty_received,
                    'qty_before'       => $qtyBefore,
                    'qty_after'        => $qtyBefore + $sjItem->qty_received,
                    'reference_no'     => $suratJalan->sj_number . '-' . str_pad($itemIndex, 3, '0', STR_PAD_LEFT),
                    'notes'            => "Surat Jalan: {$suratJalan->sj_number}",
                    'moveable_type'    => SuratJalan::class,
                    'moveable_id'      => $suratJalan->id,
                    'movement_date'    => $suratJalan->received_date,
                    'created_by'       => $request->user()->id,
                ]);
                $itemIndex++;
            }

            $suratJalan->update([
                'status'            => 'received',
                'received_by_user'  => $request->user()->id,
                'received_by_name'  => $request->received_by_name ?? null,
            ]);

            if ($suratJalan->purchase_order_id) {
                $po = PurchaseOrder::with('permintaanMaterials.items')->find($suratJalan->purchase_order_id);
                if ($po) {
                    $po->update(['status' => 'completed']);

                    // Update status tiap PM yang terkait dengan PO ini
                    // PM hanya jadi 'completed' jika SEMUA itemnya sudah punya PO
                    // (tidak peduli apakah PO sudah diterima — itu urusan purchasing)
                    foreach ($po->permintaanMaterials as $pm) {
                        $pm->loadMissing('items');
                        if ($pm->isFullyOrdered()) {
                            $pm->update(['status' => 'completed']);
                        } else {
                            // Masih ada item yang belum di-PO — tetap partial_ordered
                            $pm->update(['status' => 'partial_ordered']);
                        }
                    }

                    // Backward compat: kolom lama permintaan_material_id
                    if ($po->permintaan_material_id && $po->permintaanMaterials->isEmpty()) {
                        $pmOld = PermintaanMaterial::with('items')->find($po->permintaan_material_id);
                        if ($pmOld) {
                            $pmOld->update([
                                'status' => $pmOld->isFullyOrdered() ? 'completed' : 'partial_ordered'
                            ]);
                        }
                    }
                }
            }

            if ($suratJalan->material_request_id) {
                MaterialRequest::find($suratJalan->material_request_id)
                    ->update(['status' => 'completed']);
            }

            // Jika SJ langsung dari PM (bukan via PO), baru langsung completed
            if ($suratJalan->permintaan_material_id && !$suratJalan->purchase_order_id) {
                PermintaanMaterial::find($suratJalan->permintaan_material_id)
                    ->update(['status' => 'completed']);
            }
        });

        return response()->json(['success' => true, 'message' => 'Surat Jalan dikonfirmasi, barang masuk ke stok']);
    }
}