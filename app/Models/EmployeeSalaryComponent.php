<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryComponent extends Model
{
    protected $fillable = [
        'employee_id', 'basic_salary',
        'allowance_transport', 'allowance_meal', 'allowance_position', 'allowance_other',
        'deduction_bpjs_tk', 'deduction_bpjs_kes',
    ];

    protected $casts = [
        'basic_salary'        => 'decimal:2',
        'allowance_transport' => 'decimal:2',
        'allowance_meal'      => 'decimal:2',
        'allowance_position'  => 'decimal:2',
        'allowance_other'     => 'decimal:2',
        'deduction_bpjs_tk'   => 'decimal:2',
        'deduction_bpjs_kes'  => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Total tunjangan
    public function getTotalAllowanceAttribute(): float
    {
        return $this->allowance_transport + $this->allowance_meal
             + $this->allowance_position + $this->allowance_other;
    }

    // Estimasi gaji bersih (tanpa bonus/potongan dinamis)
    public function getEstimatedNetSalaryAttribute(): float
    {
        $gross      = $this->basic_salary + $this->total_allowance;
        $deductions = $this->deduction_bpjs_tk + $this->deduction_bpjs_kes;
        return $gross - $deductions;
    }
}
