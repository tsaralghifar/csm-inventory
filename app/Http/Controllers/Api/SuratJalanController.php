<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SuratJalanController — Tanda Terima Barang dari Purchase Order
 *
 * Mendukung partial delivery:
 *   - Satu PO bisa memiliki banyak Surat Jalan (TTB)
 *   - Setiap TTB mencatat qty yang diterima per item (boleh kurang dari qty PO)
 *   - qty_received di purchase_order_items terakumulasi dari semua TTB
 *   - delivery_status PO otomatis berubah: null → partial → completed
 *   - Stok masuk ke gudang saat TTB di-confirm
 */
class SuratJalanController extends Controller
{
    // ── GET /surat-jalan ──────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = SuratJalan::with(['purchaseOrder', 'warehouse', 'creator', 'receiver'])
            ->latest();

        if ($request->po_id) {
            $query->where('purchase_order_id', $request->po_id);
        }

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan delivery_status PO (Belum Diterima / Sebagian / Selesai)
        if ($request->delivery_status) {
            if ($request->delivery_status === 'null') {
                $query->whereHas('purchaseOrder', fn($q) => $q->whereNull('delivery_status'));
            } else {
                $query->whereHas('purchaseOrder', fn($q) => $q->where('delivery_status', $request->delivery_status));
            }
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sj_number', 'ilike', "%{$search}%")
                  ->orWhereHas('purchaseOrder', fn($q2) => $q2->where('po_number', 'ilike', "%{$search}%"));
            });
        }

        if ($request->date_from) {
            $query->whereDate('received_date', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('received_date', '<=', $request->date_to);
        }

        $paginated = $query->withCount('items')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'meta'    => [
                'total'     => $paginated->total(),
                'page'      => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page'  => $paginated->perPage(),
            ],
        ]);
    }

    // ── GET /surat-jalan/{id} ─────────────────────────────────────────────────

    public function show(SuratJalan $suratJalan)
    {
        return response()->json([
            'success' => true,
            'data'    => $suratJalan->load(['purchaseOrder.items.item', 'warehouse', 'creator', 'receiver', 'items.item']),
        ]);
    }

    // ── GET /surat-jalan/po/{po}/remaining ────────────────────────────────────
    // Mengembalikan sisa qty yang belum diterima per item untuk form TTB baru

    public function remaining(PurchaseOrder $po)
    {
        $items = $po->items()->with('item')->get()->map(function ($poItem) {
            $remaining = max(0, $poItem->qty - $poItem->qty_received);
            return [
                'purchase_order_item_id' => $poItem->id,
                'item_id'                => $poItem->item_id,
                'nama_barang'            => $poItem->nama_barang,
                'satuan'                 => $poItem->satuan,
                'harga_satuan'           => $poItem->harga_satuan,
                'kode_unit'              => $poItem->kode_unit,
                'tipe_unit'              => $poItem->tipe_unit,
                'qty_ordered'            => $poItem->qty,
                'qty_received'           => $poItem->qty_received,
                'qty_remaining'          => $remaining,
                'is_fully_received'      => $remaining <= 0,
            ];
        });

        $allReceived = $items->every(fn($i) => $i['is_fully_received']);

        return response()->json([
            'success'      => true,
            'data'         => $items,
            'all_received' => $allReceived,
            'po_status'    => $po->delivery_status,
        ]);
    }

    // ── POST /surat-jalan ─────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'warehouse_id'      => 'required|exists:warehouses,id',
            'vendor_name'       => 'nullable|string|max:255',
            'driver_name'       => 'nullable|string|max:255',
            'vehicle_plate'     => 'nullable|string|max:50',
            'received_date'     => 'required|date',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.item_id'       => 'nullable|exists:items,id',
            'items.*.qty_received'  => 'required|numeric|min:0.01',
            'items.*.masuk_stok'    => 'boolean',
            'items.*.keterangan'    => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $po = PurchaseOrder::with('items')->findOrFail($validated['purchase_order_id']);

            // Validasi: PO harus sudah dikirim ke vendor (tidak bisa jika masih draft/cancelled)
            // Juga izinkan status 'completed' jika delivery_status belum selesai (data lama)
            $allowedStatuses = ['sent_to_vendor', 'partial_received', 'completed'];
            if (!in_array($po->status, $allowedStatuses)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'PO harus berstatus "Dikirim ke Vendor" sebelum dapat menerima barang.',
                ]);
            }
            if ($po->status === 'completed' && $po->delivery_status === 'completed') {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'Semua barang dari PO ini sudah diterima seluruhnya.',
                ]);
            }

            // Validasi qty_received tidak melebihi sisa
            foreach ($validated['items'] as $inputItem) {
                $poItem    = PurchaseOrderItem::findOrFail($inputItem['purchase_order_item_id']);
                $remaining = max(0, $poItem->qty - $poItem->qty_received);

                if ($inputItem['qty_received'] > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => "Qty diterima untuk '{$poItem->nama_barang}' ({$inputItem['qty_received']}) "
                                 . "melebihi sisa yang belum diterima ({$remaining} {$poItem->satuan}).",
                    ]);
                }
            }

            // Buat Surat Jalan
            $sj = SuratJalan::create([
                'sj_number'         => SuratJalan::generateNumber(),
                'purchase_order_id' => $validated['purchase_order_id'],
                'warehouse_id'      => $validated['warehouse_id'],
                'created_by'        => $request->user()->id,
                'status'            => 'received',
                'vendor_name'       => $validated['vendor_name'] ?? $po->vendor_name,
                'driver_name'       => $validated['driver_name'] ?? null,
                'vehicle_plate'     => $validated['vehicle_plate'] ?? null,
                'received_date'     => $validated['received_date'],
                'notes'             => $validated['notes'] ?? null,
                'received_by_user'  => $request->user()->id,
                'received_by_name'  => $request->user()->name,
            ]);

            // Proses setiap item
            foreach ($validated['items'] as $inputItem) {
                $poItem      = PurchaseOrderItem::findOrFail($inputItem['purchase_order_item_id']);
                $qtyReceived = (float) $inputItem['qty_received'];
                $masukStok   = $inputItem['masuk_stok'] ?? true;

                // Simpan item TTB
                SuratJalanItem::create([
                    'surat_jalan_id'          => $sj->id,
                    'purchase_order_item_id'  => $poItem->id,
                    'item_id'                 => $inputItem['item_id'] ?? $poItem->item_id,
                    'nama_barang'             => $poItem->nama_barang,
                    'kode_unit'               => $poItem->kode_unit,
                    'tipe_unit'               => $poItem->tipe_unit,
                    'qty_ordered'             => $poItem->qty,
                    'qty_received'            => $qtyReceived,
                    'satuan'                  => $poItem->satuan,
                    'harga_satuan'            => $poItem->harga_satuan,
                    'masuk_stok'              => $masukStok,
                    'keterangan'              => $inputItem['keterangan'] ?? null,
                ]);

                // Akumulasi qty_received di PO item
                $poItem->increment('qty_received', $qtyReceived);

                // Masukkan ke stok gudang
                if ($masukStok && $poItem->item_id) {
                    $stock = ItemStock::firstOrCreate(
                        ['item_id' => $poItem->item_id, 'warehouse_id' => $validated['warehouse_id']],
                        ['qty' => 0]
                    );
                    $qtyBefore = (float) $stock->qty;
                    $stock->increment('qty', $qtyReceived);
                    $qtyAfter = $qtyBefore + $qtyReceived;

                    // Catat mutasi stok dengan qty_before dan qty_after
                    StockMovement::create([
                        'item_id'        => $poItem->item_id,
                        'to_warehouse_id'=> $validated['warehouse_id'],
                        'type'           => 'in',
                        'qty'            => $qtyReceived,
                        'qty_before'     => $qtyBefore,
                        'qty_after'      => $qtyAfter,
                        'reference_no'   => $sj->sj_number,
                        'notes'          => "Penerimaan dari PO {$po->po_number}",
                        'created_by'     => $request->user()->id,
                        'movement_date'  => $validated['received_date'],
                    ]);
                }
            }

            // Update delivery_status PO
            $this->updatePoDeliveryStatus($po);

            // Update status PM jika semua item PO sudah diterima penuh
            $this->updatePmStatusAfterDelivery($po);

            return response()->json([
                'success' => true,
                'data'    => $sj->load('items.item'),
                'message' => 'Tanda Terima Barang berhasil disimpan.',
            ], 201);
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Update status PM ke 'completed' jika semua item dari semua PO-nya
     * sudah diterima seluruhnya.
     */
    private function updatePmStatusAfterDelivery(PurchaseOrder $po): void
    {
        $pms = $po->permintaanMaterials()->with('items')->get();

        foreach ($pms as $pm) {
            // Skip jika status PM bukan purchasing/partial_ordered
            if (!in_array($pm->status, ['purchasing', 'partial_ordered'])) {
                continue;
            }

            // Cek apakah semua item PM sudah diterima sepenuhnya
            $allReceived = true;
            foreach ($pm->items as $pmItem) {
                $totalReceived = DB::table('purchase_order_items')
                    ->where('permintaan_material_item_id', $pmItem->id)
                    ->sum('qty_received');

                if ((float) $totalReceived < (float) $pmItem->qty) {
                    $allReceived = false;
                    break;
                }
            }

            if ($allReceived) {
                $pm->update(['status' => 'completed']);
            }
        }
    }

    /**
     * null     = belum ada penerimaan sama sekali
     * partial  = sebagian item sudah diterima, sebagian belum
     * completed = semua item sudah diterima penuh
     */
    private function updatePoDeliveryStatus(PurchaseOrder $po): void
    {
        $po->refresh();
        $items = $po->items;

        $totalItems    = $items->count();
        $receivedItems = $items->filter(fn($i) => $i->qty_received > 0)->count();
        $fullItems     = $items->filter(fn($i) => $i->qty_received >= $i->qty)->count();

        if ($receivedItems === 0) {
            $deliveryStatus = null;
            $poStatus       = $po->status; // tidak berubah
        } elseif ($fullItems === $totalItems) {
            $deliveryStatus = 'completed';
            $poStatus       = 'completed';
        } else {
            $deliveryStatus = 'partial';
            $poStatus       = 'partial_received';
        }

        $po->update([
            'delivery_status' => $deliveryStatus,
            'status'          => $poStatus ?? $po->status,
        ]);
    }
}