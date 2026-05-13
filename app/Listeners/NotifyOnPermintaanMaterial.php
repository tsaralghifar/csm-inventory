<?php

namespace App\Listeners;

use App\Events\PermintaanMaterialUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyOnPermintaanMaterial
 *
 * Kirim notifikasi ke pihak yang relevan di setiap tahap alur
 * persetujuan Permintaan Material (PM):
 *
 * created          → pemohon & admin gudang
 * submitted        → Chief Mekanik (authorize-mr-chief)
 * authorized_chief → Manager (approve-mr-manager)
 * approved_manager → Admin HO (approve-pm-ho)
 * approved_ho      → Admin HO (reminder: ajukan PO ke Purchasing)
 * submit_purchasing→ Purchasing / superuser
 * rejected         → pemohon (admin gudang asal)
 */
class NotifyOnPermintaanMaterial implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(PermintaanMaterialUpdated $event): void
    {
        $pm     = $event->pm;
        $nomor  = $pm->nomor;
        $status = $pm->status;

        $message = match ($event->action) {
            'created'           => 'Permintaan Material ' . $nomor . ' telah dibuat.',
            'submitted'         => 'Permintaan Material ' . $nomor . ' menunggu otorisasi Chief Mekanik.',
            'authorized_chief'  => 'Permintaan Material ' . $nomor . ' diotorisasi Chief, menunggu Manager.',
            'approved_manager'  => 'Permintaan Material ' . $nomor . ' disetujui Manager, menunggu Admin HO.',
            'approved_ho'       => 'Permintaan Material ' . $nomor . ' disetujui Admin HO. Siap diajukan ke Purchasing.',
            'submit_purchasing' => 'Permintaan Material ' . $nomor . ' diajukan ke Purchasing. Menunggu pembuatan PO.',
            'rejected'          => 'Permintaan Material ' . $nomor . ' ditolak.',
            'deleted'           => 'Permintaan Material ' . $nomor . ' dihapus.',
            default             => 'Permintaan Material ' . $nomor . ' diperbarui (status: ' . $status . ').',
        };

        $payload = [
            'type'         => 'permintaan_material',
            'action'       => $event->action,
            'id'           => $pm->id,
            'nomor'        => $pm->nomor,
            'status'       => $pm->status,
            'warehouse_id' => $pm->warehouse_id,
            'title'        => 'Permintaan Material',
            'message'      => $message,
            'url'          => '/permintaan-material/' . $pm->id,
        ];

        match ($event->action) {
            // Saat disubmit → notif ke user dengan permission authorize-mr-chief
            'submitted' => $this->notifyByPermission('authorize-mr-chief', $payload),

            // Saat diotorisasi Chief → notif ke Manager (approve-mr-manager)
            'authorized_chief' => $this->notifyByPermission('approve-mr-manager', $payload),

            // Saat disetujui Manager → notif ke Admin HO (approve-pm-ho)
            'approved_manager' => $this->notifyByPermission('approve-pm-ho', $payload),

            // Saat disetujui HO atau diajukan Purchasing → notif superuser & admin_ho
            'approved_ho', 'submit_purchasing' => $this->notifyAdmins($payload),

            // Saat ditolak → notif admin gudang asal & pemohon
            'rejected' => $this->notifyWarehouseAdmins($pm->warehouse_id, $payload),

            // Default (created, deleted, dll) → notif superuser
            default => $this->notifySuperusers($payload),
        };
    }

    private function notifyByPermission(string $permission, array $payload): void
    {
        $users = User::whereHas('roles.permissions', fn ($q) => $q->where('name', $permission))
            ->orWhereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->get();

        // Fallback ke superuser jika tidak ada pemegang permission
        if ($users->isEmpty()) {
            $users = User::whereHas('roles', fn ($q) => $q->where('name', 'superuser'))->get();
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
            $this->notifySuperusers($payload);
            return;
        }

        $admins = User::where('warehouse_id', $warehouseId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin_gudang', 'superuser']))
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new DocumentStatusNotification($payload));
        }
    }

    private function notifySuperusers(array $payload): void
    {
        $superusers = User::whereHas('roles', fn ($q) => $q->where('name', 'superuser'))->get();
        foreach ($superusers as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(PermintaanMaterialUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyOnPermintaanMaterial failed', [
            'pm_id' => $event->pm->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
