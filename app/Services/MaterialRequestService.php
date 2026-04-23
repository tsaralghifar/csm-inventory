<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialRequestService
{
    public function __construct(private StockService $stockService) {}

    public function create(array $data, int $userId): MaterialRequest
    {
        return DB::transaction(function () use ($data, $userId) {
            $mr = MaterialRequest::create([
                'mr_number' => MaterialRequest::generateNumber(),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'status' => 'draft',
                'requested_by' => $userId,
                'notes' => $data['notes'] ?? null,
                'needed_date' => $data['needed_date'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                MaterialRequestItem::create([
                    'material_request_id' => $mr->id,
                    'item_id' => $item['item_id'],
                    'qty_request' => $item['qty'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $mr->load('items.item', 'fromWarehouse', 'toWarehouse', 'requester');
        });
    }

    public function submit(MaterialRequest $mr): MaterialRequest
    {
        if (!in_array($mr->status, ['draft'])) {
            throw ValidationException::withMessages(['status' => 'MR tidak bisa disubmit dari status: ' . $mr->status]);
        }
        $mr->update(['status' => 'submitted', 'submitted_at' => now()]);
        return $mr;
    }

    public function approve(MaterialRequest $mr, array $approvedItems, int $userId): MaterialRequest
    {
        return DB::transaction(function () use ($mr, $approvedItems, $userId) {
            $approvableStatuses = ['submitted', 'pending_ho', 'manager_approved'];
            if (!in_array($mr->status, $approvableStatuses)) {
                throw ValidationException::withMessages(['status' => 'MR harus berstatus submitted/pending_ho/manager_approved untuk di-approve']);
            }

            foreach ($approvedItems as $ai) {
                $mrItem = MaterialRequestItem::find($ai['id']);
                if (!$mrItem) continue;

                $qtyApproved = (float) $ai['qty_approved'];
                $mrItem->update(['qty_approved' => $qtyApproved]);

                // Reserve stock di gudang SUMBER (from_warehouse_id = HO)
                if ($qtyApproved > 0) {
                    $this->stockService->reserveStock(
                        $mrItem->item_id,
                        $mr->from_warehouse_id,  // FIX: was to_warehouse_id
                        $qtyApproved
                    );
                }
            }

            $mr->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $mr->fresh('items.item');
        });
    }

    public function dispatch(MaterialRequest $mr, array $data, int $userId): DeliveryOrder
    {
        return DB::transaction(function () use ($mr, $data, $userId) {
            if ($mr->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'MR harus berstatus approved untuk dikirim']);
            }

            // Create Delivery Order
            $do = DeliveryOrder::create([
                'do_number' => DeliveryOrder::generateNumber(),
                'material_request_id' => $mr->id,
                'from_warehouse_id' => $mr->from_warehouse_id,  // FIX: HO sebagai sumber
                'to_warehouse_id' => $mr->to_warehouse_id,      // FIX: Site sebagai tujuan
                'status' => 'sent',
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_plate' => $data['vehicle_plate'] ?? null,
                'sent_by' => $userId,
                'sent_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Process each item
            foreach ($mr->items as $mrItem) {
                $qtySent = (float) ($data['items'][$mrItem->id] ?? $mrItem->qty_approved);
                if ($qtySent <= 0) continue;

                DeliveryOrderItem::create([
                    'delivery_order_id' => $do->id,
                    'item_id' => $mrItem->item_id,
                    'qty_sent' => $qtySent,
                ]);

                // Transfer stock dari HO ke Site
                $this->stockService->transfer([
                    'item_id' => $mrItem->item_id,
                    'from_warehouse_id' => $mr->from_warehouse_id,  // FIX: HO sebagai sumber
                    'to_warehouse_id' => $mr->to_warehouse_id,       // FIX: Site sebagai tujuan
                    'qty' => $qtySent,
                    'notes' => "Transfer via DO: {$do->do_number}",
                    'moveable_type' => DeliveryOrder::class,
                    'moveable_id' => $do->id,
                ], $userId);

                $mrItem->update(['qty_sent' => $qtySent]);
            }

            $mr->update([
                'status' => 'dispatched',
                'dispatched_by' => $userId,
                'dispatched_at' => now(),
            ]);

            return $do->load('items.item', 'fromWarehouse', 'toWarehouse');
        });
    }

    public function confirmReceive(DeliveryOrder $do, array $data, int $userId): DeliveryOrder
    {
        return DB::transaction(function () use ($do, $data, $userId) {
            foreach ($do->items as $doItem) {
                $qtyReceived = (float) ($data['items'][$doItem->id] ?? $doItem->qty_sent);
                $doItem->update(['qty_received' => $qtyReceived]);

                $mrItem = $do->materialRequest->items()
                    ->where('item_id', $doItem->item_id)->first();
                if ($mrItem) {
                    $mrItem->update(['qty_received' => $mrItem->qty_received + $qtyReceived]);
                }
            }

            $do->update([
                'status' => 'received',
                'received_by' => $userId,
                'received_at' => now(),
                'receive_notes' => $data['notes'] ?? null,
            ]);

            $do->materialRequest->update([
                'status' => 'received',
                'received_by' => $userId,
                'received_at' => now(),
            ]);

            return $do->fresh('items.item');
        });
    }

    public function reject(MaterialRequest $mr, string $reason, int $userId): MaterialRequest
    {
        return DB::transaction(function () use ($mr, $reason, $userId) {
            // Release reserved stock di gudang SUMBER (from_warehouse_id = HO)
            foreach ($mr->items as $mrItem) {
                if ($mrItem->qty_approved > 0) {
                    $this->stockService->releaseReserve(
                        $mrItem->item_id,
                        $mr->from_warehouse_id,  // FIX: was to_warehouse_id
                        (float) $mrItem->qty_approved
                    );
                }
            }

            $mr->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $mr;
        });
    }
}