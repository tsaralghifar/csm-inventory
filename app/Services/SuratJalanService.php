<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLayer;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class SuratJalanService
{
    /**
     * Ambil daftar Surat Jalan / TTB dengan filter.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = SuratJalan::with(['purchaseOrder', 'warehouse', 'creator', 'receiver'])
            ->withCount('items')
            ->latest();

        if ($request->po_id)         $query->where('purchase_order_id', $request->po_id);
        if ($request->warehouse_id)  $query->where('warehouse_id', $request->warehouse_id);
        if ($request->status)        $query->where('status', $request->status);

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

        if ($request->date_from) $query->whereDate('received_date', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('received_date', '<=', $request->date_to);

        return $query->paginate($request->per_page ?? 15);
    }

    /**
     * Buat Surat Jalan (TTB) baru dari sebuah PO.
     */
    public function create(array $validated, PurchaseOrder $po, int $userId): SuratJalan
    {
        $this->assertPoReceivable($po);

        return DB::transaction(function () use ($validated, $po, $userId) {
            $sj = SuratJalan::create([
                'sj_number'         => SuratJalan::generateNumber(),
                'purchase_order_id' => $po->id,
                'warehouse_id'      => $po->warehouse_id,
                'created_by'        => $userId,
                'status'            => 'received',
                'vendor_name'       => $validated['vendor_name'] ?? null,
                'driver_name'       => $validated['driver_name'] ?? null,
                'vehicle_plate'     => $validated['vehicle_plate'] ?? null,
                'received_date'     => $validated['received_date'],
                'notes'             => $validated['notes'] ?? null,
                'received_by_user'  => $userId,
            ]);

            $this->processItems($validated['items'], $sj, $po, $userId);
            $this->updatePoDeliveryStatus($po);

            return $sj->load('items.item', 'purchaseOrder', 'warehouse', 'creator');
        });
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function assertPoReceivable(PurchaseOrder $po): void
    {
        if (in_array($po->status, ['draft', 'cancelled'])) {
            throw ValidationException::withMessages([
                'status' => 'PO belum siap untuk diterima barangnya.',
            ]);
        }
    }

    private function processItems(array $items, SuratJalan $sj, PurchaseOrder $po, int $userId): void
    {
        foreach ($items as $itemData) {
            $qtyReceived = (float) ($itemData['qty_received'] ?? 0);

            if ($qtyReceived <= 0) continue;

            $poItem = PurchaseOrderItem::findOrFail($itemData['purchase_order_item_id']);

            SuratJalanItem::create([
                'surat_jalan_id'         => $sj->id,
                'purchase_order_item_id' => $poItem->id,
                'item_id'                => $poItem->item_id,
                'nama_barang'            => $poItem->nama_barang,
                'kode_unit'              => $poItem->kode_unit ?? null,
                'tipe_unit'              => $poItem->tipe_unit ?? null,
                'qty_ordered'            => $poItem->qty,
                'qty_received'           => $qtyReceived,
                'satuan'                 => $poItem->satuan,
                'harga_satuan'           => $poItem->harga_satuan ?? 0,
                'keterangan'             => $itemData['keterangan'] ?? null,
                'masuk_stok'             => $itemData['masuk_stok'] ?? true,
            ]);

            // Akumulasi qty_received di PO item
            $poItem->increment('qty_received', $qtyReceived);

            // Update stok jika item terdaftar di master item dan masuk_stok tidak di-set false
            $masukStok = $itemData['masuk_stok'] ?? true;
            if ($poItem->item_id && $masukStok) {
                // Jika PO ini pengganti Transfer Part Darurat,
                // stok masuk ke warehouse tujuan transfer (bukan warehouse PO)
                $targetWarehouseId = $po->warehouse_id;
                if ($po->linked_mr_transfer_id) {
                    $tp = \App\Models\MaterialRequest::find($po->linked_mr_transfer_id);
                    if ($tp && $tp->to_warehouse_id) {
                        $targetWarehouseId = $tp->to_warehouse_id;
                    }
                }
                $this->addStock($poItem, $po, $qtyReceived, $sj, $userId, $targetWarehouseId);
            }
        }
    }

    private function addStock(PurchaseOrderItem $poItem, PurchaseOrder $po, float $qty, SuratJalan $sj, int $userId, ?int $warehouseId = null): void
    {
        $warehouseId ??= $po->warehouse_id;
        $stock = ItemStock::firstOrCreate(
            ['item_id' => $poItem->item_id, 'warehouse_id' => $warehouseId],
            ['qty' => 0, 'qty_reserved' => 0, 'avg_price' => 0]
        );

        $qtyBefore  = $stock->qty;
        $harga      = $poItem->harga_satuan ?? 0;

        // Hitung average price (moving average)
        $newAvgPrice = $stock->qty > 0
            ? (($stock->avg_price * $stock->qty) + ($harga * $qty)) / ($stock->qty + $qty)
            : $harga;

        $stock->update([
            'qty'       => $qtyBefore + $qty,
            'avg_price' => round($newAvgPrice, 2),
        ]);

        // ── Buat FIFO Layer ───────────────────────────────────────────────────
        StockLayer::create([
            'item_id'       => $poItem->item_id,
            'warehouse_id'  => $warehouseId,
            'qty_awal'      => $qty,
            'qty_sisa'      => $qty,
            'harga_satuan'  => $harga,
            'tanggal_masuk' => now()->toDateString(),
            'source_type'   => 'po',
            'reference_no'  => $po->po_number,
            'created_by'    => $userId,
        ]);

        // ── Price Intelligence — analisis perubahan harga & kirim notif ───────
        app(\App\Services\PriceAlertService::class)->analyzeAndNotify(
            itemId:          $poItem->item_id,
            warehouseId:     $warehouseId,
            newPrice:        $harga,
            qtyReceived:     $qty,
            referenceNo:     $po->po_number,
            supplierName:    $po->vendor_name    ?? '',
            supplierId:      $po->supplier_id    ?? null,
            userId:          $userId,
            transactionDate: now()->toDateString(),
        );

        // ── Budget Alert check ────────────────────────────────────────────────
        app(\App\Services\PriceAlertService::class)->checkBudgetAlert($userId);

        StockMovement::create([
            'item_id'          => $poItem->item_id,
            'to_warehouse_id'  => $warehouseId,
            'type'             => 'in',
            'qty'              => $qty,
            'qty_before'       => $qtyBefore,
            'qty_after'        => $qtyBefore + $qty,
            'reference_no'     => $sj->sj_number . '-' . $poItem->id,
            'po_number'        => $po->po_number,
            'notes'            => "Penerimaan barang dari PO: {$po->po_number}",
            'moveable_type'    => SuratJalan::class,
            'moveable_id'      => $sj->id,
            'movement_date'    => now()->toDateString(),
            'created_by'       => $userId,
            'price'            => $harga,
        ]);
    }

    /**
     * Konfirmasi penerimaan Surat Jalan oleh penerima akhir.
     * Method ini dipanggil dari route POST /surat-jalan/{id}/receive.
     */
    public function markReceived(SuratJalan $sj, array $validated, int $userId): SuratJalan
    {
        if ($sj->status === 'received') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Surat Jalan sudah pernah dikonfirmasi penerimaannya.',
            ]);
        }

        $sj->update([
            'status'      => 'received',
            'received_by' => $validated['received_by'],
            'received_at' => now(),
            'notes'       => ($sj->notes ? $sj->notes . "\n" : '') . ($validated['notes'] ?? ''),
            'received_by_user_id' => $userId,
        ]);

        return $sj->fresh();
    }

    private function updatePoDeliveryStatus(PurchaseOrder $po): void
    {
        $po->load('items');

        $totalQty    = $po->items->sum('qty');
        $receivedQty = $po->items->sum('qty_received');

        $deliveryStatus = match (true) {
            $receivedQty <= 0          => null,
            $receivedQty < $totalQty   => 'partial',
            default                    => 'completed',
        };

        // Sync kolom status sesuai kondisi penerimaan barang
        $poStatus = match ($deliveryStatus) {
            'partial'   => PurchaseOrder::STATUS_PARTIAL_RECEIVED,
            'completed' => PurchaseOrder::STATUS_COMPLETED,
            default     => $po->status, // tidak berubah jika belum ada penerimaan
        };

        $po->update([
            'delivery_status' => $deliveryStatus,
            'status'          => $poStatus,
        ]);
    }
}