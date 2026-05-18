<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    // ─── Konstanta ────────────────────────────────────────────────────────────

    public const PAYMENT_CASH   = 'cash';
    public const PAYMENT_KREDIT = 'kredit';

    public const STATUS_DRAFT            = 'draft';
    public const STATUS_SENT_TO_VENDOR   = 'sent_to_vendor';
    public const STATUS_PARTIAL_RECEIVED = 'partial_received';
    public const STATUS_COMPLETED        = 'completed';
    public const STATUS_CANCELLED        = 'cancelled';

    public const COMPLETABLE_STATUSES = [self::STATUS_SENT_TO_VENDOR, self::STATUS_PARTIAL_RECEIVED];
    public const ACTIVE_STATUSES      = [self::STATUS_DRAFT, self::STATUS_SENT_TO_VENDOR, self::STATUS_PARTIAL_RECEIVED];

    // ─── Fillable & Casts ─────────────────────────────────────────────────────

    protected $fillable = [
        'po_number', 'material_request_id', 'permintaan_material_id',
        'warehouse_id', 'supplier_id', 'created_by',
        'status', 'delivery_status',
        'vendor_name', 'vendor_contact',
        'total_amount', 'ppn_percent', 'ppn_amount', 'grand_total',
        'diskon_persen', 'diskon_amount',
        'expected_date', 'notes',
        'payment_type', 'payment_term_days', 'payment_due_date',
    ];

    protected $casts = [
        'total_amount'     => 'decimal:2',
        'ppn_percent'      => 'decimal:2',
        'ppn_amount'       => 'decimal:2',
        'grand_total'      => 'decimal:2',
        'diskon_persen'    => 'decimal:2',
        'diskon_amount'    => 'decimal:2',
        'expected_date'    => 'date',
        'payment_due_date' => 'date',
    ];

    // ─── Relasi ───────────────────────────────────────────────────────────────

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    /** @deprecated Gunakan permintaanMaterials() (many-to-many) */
    public function permintaanMaterial(): BelongsTo
    {
        return $this->belongsTo(PermintaanMaterial::class);
    }

    public function permintaanMaterials(): BelongsToMany
    {
        return $this->belongsToMany(
            PermintaanMaterial::class,
            'purchase_order_permintaan_material',
            'purchase_order_id',
            'permintaan_material_id',
        )->withTimestamps();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function suratJalan(): HasMany
    {
        return $this->hasMany(SuratJalan::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    // ─── Payment helpers ──────────────────────────────────────────────────────

    public function isKredit(): bool
    {
        return $this->payment_type === self::PAYMENT_KREDIT;
    }

    public function isCash(): bool
    {
        return $this->payment_type === self::PAYMENT_CASH;
    }

    /**
     * Apakah PO kredit ini sudah melewati jatuh tempo dan masih ada hutang.
     */
    public function isOverdue(): bool
    {
        return $this->isKredit()
            && $this->payment_due_date?->isPast()
            && $this->supplierInvoices()->whereIn('status', ['unpaid', 'partial'])->exists();
    }

    /**
     * Sisa hari hingga jatuh tempo. Negatif = sudah lewat.
     */
    public function daysUntilDue(): ?int
    {
        if (! $this->isKredit() || ! $this->payment_due_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->payment_due_date, false);
    }

    /**
     * Label pembayaran untuk audit log / tampilan.
     * Contoh: "Cash" | "Kredit 30 hari — 15/06/2026"
     */
    public function paymentLabel(): string
    {
        if ($this->isCash()) {
            return 'Cash';
        }

        $due = $this->payment_due_date?->format('d/m/Y') ?? '-';

        return "Kredit {$this->payment_term_days} hari — {$due}";
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isCompletable(): bool
    {
        return in_array($this->status, self::COMPLETABLE_STATUSES, true);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeCash($query)
    {
        return $query->where('payment_type', self::PAYMENT_CASH);
    }

    public function scopeKredit($query)
    {
        return $query->where('payment_type', self::PAYMENT_KREDIT);
    }

    public function scopeNearDue($query, int $days = 7)
    {
        return $query->kredit()
                     ->whereNotNull('payment_due_date')
                     ->whereDate('payment_due_date', '<=', now()->addDays($days))
                     ->whereNotIn('status', [self::STATUS_CANCELLED]);
    }

    public function scopeOverdue($query)
    {
        return $query->kredit()
                     ->whereNotNull('payment_due_date')
                     ->whereDate('payment_due_date', '<', now()->toDateString())
                     ->whereNotIn('status', [self::STATUS_CANCELLED]);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    // ─── Static helpers ───────────────────────────────────────────────────────

    public static function generateNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';

        $last = static::lockForUpdate()
            ->where('po_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(po_number FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('po_number');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return sprintf('%s%04d', $prefix, $seq);
    }

    /**
     * Hitung tanggal jatuh tempo dari sekarang + tenor.
     * Mengembalikan null jika bukan kredit atau tenor tidak diisi.
     */
    public static function calculateDueDate(?string $paymentType, ?int $termDays): ?string
    {
        if ($paymentType !== self::PAYMENT_KREDIT || ! $termDays) {
            return null;
        }

        return now()->addDays($termDays)->toDateString();
    }
}