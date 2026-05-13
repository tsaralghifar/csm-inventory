<?php

namespace App\Events;

use App\Models\PermintaanMaterial;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermintaanMaterialUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $action  created | submitted | authorized_chief | approved_manager
     *                        | approved_ho | submit_purchasing | rejected | deleted
     */
    public function __construct(
        public PermintaanMaterial $pm,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('permintaan-material')];
    }

    public function broadcastAs(): string
    {
        return 'pm.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'       => $this->action,
            'id'           => $this->pm->id,
            'nomor'        => $this->pm->nomor,
            'status'       => $this->pm->status,
            'warehouse_id' => $this->pm->warehouse_id,
            'type'         => $this->pm->type,
        ];
    }
}
