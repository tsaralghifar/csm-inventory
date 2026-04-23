<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    protected $fillable = [
        'invoice_number', 'internal_number', 'supplier_id', 'purchase_order_id',
        'subtotal', 'tax_amount', 'total_amount', 'paid_amount', 'remaining_amount',
        'invoice_date', 'due_date', 'status', 'created_by', 'notes',
    ];

    protected $casts = [
        'subtotal'          => 'decimal:2',
        'tax_amount'        => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'paid_amount'       => 'decimal:2',
        'remaining_amount'  => 'decimal:2',
        'invoice_date'      => 'date',
        'due_date'          => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && $this->status !== 'paid';
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }
}
