<?php

namespace App\Listeners;

use App\Events\ReturBarangUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyAdminOnReturBarang
 *
 * Kirim notifikasi ke admin saat Retur Barang dibuat atau dikonfirmasi.
 */
class NotifyAdminOnReturBarang implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(ReturBarangUpdated $event): void
    {
        $retur  = $event->retur;
        $number = $retur->retur_number;
        $vendor = $retur->vendor_name;
        $status = $retur->status;

        $message = match ($event->action) {
            'created'   => 'Retur Barang ' . $number . ' ke vendor ' . $vendor . ' telah dibuat.',
            'confirmed' => 'Retur Barang ' . $number . ' telah dikonfirmasi. Stok telah diperbarui.',
            default     => 'Retur Barang ' . $number . ' diperbarui (status: ' . $status . ').',
        };

        $payload = [
            'type'               => 'retur_barang',
            'action'             => $event->action,
            'id'                 => $retur->id,
            'retur_number'       => $retur->retur_number,
            'status'             => $retur->status,
            'warehouse_id'       => $retur->warehouse_id,
            'purchase_order_id'  => $retur->purchase_order_id,
            'title'              => 'Retur Barang',
            'message'            => $message,
            'url'                => '/retur-barang/' . $retur->id,
        ];

        $recipients = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['superuser', 'admin_ho'])
        )->get();

        foreach ($recipients as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(ReturBarangUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyAdminOnReturBarang failed', [
            'retur_id' => $event->retur->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}