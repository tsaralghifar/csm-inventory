<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── PERIODE PENGGAJIAN ────────────────────────────────────────────────
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // Contoh: "Maret 2026"
            $table->integer('month');                       // 1-12
            $table->integer('year');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date')->nullable();
            $table->enum('status', ['draft', 'processing', 'approved', 'paid'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['month', 'year']);
        });

        // ── DETAIL GAJI PER KARYAWAN ──────────────────────────────────────────
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('basic_salary', 18, 2)->default(0);         // Gaji pokok
            $table->decimal('allowance_transport', 18, 2)->default(0);  // Tunjangan transport
            $table->decimal('allowance_meal', 18, 2)->default(0);        // Tunjangan makan
            $table->decimal('allowance_position', 18, 2)->default(0);   // Tunjangan jabatan
            $table->decimal('allowance_other', 18, 2)->default(0);      // Tunjangan lain
            $table->decimal('bonus', 18, 2)->default(0);                 // Bonus/insentif
            $table->decimal('thr', 18, 2)->default(0);                   // THR
            $table->decimal('overtime', 18, 2)->default(0);              // Lembur
            $table->decimal('deduction_loan', 18, 2)->default(0);       // Potongan pinjaman
            $table->decimal('deduction_bpjs_tk', 18, 2)->default(0);   // Potongan BPJS TK
            $table->decimal('deduction_bpjs_kes', 18, 2)->default(0);  // Potongan BPJS Kesehatan
            $table->decimal('deduction_pph21', 18, 2)->default(0);     // Potongan PPh21
            $table->decimal('deduction_fine', 18, 2)->default(0);       // Potongan denda
            $table->decimal('deduction_other', 18, 2)->default(0);     // Potongan lain
            $table->decimal('gross_salary', 18, 2)->default(0);         // Total kotor (gaji + tunjangan + bonus)
            $table->decimal('total_deduction', 18, 2)->default(0);     // Total potongan
            $table->decimal('net_salary', 18, 2)->default(0);          // Gaji bersih (take-home pay)
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
        });

        // ── PINJAMAN KARYAWAN ─────────────────────────────────────────────────
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('loan_amount', 18, 2);          // Pokok pinjaman
            $table->decimal('monthly_deduction', 18, 2);   // Cicilan per bulan
            $table->integer('total_installments');          // Total cicilan
            $table->integer('paid_installments')->default(0); // Cicilan terbayar
            $table->decimal('remaining_balance', 18, 2);   // Sisa hutang
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── KOMPONEN GAJI DEFAULT PER KARYAWAN ───────────────────────────────
        // (Disimpan agar tidak perlu input manual setiap periode)
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('allowance_transport', 18, 2)->default(0);
            $table->decimal('allowance_meal', 18, 2)->default(0);
            $table->decimal('allowance_position', 18, 2)->default(0);
            $table->decimal('allowance_other', 18, 2)->default(0);
            $table->decimal('deduction_bpjs_tk', 18, 2)->default(0);
            $table->decimal('deduction_bpjs_kes', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');
    }
};
