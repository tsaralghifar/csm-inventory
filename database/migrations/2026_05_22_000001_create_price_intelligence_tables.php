<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Tambah kolom supplier & severity ke item_price_history ─────────
        Schema::table('item_price_history', function (Blueprint $table) {
            $table->string('supplier_name', 150)->nullable()->after('reference_no');
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')
                  ->constrained('suppliers')->nullOnDelete();
            $table->decimal('price_change_pct', 8, 2)->nullable()->after('avg_price_after')
                  ->comment('Persentase perubahan harga vs pembelian terakhir');
            $table->string('severity', 20)->nullable()->after('price_change_pct')
                  ->comment('normal | up_low | up_high | up_critical | down');
            $table->decimal('prev_purchase_price', 15, 2)->nullable()->after('severity')
                  ->comment('Harga beli terakhir sebelumnya dari supplier manapun');
        });

        // ── 2. Tabel konfigurasi alert harga ─────────────────────────────────
        Schema::create('price_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 500);
            $table->string('label', 200)->nullable();
            $table->string('type', 20)->default('number')
                  ->comment('number | time | boolean | select');
            $table->timestamps();
        });

        // ── 3. Tabel log anomali harga ────────────────────────────────────────
        Schema::create('price_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('anomaly_type', 50)
                  ->comment('price_spike | consecutive_increase | po_vs_receive | budget_exceeded');
            $table->string('severity', 20)->default('warning')
                  ->comment('info | warning | critical');
            $table->decimal('value_before', 15, 2)->nullable();
            $table->decimal('value_after', 15, 2)->nullable();
            $table->decimal('change_pct', 8, 2)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->string('supplier_name', 150)->nullable();
            $table->json('meta')->nullable()->comment('Data tambahan spesifik per anomaly_type');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'anomaly_type', 'created_at']);
            $table->index(['is_read', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_anomalies');
        Schema::dropIfExists('price_alert_settings');
        Schema::table('item_price_history', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_name', 'supplier_id', 'price_change_pct',
                'severity', 'prev_purchase_price',
            ]);
        });
    }
};
