<?php

namespace App\Events;

use App\Models\ReturBarang;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturBarangUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $action  created | confirmed
     */
    public function __construct(
        public ReturBarang $retur,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('retur-barang')];
    }

    public function broadcastAs(): string
    {
        return 'retur.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'             => $this->action,
            'id'                 => $this->retur->id,
            'retur_number'       => $this->retur->retur_number,
            'status'             => $this->retur->status,
            'warehouse_id'       => $this->retur->warehouse_id,
            'purchase_order_id'  => $this->retur->purchase_order_id,
        ];
    }
}
