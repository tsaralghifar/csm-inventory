<?php
// app/Events/MaterialRequestUpdated.php
namespace App\Events;

use App\Models\MaterialRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaterialRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MaterialRequest $mr, public string $action = 'updated') {}

    public function broadcastOn(): array { return [new Channel('material-request')]; }
    public function broadcastAs(): string { return 'mr.updated'; }
    public function broadcastWith(): array {
        return ['action' => $this->action, 'id' => $this->mr->id, 'mr_number' => $this->mr->mr_number, 'status' => $this->mr->status];
    }
}
