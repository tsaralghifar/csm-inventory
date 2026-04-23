<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturBarang extends Model
{
    use SoftDeletes;

    protected $table = 'retur_barang';

    protected $fillable = [
        'retur_number', 'purchase_order_id', 'warehouse_id',
        'vendor_name', 'vendor_contact',
        'retur_date', 'alasan', 'notes',
        'status', 'created_by', 'confirmed_by', 'confirmed_at',
    ];

    protected $casts = [
        'retur_date'   => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items()
    {
        return $this->hasMany(ReturBarangItem::class);
    }

    public static function generateNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "RET-{$date}-";

        $last = static::lockForUpdate()
            ->where('retur_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(retur_number FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('retur_number');

        $lastNumber = $last ? (int) substr($last, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }
}