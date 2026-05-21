<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Harga FIFO aktual saat pengeluaran — bisa berbeda dengan avg_price
            $table->decimal('fifo_price', 15, 2)->default(0)->after('price')
                  ->comment('Harga FIFO aktual per satuan saat barang dikeluarkan');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('fifo_price');
        });
    }
};
