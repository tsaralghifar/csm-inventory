<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'type', 'location', 'address',
        'pic_name', 'pic_phone', 'is_active', 'notes',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function itemStocks()
    {
        return $this->hasMany(ItemStock::class);
    }

    public function stockMovementsFrom()
    {
        return $this->hasMany(StockMovement::class, 'from_warehouse_id');
    }

    public function stockMovementsTo()
    {
        return $this->hasMany(StockMovement::class, 'to_warehouse_id');
    }

    public function materialRequestsFrom()
    {
        return $this->hasMany(MaterialRequest::class, 'from_warehouse_id');
    }

    public function materialRequestsTo()
    {
        return $this->hasMany(MaterialRequest::class, 'to_warehouse_id');
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHO($query)
    {
        return $query->where('type', 'ho');
    }

    public function scopeSite($query)
    {
        return $query->where('type', 'site');
    }
}
