<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus constraint lama
        DB::statement("ALTER TABLE permintaan_material DROP CONSTRAINT IF EXISTS permintaan_material_status_check");

        // Tambah constraint baru dengan 'partial_ordered' + 'manager_approved'
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
                'rejected',
                'purchasing',
                'bon_pengeluaran',
                'completed',
                'pending_site'
            ))
        ");
    }
};
