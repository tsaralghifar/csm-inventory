<?php

namespace App\Listeners;

use App\Events\BonPengeluaranUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyAdminOnBonPengeluaran
 *
 * Kirim notifikasi ke admin saat Bon Pengeluaran dibuat atau dikeluarkan.
 */
class NotifyAdminOnBonPengeluaran implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(BonPengeluaranUpdated $event): void
    {
        $bon    = $event->bon;
        $number = $bon->bon_number;
        $status = $bon->status;

        $message = match ($event->action) {
            'created' => 'Bon Pengeluaran ' . $number . ' telah dibuat.',
            'issued'  => 'Bon Pengeluaran ' . $number . ' telah dikeluarkan. Stok telah dikurangi.',
            default   => 'Bon Pengeluaran ' . $number . ' diperbarui (status: ' . $status . ').',
        };

        $payload = [
            'type'         => 'bon_pengeluaran',
            'action'       => $event->action,
            'id'           => $bon->id,
            'bon_number'   => $bon->bon_number,
            'status'       => $bon->status,
            'warehouse_id' => $bon->warehouse_id,
            'title'        => 'Bon Pengeluaran',
            'message'      => $message,
            'url'          => '/bon-pengeluaran/' . $bon->id,
        ];

        $recipients = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['superuser', 'admin_ho'])
        )->get();

        foreach ($recipients as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(BonPengeluaranUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyAdminOnBonPengeluaran failed', [
            'bon_id' => $event->bon->id,
            'error'  => $exception->getMessage(),
        ]);
    }
}