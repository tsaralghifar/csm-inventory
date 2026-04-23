<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MainCashTransaction extends Model
{
    protected $fillable = [
        'transaction_number', 'main_cash_account_id', 'type', 'amount',
        'description', 'reference_number', 'transaction_date',
        'status', 'created_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
        'approved_at'      => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MainCashAccount::class, 'main_cash_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
