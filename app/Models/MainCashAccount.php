<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MainCashAccount extends Model
{
    protected $fillable = ['name', 'account_number', 'bank_name', 'balance', 'is_active', 'notes'];

    protected $casts = [
        'balance'   => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(MainCashTransaction::class);
    }
}
