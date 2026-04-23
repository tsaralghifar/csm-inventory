<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoan extends Model
{
    protected $fillable = [
        'loan_number', 'employee_id', 'loan_amount', 'monthly_deduction',
        'total_installments', 'paid_installments', 'remaining_balance',
        'start_date', 'end_date', 'status', 'created_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'loan_amount'        => 'decimal:2',
        'monthly_deduction'  => 'decimal:2',
        'remaining_balance'  => 'decimal:2',
        'start_date'         => 'date',
        'end_date'           => 'date',
        'approved_at'        => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Persentase progress cicilan
    public function getProgressPercentAttribute(): float
    {
        if ($this->total_installments === 0) return 0;
        return round(($this->paid_installments / $this->total_installments) * 100, 1);
    }
}
