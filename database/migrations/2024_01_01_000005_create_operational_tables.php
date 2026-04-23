<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Heavy equipment units
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_code', 30)->unique();
            $table->string('type_unit');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->decimal('hm_current', 12, 2)->default(0);
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->enum('status', ['active', 'standby', 'maintenance', 'retired'])->default('active');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // Fuel / Solar logs
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->date('log_date');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('unit_code')->nullable();
            $table->string('unit_type')->nullable();
            $table->string('division')->nullable();
            $table->decimal('hm_km', 12, 2)->nullable();
            $table->time('fill_time')->nullable();
            $table->decimal('liter_out', 12, 2)->default(0);
            $table->decimal('stock_in', 12, 2)->default(0);
            $table->decimal('stock_before', 12, 2)->default(0);
            $table->decimal('stock_after', 12, 2)->default(0);
            $table->string('operator_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Employees
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 30)->unique();
            $table->string('name');
            $table->string('position');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // APD distributions
        Schema::create('apd_distributions', function (Blueprint $table) {
            $table->id();
            $table->date('distribution_date');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('qty', 12, 2);
            $table->string('size')->nullable();
            $table->string('brand')->nullable();
            $table->string('handed_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Toolbox stock opname per mechanic
        Schema::create('toolbox_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number', 50)->unique();
            $table->date('opname_date');
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->enum('status', ['draft', 'confirmed'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('toolbox_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toolbox_opname_id')->constrained('toolbox_opnames')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_code')->nullable();
            $table->decimal('qty', 12, 2);
            $table->string('unit', 20)->default('PCS');
            $table->enum('condition', ['good', 'damaged', 'lost'])->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Audit logs for all actions
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('toolbox_opname_items');
        Schema::dropIfExists('toolbox_opnames');
        Schema::dropIfExists('apd_distributions');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('units');
    }
};
