<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Daftarkan scheduled commands di sini.
     *
     * Pastikan cron berjalan di server:
     *   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
     */
    protected function schedule(Schedule $schedule): void
    {
        // Scan stok menipis setiap jam — kirim notifikasi jika ada yang kritis
        $schedule->command('inventory:scan-low-stock')
            ->hourly()
            ->name('low-stock-scan')
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}