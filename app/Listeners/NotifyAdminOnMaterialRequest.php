<?php

namespace App\Listeners;

use App\Events\MaterialRequestUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyAdminOnMaterialRequest
 *
 * Menangani semua action pada Material Request (type: part/office):
 * created | submitted | authorized_chief | approved_manager
 * | approved_ho | rejected | dispatched | received
 */
class NotifyAdminOnMaterialRequest implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(MaterialRequestUpdated $event): void
    {
        $mr     = $event->mr;
        $number = $mr->mr_number;
        $status = $mr->status;

        $message = match ($event->action) {
            'created'           => 'Material Request ' . $number . ' telah dibuat.',
            'submitted'         => 'Material Request ' . $number . ' menunggu otorisasi Chief Mekanik.',
            'authorized_chief'  => 'Material Request ' . $number . ' diotorisasi Chief, menunggu Manager.',
            'approved_manager'  => 'Material Request ' . $number . ' disetujui Manager, menunggu Admin HO.',
            'approved_ho'       => 'Material Request ' . $number . ' disetujui Admin HO.',
            'rejected'          => 'Material Request ' . $number . ' ditolak.',
            'dispatched'        => 'Material Request ' . $number . ' telah diproses dan dikirim.',
            'received'          => 'Material Request ' . $number . ' telah diterima.',
            default             => 'Material Request ' . $number . ' diperbarui (status: ' . $status . ').',
        };

        $payload = [
            'type'              => 'material_request',
            'action'            => $event->action,
            'id'                => $mr->id,
            'mr_number'         => $mr->mr_number,
            'status'            => $mr->status,
            'from_warehouse_id' => $mr->from_warehouse_id,
            'to_warehouse_id'   => $mr->to_warehouse_id,
            'title'             => 'Material Request',
            'message'           => $message,
            'url'               => '/material-request/' . $mr->id,
        ];

        match ($event->action) {
            // Disubmit → notif Chief Mekanik
            'submitted' => $this->notifyByPermission('authorize-mr-chief', $payload),

            // Diotorisasi Chief → notif Manager
            'authorized_chief' => $this->notifyByPermission('approve-mr-manager', $payload),

            // Disetujui Manager → notif Admin HO
            'approved_manager' => $this->notifyAdmins($payload),

            // Disetujui HO / dispatched → notif admin gudang asal
            'approved_ho', 'dispatched' => $this->notifyWarehouseAdmins($mr->from_warehouse_id, $payload),

            // Ditolak → notif admin gudang asal
            'rejected' => $this->notifyWarehouseAdmins($mr->from_warehouse_id, $payload),

            // Diterima → notif superuser & admin_ho
            'received' => $this->notifyAdmins($payload),

            // Default (created, dll)
            default => $this->notifyAdmins($payload),
        };
    }

    private function notifyByPermission(string $permission, array $payload): void
    {
        $users = User::whereHas('roles.permissions', fn ($q) => $q->where('name', $permission))
            ->orWhereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->get();

        if ($users->isEmpty()) {
            $this->notifyAdmins($payload);
            return;
        }

        foreach ($users as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    private function notifyAdmins(array $payload): void
    {
        $users = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['superuser', 'admin_ho'])
        )->get();

        foreach ($users as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    private function notifyWarehouseAdmins(?int $warehouseId, array $payload): void
    {
        if (!$warehouseId) {
            $this->notifyAdmins($payload);
            return;
        }

        $admins = User::where('warehouse_id', $warehouseId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin_gudang', 'superuser']))
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(MaterialRequestUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyAdminOnMaterialRequest failed', [
            'mr_id' => $event->mr->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
