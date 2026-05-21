<?php

namespace App\Services;

use App\Events\TransferBarangUpdated;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\ItemStock;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\StockLayer;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class TransferBarangService
{
    public function __construct(private StockService $stockService) {}

    /**
     * Approve oleh Admin — set qty_approved per item, reserve stok via StockService.
     */
    public function approveAdmin(MaterialRequest $mr, array $approvedItems, int $userId): MaterialRequest
    {
        return DB::transaction(function () use ($mr, $approvedItems, $userId) {
            foreach ($approvedItems as $ai) {
                $mrItem = MaterialRequestItem::find($ai['id']);
                if (!$mrItem || $mrItem->material_request_id !== $mr->id) continue;

                $qtyApproved = (float) $ai['qty_approved'];
                $mrItem->update(['qty_approved' => $qtyApproved]);

                if ($qtyApproved > 0) {
                    // Delegasi reserve ke StockService — sudah ada transaction + lockForUpdate
                    $this->stockService->reserveStock(
                        $mrItem->item_id,
                        $mr->from_warehouse_id,
                        $qtyApproved
                    );
                }
            }

            $mr->update([
                'status'      => 'pending_atasan',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $mr->fresh();
        });
    }

    /**
     * Ambil daftar Transfer Barang (MR type=transfer) dengan filter.
     */
    public function list(Request $request, $user): LengthAwarePaginator
    {
        $query = MaterialRequest::with(['fromWarehouse', 'toWarehouse', 'requester', 'approver', 'atasanApprover'])
            ->withCount('items')
            ->where('type', 'transfer')
            ->orderBy('created_at', 'desc');

        if (!$user->isSuperuser() && !$user->isAdminHO()) {
            $query->where(fn($q) => $q
                ->where('from_warehouse_id', $user->warehouse_id)
                ->orWhere('to_warehouse_id', $user->warehouse_id));
        }

        if ($request->status)            $query->where('status', $request->status);
        if ($request->from_warehouse_id) $query->where('from_warehouse_id', $request->from_warehouse_id);
        if ($request->to_warehouse_id)   $query->where('to_warehouse_id', $request->to_warehouse_id);
        if ($request->date_from)         $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)           $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->search)            $query->where('mr_number', 'ilike', "%{$request->search}%");

        return $query->paginate($request->per_page ?? 15);
    }

    /**
     * Kirim barang: buat Delivery Order + kurangi stok gudang asal.
     */
    public function kirim(array $validated, MaterialRequest $mr, int $userId): DeliveryOrder
    {
        if ($mr->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Transfer harus sudah diapprove sebelum dikirim',
            ]);
        }

        return DB::transaction(function () use ($validated, $mr, $userId) {
            $do = DeliveryOrder::create([
                'do_number'          => DeliveryOrder::generateNumber(),
                'material_request_id'=> $mr->id,
                'from_warehouse_id'  => $mr->from_warehouse_id,
                'to_warehouse_id'    => $mr->to_warehouse_id,
                'created_by'         => $userId,
                'status'             => 'sent',
                'sent_at'            => now(),
                'notes'              => $validated['notes'] ?? null,
            ]);

            $itemIndex = 1;

            foreach ($validated['items'] as $itemData) {
                $mrItem   = MaterialRequestItem::findOrFail($itemData['id']);
                $qtySent  = (float) $itemData['qty_sent'];

                if ($qtySent <= 0) continue;

                DeliveryOrderItem::create([
                    'delivery_order_id' => $do->id,
                    'item_id'           => $mrItem->item_id,
                    'qty_sent'          => $qtySent,
                ]);

                $mrItem->update(['qty_sent' => $mrItem->qty_sent + $qtySent]);

                $this->deductStock(
                    $mrItem->item_id,
                    $mr->from_warehouse_id,
                    $mr->to_warehouse_id,
                    $qtySent,
                    $do,
                    $itemIndex,
                    $userId
                );

                $itemIndex++;
            }

            $mr->update([
                'status'        => 'dispatched',
                'dispatched_by' => $userId,
                'dispatched_at' => now(),
            ]);

            return $do->load('items.item', 'fromWarehouse', 'toWarehouse');
        });
    }

    /**
     * Konfirmasi penerimaan barang: tambah stok gudang tujuan.
     */
    public function terima(array $validated, DeliveryOrder $do, int $userId): void
    {
        if ($do->status !== 'sent') {
            throw ValidationException::withMessages([
                'status' => 'Surat Jalan sudah pernah dikonfirmasi',
            ]);
        }

        DB::transaction(function () use ($do, $validated, $userId) {
            $itemIndex = 1;

            foreach ($validated['items'] as $itemData) {
                $doItem      = DeliveryOrderItem::find($itemData['id']);
                if (!$doItem || $doItem->delivery_order_id !== $do->id) continue;

                $qtyReceived = (float) $itemData['qty_received'];
                $doItem->update(['qty_received' => $qtyReceived]);

                if ($qtyReceived <= 0) continue;

                $this->addStock(
                    $doItem->item_id,
                    $do->from_warehouse_id,
                    $do->to_warehouse_id,
                    $qtyReceived,
                    $do,
                    $itemIndex,
                    $userId
                );

                // Update qty_received di MR item
                $mrItem = $do->materialRequest?->items()
                    ->where('item_id', $doItem->item_id)->first();
                $mrItem?->increment('qty_received', $qtyReceived);

                $itemIndex++;
            }

            $do->update([
                'status'           => 'received',
                'received_by'      => $userId,
                'received_by_name' => $validated['received_by_name'],
                'received_at'      => now(),
                'receive_notes'    => $validated['notes'] ?? null,
            ]);

            if ($do->material_request_id) {
                MaterialRequest::find($do->material_request_id)?->update([
                    'status'      => 'received',
                    'received_by' => $userId,
                    'received_at' => now(),
                ]);
            }
        });
    }

    /**
     * Tolak Transfer MR.
     */
    public function reject(MaterialRequest $mr, string $reason, int $userId): MaterialRequest
    {
        $rejectableStatuses = ['pending_admin', 'pending_atasan'];

        if (!in_array($mr->status, $rejectableStatuses)) {
            throw ValidationException::withMessages([
                'status' => 'MR tidak bisa ditolak dari status ini',
            ]);
        }

        DB::transaction(function () use ($mr, $reason, $userId) {
            // Release reserved stock jika sudah di-approve admin
            if ($mr->status === 'pending_atasan') {
                $this->releaseReservedStock($mr);
            }

            $mr->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'approved_by'      => $userId,
                'approved_at'      => now(),
            ]);
        });

        return $mr->fresh();
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function deductStock(
        int $itemId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $qty,
        DeliveryOrder $do,
        int $index,
        int $userId
    ): void {
        $stock = ItemStock::where('item_id', $itemId)
            ->where('warehouse_id', $fromWarehouseId)
            ->lockForUpdate()
            ->first();

        if (!$stock) return;

        $qtyBefore = $stock->qty;
        $stock->decrement('qty', $qty);
        $stock->decrement('qty_reserved', min($qty, $stock->qty_reserved));

        // ── Pindahkan FIFO layers dari gudang asal ke gudang tujuan ──────────
        $qtyRemaining = $qty;
        $layers = StockLayer::forStock($itemId, $fromWarehouseId)
            ->available()
            ->fifo()
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($qtyRemaining <= 0) break;

            $ambil = min((float) $layer->qty_sisa, $qtyRemaining);

            // Kurangi layer asal
            $layer->qty_sisa -= $ambil;
            $layer->save();

            // Buat layer baru di gudang tujuan dengan harga asli terjaga
            StockLayer::create([
                'item_id'        => $itemId,
                'warehouse_id'   => $toWarehouseId,
                'qty_awal'       => $ambil,
                'qty_sisa'       => $ambil,
                'harga_satuan'   => $layer->harga_satuan,
                'tanggal_masuk'  => $layer->tanggal_masuk, // pertahankan tanggal asal
                'source_type'    => 'transfer',
                'reference_no'   => $do->do_number,
                'parent_layer_id'=> $layer->id,
                'created_by'     => $userId,
            ]);

            $qtyRemaining -= $ambil;
        }

        StockMovement::create([
            'item_id'           => $itemId,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id'   => $toWarehouseId,
            'type'              => 'transfer_out',
            'qty'               => $qty,
            'qty_before'        => $qtyBefore,
            'qty_after'         => $qtyBefore - $qty,
            'reference_no'      => $do->do_number . '-OUT-' . str_pad($index, 3, '0', STR_PAD_LEFT),
            'notes'             => "Transfer keluar via DO: {$do->do_number}",
            'moveable_type'     => DeliveryOrder::class,
            'moveable_id'       => $do->id,
            'movement_date'     => now()->toDateString(),
            'created_by'        => $userId,
        ]);
    }

    private function addStock(
        int $itemId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $qty,
        DeliveryOrder $do,
        int $index,
        int $userId
    ): void {
        $stock = ItemStock::firstOrCreate(
            ['item_id' => $itemId, 'warehouse_id' => $toWarehouseId],
            ['qty' => 0, 'qty_reserved' => 0, 'avg_price' => 0]
        );

        $qtyBefore = $stock->qty;
        $stock->increment('qty', $qty);
        // Catatan: layer FIFO sudah dibuat di deductStock, tidak perlu buat lagi di sini

        StockMovement::create([
            'item_id'           => $itemId,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id'   => $toWarehouseId,
            'type'              => 'transfer_in',
            'qty'               => $qty,
            'qty_before'        => $qtyBefore,
            'qty_after'         => $qtyBefore + $qty,
            'reference_no'      => $do->do_number . '-IN-' . str_pad($index, 3, '0', STR_PAD_LEFT),
            'notes'             => "Transfer masuk via DO: {$do->do_number}",
            'moveable_type'     => DeliveryOrder::class,
            'moveable_id'       => $do->id,
            'movement_date'     => now()->toDateString(),
            'created_by'        => $userId,
        ]);
    }

    private function releaseReservedStock(MaterialRequest $mr): void
    {
        foreach ($mr->items as $mrItem) {
            if ($mrItem->qty_approved > 0) {
                $stock = ItemStock::where('item_id', $mrItem->item_id)
                    ->where('warehouse_id', $mr->from_warehouse_id)
                    ->first();
                if ($stock) {
                    $stock->decrement('qty_reserved', min((float) $mrItem->qty_approved, (float) $stock->qty_reserved));
                }
            }
        }
    }
}