<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->decimal('purchase_price', 15, 2);     // Harga beli aktual
            $table->decimal('avg_price_before', 15, 2);   // Harga rata-rata sebelum
            $table->decimal('avg_price_after', 15, 2);    // Harga rata-rata sesudah
            $table->decimal('qty_received', 12, 2);       // Qty yang diterima
            $table->string('reference_no', 100)->nullable(); // No. SJ / IN-xxx
            $table->string('source_type', 50)->nullable();   // 'surat_jalan' / 'stock_in'
            $table->foreignId('created_by')->constrained('users');
            $table->date('transaction_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_price_history');
    }
};
