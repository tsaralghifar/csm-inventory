<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // purchase_orders: material_request_id jadi nullable, tambah permintaan_material_id
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Jadikan material_request_id nullable (karena PO bisa dari PM, bukan MR)
            $table->foreignId('permintaan_material_id')
                ->nullable()
                ->after('material_request_id')
                ->constrained('permintaan_material')
                ->nullOnDelete();
        });

        // Jadikan material_request_id nullable di purchase_orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('material_request_id')->nullable()->change();
        });

        // bon_pengeluaran: tambah permintaan_material_id
        Schema::table('bon_pengeluaran', function (Blueprint $table) {
            $table->foreignId('permintaan_material_id')
                ->nullable()
                ->after('material_request_id')
                ->constrained('permintaan_material')
                ->nullOnDelete();
        });

        // surat_jalan: tambah permintaan_material_id
        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->foreignId('permintaan_material_id')
                ->nullable()
                ->after('material_request_id')
                ->constrained('permintaan_material')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['permintaan_material_id']);
            $table->dropColumn('permintaan_material_id');
        });

        Schema::table('bon_pengeluaran', function (Blueprint $table) {
            $table->dropForeign(['permintaan_material_id']);
            $table->dropColumn('permintaan_material_id');
        });

        Schema::table('surat_jalan', function (Blueprint $table) {
            $table->dropForeign(['permintaan_material_id']);
            $table->dropColumn('permintaan_material_id');
        });
    }
};
