<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── bon_pengeluaran ──────────────────────────────────────────────────
        Schema::table('bon_pengeluaran', function (Blueprint $table) {
            $table->index('status',       'idx_bon_status');
            $table->index('created_at',   'idx_bon_created_at');
            $table->index('issue_date',   'idx_bon_issue_date');
            $table->index('warehouse_id', 'idx_bon_warehouse_id');
            $table->index('created_by',   'idx_bon_created_by');
        });

        // ── bon_pengeluaran_items ────────────────────────────────────────────
        Schema::table('bon_pengeluaran_items', function (Blueprint $table) {
            $table->index('bon_pengeluaran_id', 'idx_bon_items_bon_id');
            $table->index('item_id',            'idx_bon_items_item_id');
        });

        // ── item_stocks ──────────────────────────────────────────────────────
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->index(['warehouse_id', 'item_id'], 'idx_stocks_warehouse_item');
            $table->index('qty',                       'idx_stocks_qty');
        });

        // ── items ────────────────────────────────────────────────────────────
        Schema::table('items', function (Blueprint $table) {
            $table->index('is_active',   'idx_items_is_active');
            $table->index('category_id', 'idx_items_category_id');
            $table->index('name',        'idx_items_name');
        });

        // GIN trigram index untuk pencarian ILIKE yang cepat
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_items_name_trgm ON items USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_items_part_number_trgm ON items USING GIN (part_number gin_trgm_ops)');

        // ── permintaan_material ──────────────────────────────────────────────
        Schema::table('permintaan_material', function (Blueprint $table) {
            $table->index('status',       'idx_pm_status');
            $table->index('warehouse_id', 'idx_pm_warehouse_id');
            $table->index('created_at',   'idx_pm_created_at');
        });

        // ── permintaan_material_items ────────────────────────────────────────
        Schema::table('permintaan_material_items', function (Blueprint $table) {
            $table->index('permintaan_material_id', 'idx_pm_items_pm_id');
            $table->index('item_id',                'idx_pm_items_item_id');
        });

        // ── purchase_orders ──────────────────────────────────────────────────
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('status',     'idx_po_status');
            $table->index('created_at', 'idx_po_created_at');
        });

        // ── stock_movements ──────────────────────────────────────────────────
        // Kolom yang benar: from_warehouse_id, to_warehouse_id, item_id, movement_date
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('from_warehouse_id', 'idx_movements_from_warehouse');
            $table->index('to_warehouse_id',   'idx_movements_to_warehouse');
            $table->index('item_id',           'idx_movements_item');
            $table->index('movement_date',     'idx_movements_date');
            $table->index('type',              'idx_movements_type');
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_items_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_items_part_number_trgm');

        Schema::table('bon_pengeluaran', function (Blueprint $table) {
            $table->dropIndex('idx_bon_status');
            $table->dropIndex('idx_bon_created_at');
            $table->dropIndex('idx_bon_issue_date');
            $table->dropIndex('idx_bon_warehouse_id');
            $table->dropIndex('idx_bon_created_by');
        });

        Schema::table('bon_pengeluaran_items', function (Blueprint $table) {
            $table->dropIndex('idx_bon_items_bon_id');
            $table->dropIndex('idx_bon_items_item_id');
        });

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropIndex('idx_stocks_warehouse_item');
            $table->dropIndex('idx_stocks_qty');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('idx_items_is_active');
            $table->dropIndex('idx_items_category_id');
            $table->dropIndex('idx_items_name');
        });

        Schema::table('permintaan_material', function (Blueprint $table) {
            $table->dropIndex('idx_pm_status');
            $table->dropIndex('idx_pm_warehouse_id');
            $table->dropIndex('idx_pm_created_at');
        });

        Schema::table('permintaan_material_items', function (Blueprint $table) {
            $table->dropIndex('idx_pm_items_pm_id');
            $table->dropIndex('idx_pm_items_item_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_status');
            $table->dropIndex('idx_po_created_at');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_movements_from_warehouse');
            $table->dropIndex('idx_movements_to_warehouse');
            $table->dropIndex('idx_movements_item');
            $table->dropIndex('idx_movements_date');
            $table->dropIndex('idx_movements_type');
        });
    }
};