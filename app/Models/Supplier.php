<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'code', 'name', 'contact_name', 'phone', 'email',
        'address', 'npwp', 'outstanding_balance', 'is_active', 'notes',
    ];

    protected $casts = [
        'outstanding_balance' => 'decimal:2',
        'is_active'           => 'boolean',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    // Scope: hanya supplier aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
