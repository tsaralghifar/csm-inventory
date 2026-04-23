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
    ];

    protected $casts = [
        'purchase_price'   => 'decimal:2',
        'avg_price_before' => 'decimal:2',
        'avg_price_after'  => 'decimal:2',
        'qty_received'     => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function item()      { return $this->belongsTo(Item::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
}
