<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan index berdasarkan hasil audit performa.
 *
 * Mencakup:
 *  - purchase_orders  : supplier_id, created_by
 *  - audit_logs       : user_id, created_at, subject_type+subject_id
 *  - stock_movements  : composite (from_warehouse_id, movement_date)
 *  - material_requests: status, created_at
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── purchase_orders ──────────────────────────────────────────────────
        // Sering difilter by supplier dan by creator
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('supplier_id', 'idx_po_supplier_id');
            $table->index('created_by',  'idx_po_created_by');
        });

        // ── audit_logs ───────────────────────────────────────────────────────
        // Sering difilter by user dan diurutkan by tanggal
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index('user_id',    'idx_audit_user_id');
                $table->index('created_at', 'idx_audit_created_at');

                // Composite untuk query "semua log untuk model X dengan ID Y"
                if (Schema::hasColumn('audit_logs', 'subject_type') &&
                    Schema::hasColumn('audit_logs', 'subject_id')) {
                    $table->index(['subject_type', 'subject_id'], 'idx_audit_subject');
                }
            });
        }

        // ── stock_movements ──────────────────────────────────────────────────
        // Composite index untuk query laporan yang filter warehouse + tanggal sekaligus
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_movements_from_wh_date
            ON stock_movements (from_warehouse_id, movement_date DESC)
        ');
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_movements_to_wh_date
            ON stock_movements (to_warehouse_id, movement_date DESC)
        ');

        // ── material_requests ────────────────────────────────────────────────
        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                $table->index('status',     'idx_mr_status');
                $table->index('created_at', 'idx_mr_created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_po_supplier_id');
            $table->dropIndex('idx_po_created_by');
        });

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex('idx_audit_user_id');
                $table->dropIndex('idx_audit_created_at');

                if (Schema::hasColumn('audit_logs', 'subject_type')) {
                    $table->dropIndex('idx_audit_subject');
                }
            });
        }

        DB::statement('DROP INDEX IF EXISTS idx_movements_from_wh_date');
        DB::statement('DROP INDEX IF EXISTS idx_movements_to_wh_date');

        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                $table->dropIndex('idx_mr_status');
                $table->dropIndex('idx_mr_created_at');
            });
        }
    }
};
