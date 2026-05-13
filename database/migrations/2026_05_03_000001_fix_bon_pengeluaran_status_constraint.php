<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix check constraint pada kolom status di tabel bon_pengeluaran.
 *
 * Status lama: draft, approved, issued
 * Status baru: draft, pending_confirmation, confirmed, rejected_by_mechanic, issued
 *
 * Catatan: PostgreSQL tidak support ALTER COLUMN untuk enum,
 * sehingga kita ubah ke tipe VARCHAR + CHECK constraint secara manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Hapus constraint lama
        DB::statement('ALTER TABLE bon_pengeluaran DROP CONSTRAINT IF EXISTS bon_pengeluaran_status_check');

        // 2. Ubah tipe kolom dari enum ke varchar (agar bisa diubah fleksibel)
        DB::statement("ALTER TABLE bon_pengeluaran ALTER COLUMN status TYPE VARCHAR(50)");

        // 3. Tambahkan check constraint baru yang mencakup semua status
        DB::statement("
            ALTER TABLE bon_pengeluaran
            ADD CONSTRAINT bon_pengeluaran_status_check
            CHECK (status IN (
                'draft',
                'pending_confirmation',
                'confirmed',
                'rejected_by_mechanic',
                'issued'
            ))
        ");

        // 4. Migrasi data lama: status 'approved' → 'confirmed' (jika ada)
        DB::statement("UPDATE bon_pengeluaran SET status = 'confirmed' WHERE status = 'approved'");
    }

    public function down(): void
    {
        // Kembalikan ke constraint lama
        DB::statement('ALTER TABLE bon_pengeluaran DROP CONSTRAINT IF EXISTS bon_pengeluaran_status_check');

        DB::statement("UPDATE bon_pengeluaran SET status = 'draft' WHERE status IN ('pending_confirmation', 'rejected_by_mechanic')");
        DB::statement("UPDATE bon_pengeluaran SET status = 'approved' WHERE status = 'confirmed'");

        DB::statement("ALTER TABLE bon_pengeluaran ALTER COLUMN status TYPE VARCHAR(50)");

        DB::statement("
            ALTER TABLE bon_pengeluaran
            ADD CONSTRAINT bon_pengeluaran_status_check
            CHECK (status IN ('draft', 'approved', 'issued'))
        ");
    }
};
