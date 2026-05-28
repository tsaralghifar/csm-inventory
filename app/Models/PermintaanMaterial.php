<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PermintaanMaterial extends Model
{
    use SoftDeletes;

    protected $table = 'permintaan_material';

    protected $fillable = [
        'nomor',
        'warehouse_id',
        'type',
        'requested_by',
        'chief_authorized_by',
        'chief_authorized_at',
        'manager_approved_by',
        'manager_approved_at',
        'ho_approved_by',
        'ho_approved_at',
        'po_submitted_by',
        'po_submitted_at',
        'status',
        'rejection_reason',
        'notes',
        'needed_date',
    ];

    protected $casts = [
        'chief_authorized_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'ho_approved_at'      => 'datetime',
        'po_submitted_at'     => 'datetime',
        'needed_date'         => 'date',
    ];

    // ── Relationships ────────────────────────────────────────

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

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'permintaan_material_id');
    }

    public function bonPengeluaran()
    {
        return $this->hasMany(BonPengeluaran::class, 'permintaan_material_id');
    }

    // ── Business Logic ───────────────────────────────────────

    /**
     * Cek apakah semua item PM sudah ter-cover penuh oleh PO.
     * "Fully ordered" = setiap permintaan_material_item sudah ada
     * PurchaseOrderItem yang qty_pm-nya memenuhi qty PM.
     */
    public function isFullyOrdered(): bool
    {
        $items = $this->items()->with('purchaseOrderItems')->get();

        if ($items->isEmpty()) return false;

        foreach ($items as $item) {
            $totalOrdered = $item->purchaseOrderItems->sum('qty_pm');
            if ($totalOrdered < (float) $item->qty) {
                return false;
            }
        }

        return true;
    }

    // ── Static Helpers ───────────────────────────────────────

    /**
     * Generate nomor MR unik: MR-YYYYMMDD-XXXX
     * Dipanggil di dalam DB::transaction agar sequence aman.
     */
    public static function generateNomor(): string
    {
        $prefix = 'MR-' . now()->format('Ymd') . '-';

        $last = static::lockForUpdate()
            ->where('nomor', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(nomor FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('nomor');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return sprintf('%s%04d', $prefix, $seq);
    }
}