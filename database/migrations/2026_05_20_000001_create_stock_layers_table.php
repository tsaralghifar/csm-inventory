<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_layers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')
                  ->constrained('items')
                  ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                  ->constrained('warehouses')
                  ->cascadeOnDelete();

            // Qty awal saat layer dibuat (tidak berubah, untuk referensi)
            $table->decimal('qty_awal', 12, 2)->default(0);

            // Qty sisa yang belum dikeluarkan (berkurang saat ada pengeluaran)
            $table->decimal('qty_sisa', 12, 2)->default(0);

            // Harga beli per satuan pada batch ini
            $table->decimal('harga_satuan', 15, 2)->default(0);

            // Tanggal barang masuk (tanggal penerimaan, bukan tanggal input)
            $table->date('tanggal_masuk');

            // Asal layer: 'po' | 'import' | 'transfer' | 'opname'
            $table->string('source_type', 30)->default('po');

            // Nomor referensi (no. PO, no. bon transfer, dll.)
            $table->string('reference_no', 100)->nullable();

            // Untuk transfer: melacak layer asal di gudang pengirim
            $table->foreignId('parent_layer_id')
                  ->nullable()
                  ->constrained('stock_layers')
                  ->nullOnDelete();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            // Index untuk query FIFO (ambil layer terlama per item per gudang)
            $table->index(['item_id', 'warehouse_id', 'tanggal_masuk', 'id'],
                          'stock_layers_fifo_idx');

            // Index untuk cari layer yang masih punya sisa
            $table->index(['item_id', 'warehouse_id', 'qty_sisa'],
                          'stock_layers_available_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_layers');
    }
};
