<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah field untuk alur konfirmasi mekanik sebelum barang dikeluarkan.
 *
 * Status baru yang ditambahkan:
 *   draft               → bon dibuat, belum diproses admin
 *   pending_confirmation→ admin sudah siapkan barang, menunggu konfirmasi mekanik
 *   confirmed           → mekanik konfirmasi cocok, siap diissue admin
 *   rejected_by_mechanic→ mekanik menolak (barang tidak sesuai)
 *   issued              → sudah dikeluarkan, stok berkurang (status existing)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bon_pengeluaran', function (Blueprint $table) {
            // Siapa yang mengkonfirmasi / menolak (bisa user_id atau nama string)
            $table->string('confirmed_by')->nullable()->after('mechanic');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');

            // Alasan penolakan dari mekanik
            $table->text('rejection_reason')->nullable()->after('confirmed_at');

            // Token unik untuk link konfirmasi (opsional, jika konfirmasi via link)
            $table->string('confirmation_token', 64)->nullable()->unique()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bon_pengeluaran', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_by',
                'confirmed_at',
                'rejection_reason',
                'confirmation_token',
            ]);
        });
    }
};
