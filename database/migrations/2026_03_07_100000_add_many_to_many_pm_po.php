<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel pivot many-to-many PO ↔ PM
        Schema::create('purchase_order_permintaan_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('permintaan_material_id')
                ->constrained('permintaan_material')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['purchase_order_id', 'permintaan_material_id'], 'po_pm_unique');
        });

        // 2. Tambah kolom di purchase_order_items untuk track PM item asal
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('permintaan_material_item_id')
                ->nullable()
                ->after('item_id')
                ->constrained('permintaan_material_items')
                ->nullOnDelete();
            $table->decimal('qty_pm', 10, 2)->nullable()->after('permintaan_material_item_id')
                ->comment('Qty asli di PM, untuk referensi partial order');
        });

        // 3. Tambah status partial_ordered di permintaan_material
        // Migrasi data lama: isi pivot dari kolom permintaan_material_id lama yang sudah ada
        $pos = DB::table('purchase_orders')
            ->whereNotNull('permintaan_material_id')
            ->get(['id', 'permintaan_material_id']);

        foreach ($pos as $po) {
            DB::table('purchase_order_permintaan_material')->insertOrIgnore([
                'purchase_order_id'        => $po->id,
                'permintaan_material_id'   => $po->permintaan_material_id,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['permintaan_material_item_id']);
            $table->dropColumn(['permintaan_material_item_id', 'qty_pm']);
        });

        Schema::dropIfExists('purchase_order_permintaan_material');
    }
};
