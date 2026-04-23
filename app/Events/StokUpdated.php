<?php
// app/Events/StokUpdated.php
namespace App\Events;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StokUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $warehouseId,
        public string $action = 'updated',
        public ?int $itemId = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('stok'),                          // semua gudang
            new Channel("stok.{$this->warehouseId}"),    // gudang spesifik
        ];
    }
    public function broadcastAs(): string { return 'stok.updated'; }
    public function broadcastWith(): array {
        return ['action' => $this->action, 'warehouse_id' => $this->warehouseId, 'item_id' => $this->itemId];
    }
}
