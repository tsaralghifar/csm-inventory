<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'do_number', 'material_request_id', 'from_warehouse_id', 'to_warehouse_id',
        'status', 'driver_name', 'vehicle_plate', 'sent_by', 'received_by', 'received_by_name',
        'sent_at', 'received_at', 'notes', 'receive_notes',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'moveable');
    }

    public static function generateNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "DO-{$date}-";

        $last = static::lockForUpdate()
            ->where('do_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(do_number FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('do_number');

        $lastNumber = $last ? (int) substr($last, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }
}