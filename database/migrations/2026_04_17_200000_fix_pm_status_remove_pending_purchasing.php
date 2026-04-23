<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update data: PM yang masih di status pending_purchasing → approved
        DB::statement("UPDATE permintaan_material SET status = 'approved' WHERE status = 'pending_purchasing'");

        // Update constraint: hapus pending_purchasing dari daftar status yang diizinkan
        DB::statement("ALTER TABLE permintaan_material DROP CONSTRAINT IF EXISTS permintaan_material_status_check");

        DB::statement("
            ALTER TABLE permintaan_material
            ADD CONSTRAINT permintaan_material_status_check
            CHECK (status IN (
                'draft',
                'pending_chief',
                'pending_manager',
                'pending_ho',
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

    public function down(): void
    {
        DB::statement("ALTER TABLE permintaan_material DROP CONSTRAINT IF EXISTS permintaan_material_status_check");

        DB::statement("
            ALTER TABLE permintaan_material
            ADD CONSTRAINT permintaan_material_status_check
            CHECK (status IN (
                'draft',
                'pending_chief',
                'pending_manager',
                'pending_ho',
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
};
