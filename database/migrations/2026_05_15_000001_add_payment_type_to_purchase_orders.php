<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'purchase_orders';

    private const INDEXES = [
        'idx_po_payment'          => ['payment_type', 'payment_due_date'],
        'idx_po_supplier_payment' => ['supplier_id',  'payment_type'],
    ];

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $this->addPaymentColumns($table);
            $this->addIndexes($table);
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $this->dropIndexes($table);
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['payment_type', 'payment_term_days', 'payment_due_date', 'supplier_id']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function addPaymentColumns(Blueprint $table): void
    {
        $table->enum('payment_type', ['cash', 'kredit'])
              ->default('cash')
              ->after('notes')
              ->comment('Jenis pembayaran PO');

        $table->unsignedSmallInteger('payment_term_days')
              ->nullable()
              ->after('payment_type')
              ->comment('Tenor kredit dalam hari (null jika cash)');

        $table->date('payment_due_date')
              ->nullable()
              ->after('payment_term_days')
              ->comment('Jatuh tempo; dihitung otomatis saat PO dibuat (kredit saja)');

        $table->foreignId('supplier_id')
              ->nullable()
              ->after('payment_due_date')
              ->constrained('suppliers')
              ->nullOnDelete()
              ->comment('Nullable agar backward-compatible dengan data lama');
    }

    private function addIndexes(Blueprint $table): void
    {
        foreach (self::INDEXES as $name => $columns) {
            $table->index($columns, $name);
        }
    }

    private function dropIndexes(Blueprint $table): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            $table->dropIndex($name);
        }
    }
};
