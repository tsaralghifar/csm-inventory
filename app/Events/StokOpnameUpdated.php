<?php

namespace App\Events;

use App\Models\StokOpname;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StokOpnameUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $action  created | updated | ajukan | setujui | tolak | deleted
     */
    public function __construct(
        public StokOpname $opname,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('stok-opname'),
            new Channel("stok-opname.{$this->opname->warehouse_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stok-opname.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'       => $this->action,
            'id'           => $this->opname->id,
            'nomor'        => $this->opname->nomor,
            'status'       => $this->opname->status,
            'warehouse_id' => $this->opname->warehouse_id,
        ];
    }
}
