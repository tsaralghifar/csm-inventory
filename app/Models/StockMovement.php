<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'reference_no', 'type', 'item_id', 'from_warehouse_id', 'to_warehouse_id',
        'qty', 'qty_before', 'qty_after', 'price', 'unit_code', 'unit_type',
        'hm_km', 'po_number', 'invoice_number', 'notes', 'mechanic', 'site_name',
        'created_by', 'moveable_type', 'moveable_id', 'movement_date',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'qty_before' => 'decimal:2',
        'qty_after' => 'decimal:2',
        'price' => 'decimal:2',
        'hm_km' => 'decimal:2',
        'movement_date' => 'date',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function moveable()
    {
        return $this->morphTo();
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'in' => 'Stok Masuk',
            'out' => 'Stok Keluar',
            'transfer_out' => 'Transfer Keluar',
            'transfer_in' => 'Transfer Masuk',
            'adjustment' => 'Penyesuaian',
            'opname' => 'Stok Opname',
            default => $this->type,
        };
    }
}
