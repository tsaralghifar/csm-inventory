<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonPengeluaran extends Model
{
    use SoftDeletes;

    protected $table = 'bon_pengeluaran';

    protected $fillable = [
        'bon_number', 'material_request_id', 'permintaan_material_id',
        'warehouse_id', 'created_by',
        'approved_by', 'approved_at', 'status', 'received_by', 'issue_date', 'notes',
        'unit_code', 'unit_type', 'hm_km', 'mechanic', 'po_number',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'issue_date'  => 'date',
    ];

    public function materialRequest()
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function permintaanMaterial()
    {
        return $this->belongsTo(PermintaanMaterial::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(BonPengeluaranItem::class);
    }

    public static function generateNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "BON-{$date}-";

        $last = static::lockForUpdate()
            ->where('bon_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(bon_number FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('bon_number');

        $lastNumber = $last ? (int) substr($last, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }
}