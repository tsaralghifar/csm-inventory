<?php

namespace App\Events;

use App\Models\FuelLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FuelLogUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $action  created | updated | deleted
     */
    public function __construct(
        public FuelLog $log,
        public string $action = 'updated'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('fuel-log'),
            new Channel("fuel-log.{$this->log->warehouse_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'fuel-log.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'action'       => $this->action,
            'id'           => $this->log->id,
            'warehouse_id' => $this->log->warehouse_id,
            'log_date'     => $this->log->log_date,
        ];
    }
}
