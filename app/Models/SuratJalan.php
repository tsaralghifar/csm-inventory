<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratJalan extends Model
{
    use SoftDeletes;

    protected $table = 'surat_jalan';

    protected $fillable = [
        'sj_number', 'purchase_order_id', 'material_request_id', 'warehouse_id',
        'created_by', 'received_by_user', 'received_by_name', 'status', 'vendor_name',
        'driver_name', 'vehicle_plate', 'received_date', 'notes',
    ];

    protected $casts = [
        'received_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by_user');
    }

    public function items()
    {
        return $this->hasMany(SuratJalanItem::class);
    }

    public static function generateNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "SJ-{$date}-";

        $last = static::lockForUpdate()
            ->where('sj_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(sj_number FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('sj_number');

        $lastNumber = $last ? (int) substr($last, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }
}