<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('part_number', 100)->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('categories');
            $table->string('brand')->nullable();
            $table->string('unit', 20)->default('PCS');
            $table->decimal('min_stock', 12, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->string('location_code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('qty', 12, 2)->default(0);
            $table->decimal('qty_reserved', 12, 2)->default(0);
            $table->timestamp('last_updated')->nullable();
            $table->unique(['item_id', 'warehouse_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_stocks');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
