<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPriceHistory extends Model
{
    protected $table = 'item_price_history';

    protected $fillable = [
        'item_id', 'warehouse_id', 'purchase_price', 'avg_price_before',
        'avg_price_after', 'qty_received', 'reference_no', 'source_type',
        'created_by', 'transaction_date',
        // kolom baru
        'supplier_name', 'supplier_id', 'price_change_pct',
        'severity', 'prev_purchase_price',
    ];

    protected $casts = [
        'purchase_price'      => 'decimal:2',
        'avg_price_before'    => 'decimal:2',
        'avg_price_after'     => 'decimal:2',
        'prev_purchase_price' => 'decimal:2',
        'price_change_pct'    => 'decimal:2',
        'qty_received'        => 'decimal:2',
        'transaction_date'    => 'date',
    ];

    public function item()      { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function supplier()  { return $this->belongsTo(Supplier::class); }

    public function getSeverityLabel(): string
    {
        return match ($this->severity) {
            'up_low'      => '↑ Naik Rendah',
            'up_high'     => '↑ Naik Tinggi',
            'up_critical' => '↑ Naik Kritis',
            'down'        => '↓ Turun',
            'normal'      => '= Normal',
            default       => '-',
        };
    }

    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'up_low'      => 'warning',
            'up_high'     => 'orange',
            'up_critical' => 'danger',
            'down'        => 'success',
            default       => 'secondary',
        };
    }
}