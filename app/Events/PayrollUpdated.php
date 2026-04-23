<?php
// app/Events/PayrollUpdated.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string   $type   payroll | komponen | pinjaman
     * @param string   $action created | updated | deleted | approved | rejected
     * @param int|null $id
     */
    public function __construct(
        public string  $type,
        public string  $action = 'updated',
        public ?int    $id     = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('payroll'),
            new Channel("payroll-{$this->type}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payroll.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type'   => $this->type,
            'action' => $this->action,
            'id'     => $this->id,
        ];
    }
}
