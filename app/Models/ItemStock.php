<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemStock extends Model
{
    protected $fillable = ['item_id', 'warehouse_id', 'qty', 'qty_reserved', 'avg_price', 'last_updated'];

    protected $casts = [
        'qty' => 'decimal:2',
        'qty_reserved' => 'decimal:2',
        'avg_price' => 'decimal:2',
        'last_updated' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getAvailableQtyAttribute(): float
    {
        return max(0, (float) $this->qty - (float) $this->qty_reserved);
    }

    public function isLow(): bool
    {
        return $this->qty <= $this->item->min_stock && $this->qty > 0;
    }

    public function isMinus(): bool
    {
        return $this->qty < 0;
    }

    public function isCritical(): bool
    {
        return $this->qty <= $this->item->min_stock;
    }
}