<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bon_pengeluaran_item_layers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bon_pengeluaran_item_id')
                  ->constrained('bon_pengeluaran_items')
                  ->cascadeOnDelete();

            $table->foreignId('stock_layer_id')
                  ->nullable()
                  ->constrained('stock_layers')
                  ->nullOnDelete();

            // Qty yang diambil dari layer ini
            $table->decimal('qty', 12, 2)->default(0);

            // Harga satuan dari layer ini (snapshot)
            $table->decimal('harga_satuan', 15, 2)->default(0);

            // Nilai = qty × harga_satuan
            $table->decimal('nilai', 15, 2)->default(0);

            // Info layer untuk referensi (denormalized agar tidak hilang jika layer dihapus)
            $table->date('tanggal_masuk')->nullable();
            $table->string('source_type', 30)->nullable();
            $table->string('reference_no', 100)->nullable();

            $table->timestamps();

            $table->index('bon_pengeluaran_item_id', 'bpil_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bon_pengeluaran_item_layers');
    }
};
