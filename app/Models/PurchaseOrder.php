<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'po_number', 'material_request_id', 'permintaan_material_id',
        'warehouse_id', 'created_by',
        'status', 'vendor_name', 'vendor_contact',
        'total_amount', 'ppn_percent', 'ppn_amount', 'grand_total',
        'diskon_persen', 'diskon_amount',
        'expected_date', 'notes',
    ];

    protected $casts = [
        'total_amount'  => 'decimal:2',
        'ppn_percent'   => 'decimal:2',
        'ppn_amount'    => 'decimal:2',
        'grand_total'   => 'decimal:2',
        'diskon_persen' => 'decimal:2',
        'diskon_amount' => 'decimal:2',
        'expected_date' => 'date',
    ];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    /** Relasi lama (single PM) — backward compat */
    public function permintaanMaterial()
    {
        return $this->belongsTo(PermintaanMaterial::class);
    }

    /** Relasi baru: Many-to-Many PO ↔ PM */
    public function permintaanMaterials()
    {
        return $this->belongsToMany(
            PermintaanMaterial::class,
            'purchase_order_permintaan_material',
            'purchase_order_id',
            'permintaan_material_id'
        )->withTimestamps();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function suratJalan()
    {
        return $this->hasMany(SuratJalan::class);
    }

    public static function generateNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "PO-{$date}-";

        $last = static::lockForUpdate()
            ->where('po_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(po_number FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('po_number');

        $lastNumber = $last ? (int) substr($last, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }
}