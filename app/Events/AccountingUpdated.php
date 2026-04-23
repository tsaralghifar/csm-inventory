<?php
// app/Events/AccountingUpdated.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountingUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string   $type   supplier | invoice | payment | kas-besar | kas-kecil | jurnal
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
            new Channel('accounting'),
            new Channel("accounting-{$this->type}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'accounting.updated';
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
