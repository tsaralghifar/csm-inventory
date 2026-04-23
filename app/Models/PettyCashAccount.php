<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashAccount extends Model
{
    protected $fillable = ['name', 'warehouse_id', 'balance', 'limit', 'is_active', 'notes'];

    protected $casts = [
        'balance'   => 'decimal:2',
        'limit'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PettyCashTransaction::class);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Simpan file ini di app/Models/PettyCashAccount.php
// Buat file baru app/Models/PettyCashTransaction.php dengan isi berikut:
// ─────────────────────────────────────────────────────────────────────────────

// <?php
// namespace App\Models;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
// class PettyCashTransaction extends Model {
//     protected $fillable = [
//         'transaction_number','petty_cash_account_id','type','amount',
//         'description','reference_number','transaction_date',
//         'status','created_by','approved_by','approved_at','notes',
//     ];
//     protected $casts = ['amount'=>'decimal:2','transaction_date'=>'date','approved_at'=>'datetime'];
//     public function account(): BelongsTo { return $this->belongsTo(PettyCashAccount::class,'petty_cash_account_id'); }
//     public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
//     public function approver(): BelongsTo { return $this->belongsTo(User::class,'approved_by'); }
// }
