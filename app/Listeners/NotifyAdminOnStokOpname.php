<?php

namespace App\Listeners;

use App\Events\StokOpnameUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyAdminOnStokOpname
 *
 * Kirim notifikasi ke admin_ho / superuser saat Stok Opname
 * diajukan, disetujui, atau ditolak. Admin gudang dinotifikasi
 * saat ada keputusan (setujui/tolak) dari HO.
 */
class NotifyAdminOnStokOpname implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(StokOpnameUpdated $event): void
    {
        $opname = $event->opname;
        $nomor  = $opname->nomor;
        $status = $opname->status;

        $message = match ($event->action) {
            'created'  => 'Stok Opname ' . $nomor . ' telah dibuat.',
            'ajukan'   => 'Stok Opname ' . $nomor . ' diajukan untuk persetujuan HO.',
            'setujui'  => 'Stok Opname ' . $nomor . ' telah disetujui. Penyesuaian stok diterapkan.',
            'tolak'    => 'Stok Opname ' . $nomor . ' ditolak.',
            'deleted'  => 'Stok Opname ' . $nomor . ' telah dihapus.',
            default    => 'Stok Opname ' . $nomor . ' diperbarui (status: ' . $status . ').',
        };

        $payload = [
            'type'         => 'stok_opname',
            'action'       => $event->action,
            'id'           => $opname->id,
            'nomor'        => $opname->nomor,
            'status'       => $opname->status,
            'warehouse_id' => $opname->warehouse_id,
            'title'        => 'Stok Opname',
            'message'      => $message,
            'url'          => '/stok-opname/' . $opname->id,
        ];

        // Saat diajukan - notif admin_ho & superuser
        if ($event->action === 'ajukan') {
            $recipients = User::whereHas('roles', fn ($q) =>
                $q->whereIn('name', ['superuser', 'admin_ho'])
            )->get();

            foreach ($recipients as $user) {
                $user->notify(new DocumentStatusNotification($payload));
            }
            return;
        }

        // Saat disetujui / ditolak - notif admin gudang yang mengajukan
        if (in_array($event->action, ['setujui', 'tolak']) && $opname->warehouse_id) {
            $warehouseAdmins = User::where('warehouse_id', $opname->warehouse_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin_gudang', 'superuser']))
                ->get();

            foreach ($warehouseAdmins as $admin) {
                $admin->notify(new DocumentStatusNotification($payload));
            }
            return;
        }

        // Aksi lain - notif superuser saja
        $superusers = User::whereHas('roles', fn ($q) => $q->where('name', 'superuser'))->get();
        foreach ($superusers as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(StokOpnameUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyAdminOnStokOpname failed', [
            'opname_id' => $event->opname->id,
            'error'     => $exception->getMessage(),
        ]);
    }
}