<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'journal_number',
        'entry_date',
        'description',
        'reference_type',   // main_cash_transaction, petty_cash_transaction, supplier_payment, payroll
        'reference_id',
        'total_debit',
        'total_credit',
        'status',           // draft, posted
        'created_by',
        'posted_by',
        'posted_at',
        'notes',
    ];

    protected $casts = [
        'entry_date'  => 'date',
        'total_debit' => 'decimal:2',
        'total_credit'=> 'decimal:2',
        'posted_at'   => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // Generate nomor jurnal otomatis
    public static function generateNumber(): string
    {
        $prefix = 'JRN-' . date('Ym') . '-';
        $last   = static::where('journal_number', 'like', $prefix . '%')
            ->orderByDesc('journal_number')
            ->value('journal_number');
        $seq    = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // Validasi bahwa total debit == total credit
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }
}
