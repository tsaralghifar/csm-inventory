<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->decimal('avg_price', 15, 2)->default(0)->after('qty_reserved');
        });

        // Isi avg_price awal dari harga master barang
        DB::statement('UPDATE item_stocks SET avg_price = items.price FROM items WHERE item_stocks.item_id = items.id');
    }

    public function down(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropColumn('avg_price');
        });
    }
};
