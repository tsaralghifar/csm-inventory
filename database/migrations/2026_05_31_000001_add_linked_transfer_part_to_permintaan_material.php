<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_material', function (Blueprint $table) {
            // Link ke MR Transfer Part yang menjadi alasan PM ini dibuat
            $table->foreignId('linked_transfer_part_id')
                ->nullable()
                ->after('notes')
                ->constrained('material_requests')
                ->nullOnDelete()
                ->comment('Transfer Part Darurat yang menjadi alasan PM pengganti ini');
        });

        // Update MaterialRequest: tambah kolom linked_pm_id (relasi balik)
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignId('linked_pm_id')
                ->nullable()
                ->after('linked_po_id')
                ->constrained('permintaan_material')
                ->nullOnDelete()
                ->comment('PM pengganti yang dibuat untuk mengganti part yang ditransfer');
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropForeign(['linked_pm_id']);
            $table->dropColumn('linked_pm_id');
        });

        Schema::table('permintaan_material', function (Blueprint $table) {
            $table->dropForeign(['linked_transfer_part_id']);
            $table->dropColumn('linked_transfer_part_id');
        });
    }
};
