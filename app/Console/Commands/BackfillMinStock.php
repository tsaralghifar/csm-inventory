<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\LowStockAlertService;
use Illuminate\Console\Command;

/**
 * BackfillMinStock — set min_stock massal untuk barang yang belum punya nilai.
 *
 * Cara pakai:
 *   # Lihat item mana saja yang belum punya min_stock (dry-run)
 *   php artisan inventory:backfill-min-stock --dry-run
 *
 *   # Set min_stock = 5 untuk semua item yang min_stock-nya masih 0
 *   php artisan inventory:backfill-min-stock --default=5
 *
 *   # Set berdasarkan persentase rata-rata stok saat ini (otomatis per item)
 *   php artisan inventory:backfill-min-stock --auto
 *
 * Setelah selesai, jalankan scan untuk langsung kirim notifikasi:
 *   php artisan inventory:scan-low-stock
 */
class BackfillMinStock extends Command
{
    protected $signature = 'inventory:backfill-min-stock
                            {--dry-run  : Tampilkan item tanpa min_stock tanpa mengubah data}
                            {--default= : Set nilai min_stock default untuk semua item yang min_stock = 0}
                            {--auto     : Set min_stock = 20%% dari total stok masing-masing item (min 1)}';

    protected $description = 'Isi min_stock untuk barang-barang yang belum memiliki nilai minimum stok';

    public function __construct(private LowStockAlertService $alertService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $items = Item::with('itemStocks')
            ->active()
            ->where('min_stock', '<=', 0)
            ->orderBy('name')
            ->get();

        if ($items->isEmpty()) {
            $this->info('✅ Semua barang aktif sudah memiliki min_stock > 0. Tidak ada yang perlu diupdate.');
            return self::SUCCESS;
        }

        $this->warn("Ditemukan {$items->count()} barang aktif dengan min_stock = 0:");

        $tableRows = $items->map(fn($item) => [
            $item->id,
            $item->name,
            $item->part_number ?? '-',
            number_format((float) $item->getTotalStock(), 2),
            $this->suggestMinStock($item),
        ])->toArray();

        $this->table(
            ['ID', 'Nama Barang', 'Part Number', 'Total Stok', 'Saran Min Stock'],
            $tableRows
        );

        if ($this->option('dry-run')) {
            $this->info('Mode dry-run: tidak ada perubahan yang disimpan.');
            $this->line('Gunakan --default=N atau --auto untuk mengupdate data.');
            return self::SUCCESS;
        }

        if (!$this->option('default') && !$this->option('auto')) {
            $this->error('Pilih salah satu opsi: --dry-run, --default=N, atau --auto');
            return self::FAILURE;
        }

        $updated = 0;

        foreach ($items as $item) {
            $newMinStock = $this->option('auto')
                ? $this->suggestMinStock($item)
                : (float) $this->option('default');

            if ($newMinStock <= 0) {
                $newMinStock = 1; // fallback minimum
            }

            $item->update(['min_stock' => $newMinStock]);
            $updated++;

            // Trigger alert check untuk setiap item yang diupdate
            foreach ($item->itemStocks as $stock) {
                $this->alertService->checkAndAlert($stock->warehouse_id, $item->id);
            }
        }

        $this->info("✅ {$updated} barang berhasil diupdate min_stock-nya.");
        $this->info('Notifikasi stok kritis sudah dikirim untuk barang yang memenuhi syarat.');

        return self::SUCCESS;
    }

    /**
     * Hitung saran min_stock = 20% dari total stok, minimal 1
     */
    private function suggestMinStock(Item $item): float
    {
        $totalStock = $item->getTotalStock();
        $suggestion = ceil($totalStock * 0.2);
        return max(1, $suggestion);
    }
}
