<?php

namespace App\Events;

use App\Models\SuratJalan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SuratJalanUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $action  created | received
     */
    public function __construct(
        public SuratJalan $suratJalan,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('surat-jalan')];
    }

    public function broadcastAs(): string
    {
        return 'sj.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'              => $this->action,
            'id'                  => $this->suratJalan->id,
            'sj_number'           => $this->suratJalan->sj_number,
            'status'              => $this->suratJalan->status,
            'purchase_order_id'   => $this->suratJalan->purchase_order_id,
            'warehouse_id'        => $this->suratJalan->warehouse_id,
        ];
    }
}
