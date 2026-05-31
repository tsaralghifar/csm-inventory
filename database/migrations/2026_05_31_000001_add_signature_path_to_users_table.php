<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Migrasikan tanda tangan dari kolom base64 DB
 *            ke file path di Storage::disk('local') private.
 *
 * Kolom lama : signature  (text, berisi base64 PNG)
 * Kolom baru : signature_path  (string, berisi path relatif di local storage)
 *
 * Data lama dimigrasikan oleh Artisan command:
 *   php artisan signature:migrate-to-storage
 *
 * Jalankan SETELAH migration ini agar kolom lama masih ada saat command berjalan.
 * Setelah command selesai, kolom lama sudah tidak diperlukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tambah kolom baru untuk path file
            $table->string('signature_path')->nullable()->after('signature');
        });

        // Catatan: kolom 'signature' lama TIDAK langsung dihapus di sini.
        // Hapus dengan migration terpisah SETELAH menjalankan:
        //   php artisan signature:migrate-to-storage
        //
        // Ini memastikan data TTD lama tidak hilang jika command belum dijalankan.
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};
