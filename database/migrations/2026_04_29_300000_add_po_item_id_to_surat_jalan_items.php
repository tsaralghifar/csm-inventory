<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Tambah kolom purchase_order_item_id ke surat_jalan_items
 * untuk tracking yang lebih akurat per item PO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_jalan_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')
                  ->nullable()
                  ->after('surat_jalan_id')
                  ->constrained('purchase_order_items')
                  ->nullOnDelete();
        });

        // Backfill: isi purchase_order_item_id untuk data lama via item_id + purchase_order_id
        DB::statement("
            UPDATE surat_jalan_items
            SET purchase_order_item_id = (
                SELECT poi.id
                FROM purchase_order_items poi
                JOIN surat_jalan sj ON sj.id = surat_jalan_items.surat_jalan_id
                WHERE poi.item_id = surat_jalan_items.item_id
                  AND poi.purchase_order_id = sj.purchase_order_id
                LIMIT 1
            )
            WHERE purchase_order_item_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('surat_jalan_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_item_id']);
            $table->dropColumn('purchase_order_item_id');
        });
    }
};