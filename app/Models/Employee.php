<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = ['employee_id', 'name', 'position', 'warehouse_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function apdDistributions()
    {
        return $this->hasMany(ApdDistribution::class);
    }

    public function toolboxOpnames()
    {
        return $this->hasMany(ToolboxOpname::class);
    }

    // ── ERP Additions ────────────────────────────────────────────────────────

    public function salaryComponent()
    {
        return $this->hasOne(EmployeeSalaryComponent::class);
    }

    public function loans()
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }
}