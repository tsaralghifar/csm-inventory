<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PermintaanMaterial extends Model
{
    use SoftDeletes;

    protected $table = 'permintaan_material';

    protected $fillable = [
        'nomor', 'warehouse_id', 'type', 'requested_by',
        'chief_authorized_by', 'chief_authorized_at',
        'manager_approved_by', 'manager_approved_at',
        'ho_approved_by', 'ho_approved_at',
        'po_submitted_by', 'po_submitted_at',
        'status', 'rejection_reason', 'notes', 'needed_date',
    ];

    protected $casts = [
        'chief_authorized_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'ho_approved_at'      => 'datetime',
        'po_submitted_at'     => 'datetime',
        'needed_date'         => 'date',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function chiefAuthorizer()
    {
        return $this->belongsTo(User::class, 'chief_authorized_by');
    }

    public function managerApprover()
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function hoApprover()
    {
        return $this->belongsTo(User::class, 'ho_approved_by');
    }

    public function poSubmitter()
    {
        return $this->belongsTo(User::class, 'po_submitted_by');
    }

    public function items()
    {
        return $this->hasMany(PermintaanMaterialItem::class);
    }

    public function bonPengeluaran()
    {
        return $this->hasMany(BonPengeluaran::class, 'permintaan_material_id');
    }

    /** Relasi Many-to-Many PM ↔ PO */
    public function purchaseOrders()
    {
        return $this->belongsToMany(
            PurchaseOrder::class,
            'purchase_order_permintaan_material',
            'permintaan_material_id',
            'purchase_order_id'
        )->withTimestamps();
    }

    /**
     * Hitung total qty yang sudah di-PO-kan per item PM.
     * Return array: [pm_item_id => qty_ordered]
     */
    public function getQtyOrderedPerItem(): array
    {
        $result = [];
        foreach ($this->items as $pmItem) {
            $qty = DB::table('purchase_order_items')
                ->where('permintaan_material_item_id', $pmItem->id)
                ->sum('qty');
            $result[$pmItem->id] = (float) $qty;
        }
        return $result;
    }

    /**
     * Cek apakah semua item PM sudah sepenuhnya masuk PO.
     */
    public function isFullyOrdered(): bool
    {
        $this->loadMissing('items');
        foreach ($this->items as $pmItem) {
            $qtyOrdered = DB::table('purchase_order_items')
                ->where('permintaan_material_item_id', $pmItem->id)
                ->sum('qty');
            if ((float) $qtyOrdered < (float) $pmItem->qty) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generate nomor unik dengan database lock untuk menghindari race condition.
     */
    public static function generateNomor(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "PM-{$date}-";

        // withTrashed() agar nomor yang sudah soft-deleted tetap dihitung,
        // sehingga tidak terjadi duplicate key violation
        $last = static::withTrashed()
            ->lockForUpdate()
            ->where('nomor', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(nomor FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('nomor');

        $lastNumber = $last ? (int) substr($last, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }
}