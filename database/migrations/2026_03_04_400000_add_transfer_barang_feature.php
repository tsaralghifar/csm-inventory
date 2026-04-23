<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah type 'transfer' ke material_requests
        // PostgreSQL enum tidak bisa di-ALTER langsung, gunakan raw SQL
        DB::statement("ALTER TABLE material_requests DROP CONSTRAINT IF EXISTS material_requests_type_check");
        DB::statement("ALTER TABLE material_requests ALTER COLUMN type TYPE VARCHAR(20)");
        DB::statement("ALTER TABLE material_requests ADD CONSTRAINT material_requests_type_check CHECK (type IN ('part', 'office', 'transfer'))");

        // 2. Tambah kolom atasan approval ke material_requests (untuk alur transfer)
        Schema::table('material_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('material_requests', 'atasan_approved_by')) {
                $table->foreignId('atasan_approved_by')->nullable()->constrained('users')->after('manager_approved_at');
            }
            if (!Schema::hasColumn('material_requests', 'atasan_approved_at')) {
                $table->timestamp('atasan_approved_at')->nullable()->after('atasan_approved_by');
            }
        });

        // 3. Update status constraint material_requests — tambah status untuk transfer
        DB::statement("
            ALTER TABLE material_requests
            DROP CONSTRAINT IF EXISTS material_requests_status_check
        ");
        DB::statement("ALTER TABLE material_requests ALTER COLUMN status TYPE VARCHAR(30)");
        DB::statement("
            ALTER TABLE material_requests
            ADD CONSTRAINT material_requests_status_check
            CHECK (status IN (
                'draft',
                'submitted',
                'pending_admin',
                'pending_atasan',
                'approved',
                'dispatched',
                'received',
                'rejected',
                'cancelled',
                'pending_chief',
                'pending_manager',
                'pending_ho',
                'manager_approved'
            ))
        ");

        // 4. Tambah received_by_name ke delivery_orders
        Schema::table('delivery_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_orders', 'received_by_name')) {
                $table->string('received_by_name')->nullable()->after('received_by');
            }
        });

        // 5. Tambah nama_barang ke delivery_order_items (untuk item tanpa item_id)
        Schema::table('delivery_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_order_items', 'satuan')) {
                $table->string('satuan', 50)->nullable()->after('qty_received');
            }
            if (!Schema::hasColumn('delivery_order_items', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('satuan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['atasan_approved_by']);
            $table->dropColumn(['atasan_approved_by', 'atasan_approved_at']);
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('received_by_name');
        });

        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropColumn(['satuan', 'keterangan']);
        });

        DB::statement("ALTER TABLE material_requests DROP CONSTRAINT IF EXISTS material_requests_type_check");
        DB::statement("ALTER TABLE material_requests ADD CONSTRAINT material_requests_type_check CHECK (type IN ('part', 'office'))");

        DB::statement("ALTER TABLE material_requests DROP CONSTRAINT IF EXISTS material_requests_status_check");
    }
};
