<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id',
        'basic_salary', 'allowance_transport', 'allowance_meal',
        'allowance_position', 'allowance_other',
        'bonus', 'thr', 'overtime',
        'deduction_loan', 'deduction_bpjs_tk', 'deduction_bpjs_kes',
        'deduction_pph21', 'deduction_fine', 'deduction_other',
        'gross_salary', 'total_deduction', 'net_salary',
        'status', 'notes',
    ];

    protected $casts = [
        'basic_salary'         => 'decimal:2',
        'allowance_transport'  => 'decimal:2',
        'allowance_meal'       => 'decimal:2',
        'allowance_position'   => 'decimal:2',
        'allowance_other'      => 'decimal:2',
        'bonus'                => 'decimal:2',
        'thr'                  => 'decimal:2',
        'overtime'             => 'decimal:2',
        'deduction_loan'       => 'decimal:2',
        'deduction_bpjs_tk'    => 'decimal:2',
        'deduction_bpjs_kes'   => 'decimal:2',
        'deduction_pph21'      => 'decimal:2',
        'deduction_fine'       => 'decimal:2',
        'deduction_other'      => 'decimal:2',
        'gross_salary'         => 'decimal:2',
        'total_deduction'      => 'decimal:2',
        'net_salary'           => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

// ─── EmployeeLoan ─────────────────────────────────────────────────────────────
// Simpan di app/Models/EmployeeLoan.php

// <?php
// namespace App\Models;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
// class EmployeeLoan extends Model {
//     protected $fillable = [
//         'loan_number','employee_id','loan_amount','monthly_deduction',
//         'total_installments','paid_installments','remaining_balance',
//         'start_date','end_date','status','created_by','approved_by','approved_at','notes',
//     ];
//     protected $casts = [
//         'loan_amount'       => 'decimal:2',
//         'monthly_deduction' => 'decimal:2',
//         'remaining_balance' => 'decimal:2',
//         'start_date'        => 'date',
//         'end_date'          => 'date',
//         'approved_at'       => 'datetime',
//     ];
//     public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
//     public function creator(): BelongsTo  { return $this->belongsTo(User::class,'created_by'); }
//     public function approver(): BelongsTo { return $this->belongsTo(User::class,'approved_by'); }
// }

// ─── EmployeeSalaryComponent ──────────────────────────────────────────────────
// Simpan di app/Models/EmployeeSalaryComponent.php

// <?php
// namespace App\Models;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;
//
// class EmployeeSalaryComponent extends Model {
//     protected $fillable = [
//         'employee_id','basic_salary','allowance_transport','allowance_meal',
//         'allowance_position','allowance_other','deduction_bpjs_tk','deduction_bpjs_kes',
//     ];
//     protected $casts = [
//         'basic_salary'        => 'decimal:2',
//         'allowance_transport' => 'decimal:2',
//         'allowance_meal'      => 'decimal:2',
//         'allowance_position'  => 'decimal:2',
//         'allowance_other'     => 'decimal:2',
//         'deduction_bpjs_tk'   => 'decimal:2',
//         'deduction_bpjs_kes'  => 'decimal:2',
//     ];
//     public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
// }
