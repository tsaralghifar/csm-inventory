<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\MainCashTransaction;
use App\Models\PettyCashTransaction;
use App\Models\SupplierPayment;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

/**
 * JournalService
 * Otomatis membuat JournalEntry double-entry dari berbagai transaksi keuangan.
 *
 * Chart of Accounts (COA) Default:
 *  1-1001  Kas / Bank (Asset)
 *  1-1002  Kas Kecil  (Asset)
 *  2-1001  Hutang Dagang / Supplier (Liability)
 *  5-1001  Beban Operasional (Expense)
 *  5-1002  Beban Gaji & Tunjangan (Expense)
 *  4-1001  Pendapatan Lain-lain (Revenue)
 */
class JournalService
{
    // ──────────────────────────────────────────────────────────────────
    // Kas Besar (MainCashTransaction)
    // ──────────────────────────────────────────────────────────────────
    public function fromMainCash(MainCashTransaction $trx, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($trx, $userId) {
            $entry = JournalEntry::create([
                'journal_number'  => JournalEntry::generateNumber(),
                'entry_date'      => $trx->transaction_date,
                'description'     => $trx->description,
                'reference_type'  => 'main_cash_transaction',
                'reference_id'    => $trx->id,
                'total_debit'     => $trx->amount,
                'total_credit'    => $trx->amount,
                'status'          => 'posted',
                'created_by'      => $userId,
                'posted_by'       => $userId,
                'posted_at'       => now(),
            ]);

            if ($trx->type === 'in') {
                // Debit Kas, Credit Pendapatan
                $this->addItem($entry, '1-1001', 'Kas / Bank',            'asset',   $trx->amount, 0,            $trx->description);
                $this->addItem($entry, '4-1001', 'Pendapatan Lain-lain',  'revenue', 0,            $trx->amount, $trx->description);
            } else {
                // Debit Beban, Credit Kas
                $this->addItem($entry, '5-1001', 'Beban Operasional',     'expense', $trx->amount, 0,            $trx->description);
                $this->addItem($entry, '1-1001', 'Kas / Bank',            'asset',   0,            $trx->amount, $trx->description);
            }

            return $entry;
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Kas Kecil (PettyCashTransaction)
    // ──────────────────────────────────────────────────────────────────
    public function fromPettyCash(PettyCashTransaction $trx, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($trx, $userId) {
            $entry = JournalEntry::create([
                'journal_number'  => JournalEntry::generateNumber(),
                'entry_date'      => $trx->transaction_date,
                'description'     => $trx->description,
                'reference_type'  => 'petty_cash_transaction',
                'reference_id'    => $trx->id,
                'total_debit'     => $trx->amount,
                'total_credit'    => $trx->amount,
                'status'          => 'posted',
                'created_by'      => $userId,
                'posted_by'       => $userId,
                'posted_at'       => now(),
            ]);

            if ($trx->type === 'in') {
                // Top-up: Debit Kas Kecil, Credit Kas/Bank (rekening sumber)
                $mainAccName = $trx->mainCashAccount?->name ?? 'Kas / Bank';
                $this->addItem($entry, '1-1002', 'Kas Kecil',    'asset', $trx->amount, 0,            "Top-up dari {$mainAccName}");
                $this->addItem($entry, '1-1001', $mainAccName,   'asset', 0,            $trx->amount, "Top-up kas kecil: {$trx->account->name}");
            } else {
                $this->addItem($entry, '5-1001', 'Beban Operasional', 'expense', $trx->amount, 0,            $trx->description);
                $this->addItem($entry, '1-1002', 'Kas Kecil',         'asset',   0,            $trx->amount, $trx->description);
            }

            return $entry;
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Pembayaran Supplier
    // ──────────────────────────────────────────────────────────────────
    public function fromSupplierPayment(SupplierPayment $payment, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($payment, $userId) {
            $invoice     = $payment->invoice;
            $supplierName = $payment->supplier->name ?? 'Supplier';
            $desc = "Pembayaran invoice {$invoice->invoice_number} ke {$supplierName}";

            $entry = JournalEntry::create([
                'journal_number'  => JournalEntry::generateNumber(),
                'entry_date'      => $payment->payment_date,
                'description'     => $desc,
                'reference_type'  => 'supplier_payment',
                'reference_id'    => $payment->id,
                'total_debit'     => $payment->amount,
                'total_credit'    => $payment->amount,
                'status'          => 'posted',
                'created_by'      => $userId,
                'posted_by'       => $userId,
                'posted_at'       => now(),
            ]);

            // Debit Hutang Dagang (mengurangi hutang), Credit Kas
            $this->addItem($entry, '2-1001', 'Hutang Dagang',   'liability', $payment->amount, 0,               $desc);
            $this->addItem($entry, '1-1001', 'Kas / Bank',      'asset',     0,               $payment->amount, $desc);

            return $entry;
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Payroll
    // ──────────────────────────────────────────────────────────────────
    public function fromPayroll(PayrollPeriod $period, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($period, $userId) {
            $totalNet   = $period->items()->sum('net_salary');
            $totalGross = $period->items()->sum('gross_salary');
            $totalDed   = $period->items()->sum('total_deduction');

            $entry = JournalEntry::create([
                'journal_number'  => JournalEntry::generateNumber(),
                'entry_date'      => $period->payment_date ?? now(),
                'description'     => "Penggajian periode {$period->name}",
                'reference_type'  => 'payroll',
                'reference_id'    => $period->id,
                'total_debit'     => $totalGross,
                'total_credit'    => $totalGross,
                'status'          => 'posted',
                'created_by'      => $userId,
                'posted_by'       => $userId,
                'posted_at'       => now(),
            ]);

            // Debit: Beban Gaji (gross)
            $this->addItem($entry, '5-1002', 'Beban Gaji & Tunjangan', 'expense',   $totalGross, 0,          "Gaji bruto {$period->name}");
            // Credit: Kas (net)
            $this->addItem($entry, '1-1001', 'Kas / Bank',             'asset',     0,           $totalNet,   "Pembayaran gaji bersih {$period->name}");
            // Credit: Potongan (hutang sementara / titipan)
            if ($totalDed > 0) {
                $this->addItem($entry, '2-1002', 'Utang Potongan Karyawan', 'liability', 0,      $totalDed,   "BPJS + Pinjaman + Potongan {$period->name}");
            }

            return $entry;
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────────────
    private function addItem(JournalEntry $entry, string $code, string $name, string $type, float $debit, float $credit, ?string $desc): void
    {
        JournalItem::create([
            'journal_entry_id' => $entry->id,
            'account_code'     => $code,
            'account_name'     => $name,
            'account_type'     => $type,
            'debit'            => $debit,
            'credit'           => $credit,
            'description'      => $desc,
        ]);
    }
}