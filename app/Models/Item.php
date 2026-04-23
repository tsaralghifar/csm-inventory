<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'part_number', 'name', 'category_id', 'brand', 'unit',
        'min_stock', 'price', 'location_code', 'description', 'is_active',
    ];

    protected $casts = [
        'min_stock' => 'decimal:2',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function itemStocks()
    {
        return $this->hasMany(ItemStock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getStockInWarehouse(int $warehouseId): float
    {
        $stock = $this->itemStocks()->where('warehouse_id', $warehouseId)->first();
        return $stock ? (float) $stock->qty : 0;
    }

    public function getTotalStock(): float
    {
        return (float) $this->itemStocks()->sum('qty');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'ilike', "%{$keyword}%")
              ->orWhere('part_number', 'ilike', "%{$keyword}%")
              ->orWhere('brand', 'ilike', "%{$keyword}%");
        });
    }
}
