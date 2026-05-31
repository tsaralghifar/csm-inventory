<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom Part Transfer Darurat ke material_requests
        Schema::table('material_requests', function (Blueprint $table) {
            // Unit asal (unit/alat berat yang dilepas partnya)
            $table->string('unit_from_kode', 100)->nullable()->after('to_warehouse_id')
                ->comment('Kode unit asal yang partnya dilepas');
            $table->string('unit_from_tipe', 100)->nullable()->after('unit_from_kode')
                ->comment('Tipe unit asal');

            // Unit tujuan (unit/alat berat yang menerima part)
            $table->string('unit_to_kode', 100)->nullable()->after('unit_from_tipe')
                ->comment('Kode unit tujuan yang menerima part');
            $table->string('unit_to_tipe', 100)->nullable()->after('unit_to_kode')
                ->comment('Tipe unit tujuan');

            // Alasan urgent & link ke PO pengganti
            $table->text('alasan_urgent')->nullable()->after('unit_to_tipe')
                ->comment('Alasan pengambilan part urgent');
            $table->foreignId('linked_po_id')->nullable()
                ->after('alasan_urgent')
                ->constrained('purchase_orders')
                ->nullOnDelete()
                ->comment('PO pengganti part yang dilepas');
        });

        // 2. Tambah type 'transfer_part' ke constraint material_requests
        DB::statement("ALTER TABLE material_requests DROP CONSTRAINT IF EXISTS material_requests_type_check");
        DB::statement("ALTER TABLE material_requests ADD CONSTRAINT material_requests_type_check
            CHECK (type IN ('part', 'office', 'transfer', 'transfer_part'))");

        // 3. Tambah status baru untuk alur transfer_part
        DB::statement("ALTER TABLE material_requests DROP CONSTRAINT IF EXISTS material_requests_status_check");
        DB::statement("ALTER TABLE material_requests ADD CONSTRAINT material_requests_status_check
            CHECK (status IN (
                'draft', 'submitted',
                'pending_admin', 'pending_atasan',
                'pending_chief', 'pending_manager', 'pending_ho',
                'approved', 'manager_approved',
                'dispatched', 'received',
                'rejected', 'cancelled'
            ))");

        // 4. Tambah kolom linked_mr_transfer_id di purchase_orders
        //    agar PO pengganti bisa di-link ke MR Transfer Part
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('linked_mr_transfer_id')->nullable()
                ->after('permintaan_material_id')
                ->constrained('material_requests')
                ->nullOnDelete()
                ->comment('MR Transfer Part yang menjadi alasan pembelian ini');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['linked_mr_transfer_id']);
            $table->dropColumn('linked_mr_transfer_id');
        });

        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['linked_po_id']);
            $table->dropColumn([
                'unit_from_kode', 'unit_from_tipe',
                'unit_to_kode',   'unit_to_tipe',
                'alasan_urgent',  'linked_po_id',
            ]);
        });

        DB::statement("ALTER TABLE material_requests DROP CONSTRAINT IF EXISTS material_requests_type_check");
        DB::statement("ALTER TABLE material_requests ADD CONSTRAINT material_requests_type_check
            CHECK (type IN ('part', 'office', 'transfer'))");

        DB::statement("ALTER TABLE material_requests DROP CONSTRAINT IF EXISTS material_requests_status_check");
        DB::statement("ALTER TABLE material_requests ADD CONSTRAINT material_requests_status_check
            CHECK (status IN (
                'draft', 'submitted',
                'pending_admin', 'pending_atasan',
                'pending_chief', 'pending_manager', 'pending_ho',
                'approved', 'manager_approved',
                'dispatched', 'received',
                'rejected', 'cancelled'
            ))");
    }
};
