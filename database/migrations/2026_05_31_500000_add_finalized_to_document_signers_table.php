<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom finalisasi ke tabel document_signers.
 *
 * is_finalized    = true  → dokumen terkunci permanen, tidak bisa ditambah/diubah slot
 * finalized_by    = user yang mengklik tombol Finalisasi
 * finalized_at    = waktu finalisasi
 *
 * Selama is_finalized = false, slot TTD bisa ditambahkan oleh user lain.
 * Setelah is_finalized = true, semua perubahan ditolak di backend.
 *
 * Kolom disimpan per-dokumen (satu baris per slot), tapi is_finalized berlaku
 * untuk seluruh dokumen — semua baris dengan document_type+document_id yang sama
 * memiliki nilai is_finalized yang sama. Update dilakukan secara bulk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->boolean('is_finalized')->default(false)->after('signed_at');
            $table->foreignId('finalized_by')
                  ->nullable()
                  ->after('is_finalized')
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['is_finalized', 'finalized_by', 'finalized_at']);
        });
    }
};
