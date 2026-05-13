<?php

namespace App\Events;

use App\Models\Item;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string   $action      created | updated | deleted | stock_in | stock_out
     * @param int|null $warehouseId warehouse yang stoknya berubah (untuk stock_in / stock_out)
     */
    public function __construct(
        public Item $item,
        public string $action = 'updated',
        public ?int $warehouseId = null
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('master-data')];
    }

    public function broadcastAs(): string
    {
        return 'item.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'       => $this->action,
            'id'           => $this->item->id,
            'name'         => $this->item->name,
            'warehouse_id' => $this->warehouseId,
        ];
    }
}
