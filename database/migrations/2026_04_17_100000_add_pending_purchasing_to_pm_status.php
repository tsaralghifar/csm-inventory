<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom po_submitted_at & po_submitted_by di permintaan_material
        Schema::table('permintaan_material', function (Blueprint $table) {
            $table->unsignedBigInteger('po_submitted_by')->nullable()->after('ho_approved_at');
            $table->timestamp('po_submitted_at')->nullable()->after('po_submitted_by');

            $table->foreign('po_submitted_by')->references('id')->on('users')->nullOnDelete();
        });

        // 2. Update constraint CHECK status — tambahkan 'pending_purchasing'
        DB::statement("ALTER TABLE permintaan_material DROP CONSTRAINT IF EXISTS permintaan_material_status_check");

        DB::statement("
            ALTER TABLE permintaan_material
            ADD CONSTRAINT permintaan_material_status_check
            CHECK (status IN (
                'draft',
                'pending_chief',
                'pending_manager',
                'pending_ho',
                'manager_approved',
                'approved',
                'pending_purchasing',
                'rejected',
                'purchasing',
                'partial_ordered',
                'bon_pengeluaran',
                'completed',
                'pending_site'
            ))
        ");
    }

    public function down(): void
    {
        Schema::table('permintaan_material', function (Blueprint $table) {
            $table->dropForeign(['po_submitted_by']);
            $table->dropColumn(['po_submitted_by', 'po_submitted_at']);
        });

        DB::statement("ALTER TABLE permintaan_material DROP CONSTRAINT IF EXISTS permintaan_material_status_check");

        DB::statement("
            ALTER TABLE permintaan_material
            ADD CONSTRAINT permintaan_material_status_check
            CHECK (status IN (
                'draft',
                'pending_chief',
                'pending_manager',
                'pending_ho',
                'manager_approved',
                'approved',
                'rejected',
                'purchasing',
                'partial_ordered',
                'bon_pengeluaran',
                'completed',
                'pending_site'
            ))
        ");
    }
};
