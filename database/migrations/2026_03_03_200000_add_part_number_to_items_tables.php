<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah part_number ke permintaan_material_items
        Schema::table('permintaan_material_items', function (Blueprint $table) {
            $table->string('part_number', 100)->nullable()->after('nama_barang');
        });

        // Tambah part_number ke purchase_order_items (jika belum ada)
        if (!Schema::hasColumn('purchase_order_items', 'part_number')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->string('part_number', 100)->nullable()->after('nama_barang');
            });
        }
    }

    public function down(): void
    {
        Schema::table('permintaan_material_items', function (Blueprint $table) {
            $table->dropColumn('part_number');
        });

        if (Schema::hasColumn('purchase_order_items', 'part_number')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('part_number');
            });
        }
    }
};
