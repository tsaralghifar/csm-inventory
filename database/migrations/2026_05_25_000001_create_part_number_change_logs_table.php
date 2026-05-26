<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_number_change_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_item_id')
                  ->constrained('purchase_order_items')
                  ->cascadeOnDelete();

            $table->foreignId('purchase_order_id')
                  ->constrained('purchase_orders')
                  ->cascadeOnDelete();

            // Nullable — PO bisa berasal dari MR, bukan PM
            $table->foreignId('permintaan_material_item_id')
                  ->nullable()
                  ->constrained('permintaan_material_items')
                  ->nullOnDelete();

            // Nullable — hanya diisi jika update_master = true
            $table->foreignId('item_id')
                  ->nullable()
                  ->constrained('items')
                  ->nullOnDelete();

            $table->string('old_part_number')->nullable();
            $table->string('new_part_number');

            $table->string('po_status_at_change', 50);
            $table->boolean('update_master')->default(false);

            // Wajib diisi jika PO sudah melewati status draft
            $table->text('notes')->nullable();

            $table->foreignId('changed_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_number_change_logs');
    }
};
