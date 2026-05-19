<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillHargaSatuan extends Command
{
    protected $signature   = 'inventory:backfill-harga-satuan';
    protected $description = 'Isi kolom harga_satuan pada bon_pengeluaran_items yang masih 0';

    public function handle(): int
    {
        $this->info('Memulai backfill harga_satuan...');

        // Update dari avg_price item_stocks (per warehouse bon)
        $affected1 = DB::statement("
            UPDATE bon_pengeluaran_items bpi
            SET harga_satuan = (
                SELECT ist.avg_price
                FROM item_stocks ist
                INNER JOIN bon_pengeluaran bp ON bp.id = bpi.bon_pengeluaran_id
                WHERE ist.item_id = bpi.item_id
                  AND ist.warehouse_id = bp.warehouse_id
                  AND ist.avg_price > 0
                LIMIT 1
            )
            WHERE bpi.item_id IS NOT NULL
              AND (bpi.harga_satuan IS NULL OR bpi.harga_satuan = 0)
              AND EXISTS (
                SELECT 1 FROM item_stocks ist2
                INNER JOIN bon_pengeluaran bp2 ON bp2.id = bpi.bon_pengeluaran_id
                WHERE ist2.item_id = bpi.item_id
                  AND ist2.warehouse_id = bp2.warehouse_id
                  AND ist2.avg_price > 0
              )
        ");

        $this->line('  ✔ Tahap 1 (avg_price per warehouse): selesai');

        // Fallback: update dari avg_price item_stocks (warehouse manapun)
        $affected2 = DB::statement("
            UPDATE bon_pengeluaran_items bpi
            SET harga_satuan = (
                SELECT ist.avg_price
                FROM item_stocks ist
                WHERE ist.item_id = bpi.item_id
                  AND ist.avg_price > 0
                ORDER BY ist.avg_price DESC
                LIMIT 1
            )
            WHERE bpi.item_id IS NOT NULL
              AND (bpi.harga_satuan IS NULL OR bpi.harga_satuan = 0)
              AND EXISTS (
                SELECT 1 FROM item_stocks ist2
                WHERE ist2.item_id = bpi.item_id AND ist2.avg_price > 0
              )
        ");

        $this->line('  ✔ Tahap 2 (avg_price warehouse lain): selesai');

        // Fallback akhir: dari price master item
        $affected3 = DB::statement("
            UPDATE bon_pengeluaran_items bpi
            SET harga_satuan = (
                SELECT i.price FROM items i WHERE i.id = bpi.item_id AND i.price > 0 LIMIT 1
            )
            WHERE bpi.item_id IS NOT NULL
              AND (bpi.harga_satuan IS NULL OR bpi.harga_satuan = 0)
              AND EXISTS (
                SELECT 1 FROM items i2 WHERE i2.id = bpi.item_id AND i2.price > 0
              )
        ");

        $this->line('  ✔ Tahap 3 (price master barang): selesai');

        // Hitung sisa yang masih 0
        $stillZero = DB::table('bon_pengeluaran_items')
            ->whereNotNull('item_id')
            ->where(function ($q) { $q->whereNull('harga_satuan')->orWhere('harga_satuan', 0); })
            ->count();

        $this->info("Backfill selesai. Item yang masih harga 0: {$stillZero}");

        return self::SUCCESS;
    }
}
