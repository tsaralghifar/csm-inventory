<?php

namespace App\Events;

use App\Models\PurchaseOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $action  created | sent_to_vendor
     */
    public function __construct(
        public PurchaseOrder $po,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('purchase-order')];
    }

    public function broadcastAs(): string
    {
        return 'po.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'      => $this->action,
            'id'          => $this->po->id,
            'po_number'   => $this->po->po_number,
            'status'      => $this->po->status,
            'warehouse_id'=> $this->po->warehouse_id,
        ];
    }
}
