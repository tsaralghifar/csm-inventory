<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'name', 'month', 'year', 'period_start', 'period_end',
        'payment_date', 'status', 'created_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'payment_date' => 'date',
        'approved_at'  => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Total gaji bersih seluruh karyawan dalam periode ini
    public function getTotalNetSalaryAttribute(): float
    {
        return $this->items()->sum('net_salary');
    }
}
