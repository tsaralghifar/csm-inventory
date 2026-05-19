<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bon_pengeluaran_items', function (Blueprint $table) {
            $table->decimal('harga_satuan', 15, 2)->default(0)->after('keterangan');
        });

        // Isi harga_satuan dari avg_price item_stocks (snapshot harga saat itu)
        // Gunakan harga dari item_stocks yang sesuai dengan warehouse bon pengeluaran
        DB::statement("
            UPDATE bon_pengeluaran_items bpi
            SET harga_satuan = COALESCE(
                (
                    SELECT ist.avg_price
                    FROM item_stocks ist
                    INNER JOIN bon_pengeluaran bp ON bp.id = bpi.bon_pengeluaran_id
                    WHERE ist.item_id = bpi.item_id
                      AND ist.warehouse_id = bp.warehouse_id
                    LIMIT 1
                ),
                (
                    SELECT price FROM items WHERE id = bpi.item_id LIMIT 1
                ),
                0
            )
            WHERE bpi.item_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('bon_pengeluaran_items', function (Blueprint $table) {
            $table->dropColumn('harga_satuan');
        });
    }
};
