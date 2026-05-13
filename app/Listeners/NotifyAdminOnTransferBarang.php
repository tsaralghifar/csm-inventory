<?php

namespace App\Listeners;

use App\Events\TransferBarangUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyAdminOnTransferBarang
 *
 * Menangani semua action pada Transfer Barang:
 * created | submitted | approved_admin | approved_atasan
 * | dispatched | received | rejected
 */
class NotifyAdminOnTransferBarang implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(TransferBarangUpdated $event): void
    {
        $mr     = $event->mr;
        $number = $mr->mr_number;
        $status = $mr->status;

        $message = match ($event->action) {
            'created'          => 'Transfer Barang ' . $number . ' telah dibuat.',
            'submitted'        => 'Transfer Barang ' . $number . ' menunggu persetujuan Admin.',
            'approved_admin'   => 'Transfer Barang ' . $number . ' disetujui Admin, menunggu persetujuan Atasan.',
            'approved_atasan'  => 'Transfer Barang ' . $number . ' disetujui Atasan. Siap untuk pengiriman.',
            'dispatched'       => 'Transfer Barang ' . $number . ' telah dikirim.',
            'received'         => 'Transfer Barang ' . $number . ' telah diterima di gudang tujuan.',
            'rejected'         => 'Transfer Barang ' . $number . ' ditolak.',
            default            => 'Transfer Barang ' . $number . ' diperbarui (status: ' . $status . ').',
        };

        $payload = [
            'type'              => 'transfer_barang',
            'action'            => $event->action,
            'id'                => $mr->id,
            'mr_number'         => $mr->mr_number,
            'status'            => $mr->status,
            'from_warehouse_id' => $mr->from_warehouse_id,
            'to_warehouse_id'   => $mr->to_warehouse_id,
            'title'             => 'Transfer Barang',
            'message'           => $message,
            'url'               => '/transfer-barang/' . $mr->id,
        ];

        match ($event->action) {
            // Disubmit → notif Admin HO (approve-mr)
            'submitted' => $this->notifyByPermission('approve-mr', $payload),

            // Disetujui Admin → notif Atasan (approve-mr-manager)
            'approved_admin' => $this->notifyByPermission('approve-mr-manager', $payload),

            // Disetujui Atasan → notif admin gudang asal (siap kirim)
            'approved_atasan' => $this->notifyWarehouseAdmins($mr->from_warehouse_id, $payload),

            // Dikirim → notif admin gudang tujuan
            'dispatched' => $this->notifyWarehouseAdmins($mr->to_warehouse_id, $payload),

            // Diterima → notif superuser & admin_ho
            'received' => $this->notifyAdmins($payload),

            // Ditolak → notif admin gudang asal
            'rejected' => $this->notifyWarehouseAdmins($mr->from_warehouse_id, $payload),

            // Default → superuser & admin_ho
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

    public function failed(TransferBarangUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyAdminOnTransferBarang failed', [
            'mr_id' => $event->mr->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
