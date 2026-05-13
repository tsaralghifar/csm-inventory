<?php

namespace App\Listeners;

use App\Events\SuratJalanUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyAdminOnSuratJalan
 *
 * Kirim notifikasi ke admin_ho / superuser setiap kali
 * Surat Jalan (TTB) dibuat atau dikonfirmasi penerimaan.
 */
class NotifyAdminOnSuratJalan implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(SuratJalanUpdated $event): void
    {
        $sj          = $event->suratJalan;
        $number      = $sj->sj_number;
        $received_by = $sj->received_by ?? '-';

        $message = match ($event->action) {
            'created'  => 'Tanda Terima Barang ' . $number . ' telah dibuat.',
            'received' => 'TTB ' . $number . ' telah dikonfirmasi diterima oleh ' . $received_by . '.',
            default    => 'TTB ' . $number . ' diperbarui.',
        };

        $payload = [
            'type'         => 'surat_jalan',
            'action'       => $event->action,
            'id'           => $sj->id,
            'sj_number'    => $sj->sj_number,
            'status'       => $sj->status,
            'warehouse_id' => $sj->warehouse_id,
            'title'        => 'Surat Jalan / TTB',
            'message'      => $message,
            'url'          => '/surat-jalan/' . $sj->id,
        ];

        // Notifikasi ke superuser dan admin_ho
        $recipients = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['superuser', 'admin_ho'])
        )->get();

        foreach ($recipients as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }

        // Notifikasi ke admin gudang terkait saat barang diterima
        if ($event->action === 'received' && $sj->warehouse_id) {
            $warehouseAdmins = User::where('warehouse_id', $sj->warehouse_id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin_gudang'))
                ->get();

            foreach ($warehouseAdmins as $admin) {
                $admin->notify(new DocumentStatusNotification($payload));
            }
        }
    }

    public function failed(SuratJalanUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyAdminOnSuratJalan failed', [
            'sj_id' => $event->suratJalan->id,
            'error' => $exception->getMessage(),
        ]);
    }
}