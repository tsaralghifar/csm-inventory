<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stock movements - master table for ALL stock changes
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 50)->unique();
            $table->enum('type', ['in', 'out', 'transfer_out', 'transfer_in', 'adjustment', 'opname']);
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses');
            $table->decimal('qty', 12, 2);
            $table->decimal('qty_before', 12, 2)->default(0);
            $table->decimal('qty_after', 12, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->string('unit_code')->nullable();
            $table->string('unit_type')->nullable();
            $table->decimal('hm_km', 12, 2)->nullable();
            $table->string('po_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('mechanic')->nullable();
            $table->string('site_name')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->morphs('moveable'); // polymorphic: MR, DO, FuelLog, etc.
            $table->date('movement_date');
            $table->timestamps();
        });

        // Material Requests
        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('mr_number', 50)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->enum('status', ['draft', 'submitted', 'approved', 'dispatched', 'received', 'rejected', 'cancelled'])
                  ->default('draft');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('dispatched_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->date('needed_date')->nullable();
            $table->timestamps();
        });

        Schema::create('material_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained('material_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_request', 12, 2);
            $table->decimal('qty_approved', 12, 2)->default(0);
            $table->decimal('qty_sent', 12, 2)->default(0);
            $table->decimal('qty_received', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Delivery Orders (Surat Jalan)
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('do_number', 50)->unique();
            $table->foreignId('material_request_id')->nullable()->constrained('material_requests');
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->enum('status', ['pending', 'sent', 'received', 'partial'])->default('pending');
            $table->string('driver_name')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('receive_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_sent', 12, 2);
            $table->decimal('qty_received', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_items');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
        Schema::dropIfExists('stock_movements');
    }
};
