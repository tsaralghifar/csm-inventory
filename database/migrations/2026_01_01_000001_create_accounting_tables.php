<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── KAS KECIL (PETTY CASH) ──────────────────────────────────────────
        Schema::create('petty_cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // Nama kas kecil
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('balance', 18, 2)->default(0); // Saldo saat ini
            $table->decimal('limit', 18, 2)->default(0);   // Batas kas kecil
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('petty_cash_account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in', 'out']);            // Kas masuk / keluar
            $table->decimal('amount', 18, 2);
            $table->string('description');
            $table->string('reference_number')->nullable(); // No. bukti / nota
            $table->date('transaction_date');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── KAS BESAR (MAIN CASH) ────────────────────────────────────────────
        Schema::create('main_cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_number')->nullable();   // No. rekening bank
            $table->string('bank_name')->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('main_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('main_cash_account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->decimal('amount', 18, 2);
            $table->string('description');
            $table->string('reference_number')->nullable();
            $table->date('transaction_date');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── SUPPLIER ─────────────────────────────────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('npwp')->nullable();
            $table->decimal('outstanding_balance', 18, 2)->default(0); // Total hutang
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── INVOICE SUPPLIER ─────────────────────────────────────────────────
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();     // No. invoice dari supplier
            $table->string('internal_number')->unique();    // No. internal
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);  // PPN
            $table->decimal('total_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('remaining_amount', 18, 2);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->enum('status', ['unpaid', 'partial', 'paid', 'cancelled'])->default('unpaid');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── PEMBAYARAN SUPPLIER ───────────────────────────────────────────────
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('main_cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('payment_date');
            $table->enum('payment_method', ['transfer', 'cash', 'giro', 'cek'])->default('transfer');
            $table->string('reference_number')->nullable(); // No. transfer / giro
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('main_cash_transactions');
        Schema::dropIfExists('main_cash_accounts');
        Schema::dropIfExists('petty_cash_transactions');
        Schema::dropIfExists('petty_cash_accounts');
    }
};
