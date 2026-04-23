<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalItem extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'account_code',     // kode akun (e.g. 1-1001 Kas, 2-1001 Hutang Dagang, 6-1001 Biaya Gaji)
        'account_name',     // nama akun
        'account_type',     // asset, liability, equity, revenue, expense
        'debit',
        'credit',
        'description',
    ];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
