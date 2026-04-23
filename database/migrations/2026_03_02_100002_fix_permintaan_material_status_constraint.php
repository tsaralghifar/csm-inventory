<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus constraint lama (dari migration sebelumnya maupun dari enum awal)
        DB::statement("ALTER TABLE permintaan_material DROP CONSTRAINT IF EXISTS permintaan_material_status_check");

        // Hapus enum type jika PostgreSQL menggunakan enum type terpisah
        // (Laravel enum di PostgreSQL pakai varchar + check constraint, bukan ENUM type)
        // Pastikan kolom status adalah varchar agar bisa menerima semua nilai
        DB::statement("ALTER TABLE permintaan_material ALTER COLUMN status TYPE VARCHAR(50)");

        // Tambah constraint baru yang lengkap dengan semua status yang dipakai
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
                'completed',
                'pending_site'
            ))
        ");
    }
};
