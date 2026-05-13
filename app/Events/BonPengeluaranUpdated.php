<?php

namespace App\Events;

use App\Models\BonPengeluaran;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BonPengeluaranUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $action  created | pending_confirmation | confirmed
     *                        | rejected_by_mechanic | issued | deleted
     */
    public function __construct(
        public BonPengeluaran $bon,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('bon-pengeluaran')];
    }

    public function broadcastAs(): string
    {
        return 'bon.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'       => $this->action,
            'id'           => $this->bon->id,
            'bon_number'   => $this->bon->bon_number,
            'status'       => $this->bon->status,
            'warehouse_id' => $this->bon->warehouse_id,
            'unit_code'    => $this->bon->unit_code,
        ];
    }
}