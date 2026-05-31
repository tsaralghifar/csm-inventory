<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel document_signers — snapshot penandatangan saat dokumen diterbitkan.
 *
 * Tujuan: menyimpan siapa yang menandatangani dokumen + gambar TTD pada
 * saat dokumen diterbitkan, sehingga data tidak berubah meskipun:
 *   - User diganti namanya / jabatannya
 *   - User mengganti TTD-nya
 *   - User dinonaktifkan / dihapus
 *
 * Polimorfik: satu tabel untuk semua jenis dokumen.
 *   document_type  = nama model, misal: 'permintaan_material', 'purchase_order', 'report_stock'
 *   document_id    = ID record di tabel asal (0 atau hash untuk laporan non-record)
 *
 * Kolom snapshot (disalin saat dokumen diterbitkan, tidak berubah setelahnya):
 *   signer_user_id      = FK ke users (nullable agar tetap ada jika user dihapus)
 *   signer_name         = nama saat itu
 *   signer_position     = jabatan saat itu
 *   signer_role         = role saat itu
 *   signer_label        = 'Dibuat oleh' / 'Diperiksa oleh' / 'Disetujui oleh'
 *   signer_order        = urutan (1, 2, 3)
 *   signature_snapshot  = path file TTD saat itu (disalin ke signatures/snapshot/{id}.png)
 *   signed_at           = waktu snapshot diambil
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signers', function (Blueprint $table) {
            $table->id();

            // Polimorfik — dokumen apa + ID berapa
            $table->string('document_type', 100);   // 'report_stock', 'permintaan_material', dst
            $table->string('document_id', 100);      // string agar bisa simpan hash untuk laporan

            // Urutan & label penandatangan
            $table->unsignedTinyInteger('signer_order');  // 1, 2, atau 3
            $table->string('signer_label', 50);           // 'Dibuat oleh', dll

            // Snapshot identitas — data dikopi saat dokumen diterbitkan
            $table->foreignId('signer_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();       // tetap ada rekamannya walau user dihapus
            $table->string('signer_name', 255);
            $table->string('signer_position', 255)->nullable();
            $table->string('signer_role', 100)->nullable();

            // Snapshot file TTD — path di Storage::disk('local')
            // File dikopi dari signatures/{userId}.png → signatures/snapshots/{document_type}/{document_id}_{order}.png
            $table->string('signature_snapshot_path')->nullable();

            $table->timestamp('signed_at');
            $table->timestamps();

            // Index untuk lookup cepat per dokumen
            $table->index(['document_type', 'document_id'], 'idx_document_signers_doc');
            $table->unique(['document_type', 'document_id', 'signer_order'], 'uniq_document_signer_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signers');
    }
};
