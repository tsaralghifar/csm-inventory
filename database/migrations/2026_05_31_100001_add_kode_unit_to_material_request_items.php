<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->string('kode_unit')->nullable()->after('notes');
            $table->string('tipe_unit')->nullable()->after('kode_unit');
        });

        // Backfill dari header Transfer Part untuk item yang sudah ada
        DB::statement("
            UPDATE material_request_items
            SET
                kode_unit = mr.unit_from_kode,
                tipe_unit = mr.unit_from_tipe
            FROM material_requests mr
            WHERE mr.id = material_request_items.material_request_id
              AND mr.type = 'transfer_part'
              AND (material_request_items.kode_unit IS NULL OR material_request_items.kode_unit = '')
        ");
    }

    public function down(): void
    {
        Schema::table('material_request_items', function (Blueprint $table) {
            $table->dropColumn(['kode_unit', 'tipe_unit']);
        });
    }
};
