<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelLog extends Model
{
    protected $fillable = [
        'log_date', 'warehouse_id', 'unit_id', 'unit_code', 'unit_type',
        'division', 'hm_km', 'fill_time', 'liter_out', 'stock_in',
        'stock_before', 'stock_after', 'operator_name', 'notes', 'created_by',
    ];

    protected $casts = [
        'log_date' => 'date',
        'hm_km' => 'decimal:2',
        'liter_out' => 'decimal:2',
        'stock_in' => 'decimal:2',
        'stock_before' => 'decimal:2',
        'stock_after' => 'decimal:2',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
