<?php
// app/Events/MasterDataUpdated.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MasterDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string   $type   gudang | kategori | unit | karyawan
     * @param string   $action created | updated | deleted
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
            new Channel('master-data'),
            new Channel("master-{$this->type}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'master.updated';
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
