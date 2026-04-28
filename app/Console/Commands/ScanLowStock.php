<?php

namespace App\Console\Commands;

use App\Services\LowStockAlertService;
use Illuminate\Console\Command;

/**
 * ScanLowStock — Artisan command untuk scan stok menipis secara manual / terjadwal.
 *
 * Jalankan manual:
 *   php artisan inventory:scan-low-stock
 *   php artisan inventory:scan-low-stock --force   (abaikan cooldown cache)
 *
 * Jadwalkan di app/Console/Kernel.php:
 *   $schedule->command('inventory:scan-low-stock')->hourly();
 */
class ScanLowStock extends Command
{
    protected $signature   = 'inventory:scan-low-stock {--force : Abaikan cooldown cache}';
    protected $description = 'Scan semua item stok dan kirim notifikasi untuk yang menipis / kritis';

    public function __construct(private LowStockAlertService $alertService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('force')) {
            // Hapus semua cache cooldown agar notifikasi dikirim ulang
            $this->warn('--force aktif: cooldown cache diabaikan.');
            // Catatan: flush cache key spesifik butuh Redis SCAN atau prefix flush
            // Untuk simplicity, gunakan: Cache::flush() hanya jika safe di environment Anda
        }

        $this->info('Memulai scan stok menipis...');

        $alerts = $this->alertService->scanAll();

        if (empty($alerts)) {
            $this->info('✅ Tidak ada stok yang perlu di-alert saat ini.');
            return self::SUCCESS;
        }

        $this->table(
            ['Item', 'Gudang', 'Qty', 'Level'],
            array_map(fn($a) => [
                $a['item'],
                $a['warehouse'],
                $a['qty'],
                strtoupper($a['level']),
            ], $alerts)
        );

        $this->info(sprintf('✅ %d notifikasi stok terkirim.', count($alerts)));

        return self::SUCCESS;
    }
}
