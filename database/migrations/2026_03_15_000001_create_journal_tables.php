<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number', 30)->unique();
            $table->date('entry_date');
            $table->string('description');
            $table->string('reference_type', 50)->nullable(); // main_cash_transaction, petty_cash, supplier_payment, payroll
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, posted
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('entry_date');
            $table->index('status');
        });

        Schema::create('journal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->string('account_code', 20);
            $table->string('account_name', 100);
            $table->string('account_type', 20); // asset, liability, equity, revenue, expense
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('account_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_items');
        Schema::dropIfExists('journal_entries');
    }
};
