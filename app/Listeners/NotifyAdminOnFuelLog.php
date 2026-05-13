<?php

namespace App\Listeners;

use App\Events\FuelLogUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyAdminOnFuelLog
 *
 * Kirim notifikasi ke admin gudang & admin_ho saat FuelLog dicatat.
 * Notifikasi hanya dikirim jika stok BBM setelah transaksi < threshold
 * (default: 200 liter) supaya tidak spam setiap entri.
 */
class NotifyAdminOnFuelLog implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    /** Threshold stok BBM untuk trigger notifikasi (liter) */
    private const LOW_FUEL_THRESHOLD = 200;

    public function handle(FuelLogUpdated $event): void
    {
        $log    = $event->log;
        $action = $event->action;

        // Hanya proses saat log baru dibuat
        if ($action !== 'created') {
            return;
        }

        $stockAfter = (float) ($log->stock_after ?? 0);

        // Hanya kirim notifikasi jika stok BBM di bawah threshold
        if ($stockAfter >= self::LOW_FUEL_THRESHOLD) {
            return;
        }

        $level   = $stockAfter <= 0 ? 'Habis' : 'Menipis';
        $message = 'Stok BBM gudang ' . ($log->warehouse->name ?? '-') . ' ' . $level
            . '. Sisa: ' . number_format($stockAfter, 1) . ' liter.';

        $payload = [
            'type'         => 'fuel_log',
            'action'       => 'low_fuel',
            'id'           => $log->id,
            'warehouse_id' => $log->warehouse_id,
            'stock_after'  => $stockAfter,
            'title'        => 'Peringatan BBM',
            'message'      => $message,
            'url'          => '/fuel-log?warehouse=' . $log->warehouse_id,
        ];

        // Notif superuser & admin_ho
        $recipients = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['superuser', 'admin_ho'])
        )->get();

        foreach ($recipients as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }

        // Notif admin gudang setempat
        $warehouseAdmins = User::where('warehouse_id', $log->warehouse_id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin_gudang'))
            ->get();

        foreach ($warehouseAdmins as $admin) {
            $admin->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(FuelLogUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyAdminOnFuelLog failed', [
            'log_id' => $event->log->id ?? null,
            'error'  => $exception->getMessage(),
        ]);
    }
}
