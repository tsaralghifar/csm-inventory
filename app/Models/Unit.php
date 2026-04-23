<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'unit_code', 'type_unit', 'brand', 'model', 'year',
        'hm_current', 'warehouse_id', 'status', 'is_active',
    ];

    protected $casts = ['hm_current' => 'decimal:2', 'is_active' => 'boolean'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
