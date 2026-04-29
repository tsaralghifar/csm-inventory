<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Partial Delivery Support
 * ─────────────────────────────────────────────────────────────────────────────
 * Menambahkan kolom tracking pengiriman sebagian pada purchase_order_items:
 *   - qty_received  : total qty yang sudah diterima dari semua pengiriman
 *   - qty_remaining : sisa qty yang belum diterima (generated column / computed)
 *
 * Dan kolom di purchase_orders:
 *   - delivery_status : null | partial | completed
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── purchase_order_items ──────────────────────────────────────────────
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Total qty yang sudah diterima dari semua Surat Jalan
            $table->decimal('qty_received', 12, 2)->default(0)->after('qty');
        });

        // ── purchase_orders ───────────────────────────────────────────────────
        Schema::table('purchase_orders', function (Blueprint $table) {
            // null = belum ada penerimaan, partial = sebagian, completed = semua diterima
            $table->string('delivery_status')->nullable()->after('status');
        });

        // Backfill qty_received dari surat_jalan_items yang sudah ada
        DB::statement("
            UPDATE purchase_order_items poi
            SET qty_received = COALESCE((
                SELECT SUM(sji.qty_received)
                FROM surat_jalan_items sji
                JOIN surat_jalan sj ON sj.id = sji.surat_jalan_id
                WHERE sji.item_id = poi.item_id
                  AND sj.purchase_order_id = poi.purchase_order_id
                  AND sj.deleted_at IS NULL
            ), 0)
        ");

        // Backfill delivery_status di purchase_orders berdasarkan data TTB yang sudah ada
        DB::statement("
            UPDATE purchase_orders po
            SET delivery_status = CASE
                WHEN NOT EXISTS (
                    SELECT 1 FROM purchase_order_items WHERE purchase_order_id = po.id AND qty_received > 0
                ) THEN NULL
                WHEN EXISTS (
                    SELECT 1 FROM purchase_order_items
                    WHERE purchase_order_id = po.id AND qty_received < qty
                ) THEN 'partial'
                ELSE 'completed'
            END
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('qty_received');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });
    }
};
