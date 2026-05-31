<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Hapus kolom 'signature' (base64) yang sudah digantikan
 *            oleh 'signature_path' (file storage).
 *
 * Migration ini memiliki guard keamanan: akan GAGAL (exception) jika masih
 * ada user yang punya signature lama tapi belum dimigrasikan ke storage.
 * Jalankan command ini dulu:
 *   php artisan signature:migrate-to-storage
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard: pastikan tidak ada data yang belum dimigrasikan
        if (Schema::hasColumn('users', 'signature')) {
            $unmigrated = DB::table('users')
                ->whereNotNull('signature')
                ->whereNull('signature_path')
                ->whereNull('deleted_at')
                ->count();

            if ($unmigrated > 0) {
                throw new \RuntimeException(
                    "DITOLAK: Masih ada {$unmigrated} user dengan TTD lama yang belum dimigrasikan.\n" .
                    "Jalankan dulu: php artisan signature:migrate-to-storage"
                );
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('signature');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('signature')->nullable()->after('position');
        });
    }
};
