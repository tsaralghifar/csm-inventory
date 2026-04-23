<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    protected $fillable = [
        'mr_number', 'type', 'from_warehouse_id', 'to_warehouse_id', 'status',
        'requested_by', 'approved_by', 'dispatched_by', 'received_by',
        'chief_authorized_by', 'chief_authorized_at',
        'manager_approved_by', 'manager_approved_at',
        'atasan_approved_by', 'atasan_approved_at',
        'submitted_at', 'approved_at', 'dispatched_at', 'received_at',
        'notes', 'rejection_reason', 'needed_date',
    ];

    protected $casts = [
        'submitted_at'        => 'datetime',
        'approved_at'         => 'datetime',
        'dispatched_at'       => 'datetime',
        'received_at'         => 'datetime',
        'chief_authorized_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'atasan_approved_at'  => 'datetime',
        'needed_date'         => 'date',
    ];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function chiefAuthorizer()
    {
        return $this->belongsTo(User::class, 'chief_authorized_by');
    }

    public function managerApprover()
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function atasanApprover()
    {
        return $this->belongsTo(User::class, 'atasan_approved_by');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function bonPengeluaran()
    {
        return $this->hasMany(BonPengeluaran::class);
    }

    public function suratJalan()
    {
        return $this->hasMany(SuratJalan::class);
    }

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'moveable');
    }

    public static function generateNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "MR-{$date}-";

        $last = static::lockForUpdate()
            ->where('mr_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(mr_number FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('mr_number');

        $lastNumber = $last ? (int) substr($last, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'draft' => 'secondary',
            'submitted' => 'info',
            'approved' => 'primary',
            'dispatched' => 'warning',
            'received' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'dark',
            default => 'secondary',
        };
    }
}