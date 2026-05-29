<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    // Paksa Spatie selalu pakai guard 'web' meskipun request via sanctum
    protected $guard_name = 'web';

    protected $fillable = [
        'name', 'email', 'phone', 'employee_id', 'position',
        'signature',                          // ← base64 PNG tanda tangan
        'warehouse_id', 'password', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    // ── Relasi ─────────────────────────────────────────────────────────────

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHasSignature($query)
    {
        return $query->whereNotNull('signature');
    }

    // ── Helpers (tidak berubah dari asli) ──────────────────────────────────

    public function isSuperuser(): bool
    {
        return $this->hasRole('superuser');
    }

    public function isAdminHO(): bool
    {
        return $this->hasRole('admin_ho');
    }

    public function canAccessWarehouse(int $warehouseId): bool
    {
        if ($this->isSuperuser() || $this->isAdminHO()) return true;
        return $this->warehouse_id === $warehouseId;
    }

    // ── Helpers tanda tangan ───────────────────────────────────────────────

    /**
     * Cek apakah user sudah upload tanda tangan.
     */
    public function hasSignature(): bool
    {
        return ! empty($this->signature);
    }

    /**
     * Kembalikan data URI siap pakai di <img src="..."> atau DomPDF.
     * Jika signature sudah berupa data URI, kembalikan apa adanya.
     * Jika tidak ada signature, kembalikan null.
     */
    public function signatureDataUri(): ?string
    {
        if (empty($this->signature)) return null;

        // Sudah berformat data URI
        if (str_starts_with($this->signature, 'data:')) {
            return $this->signature;
        }

        // Plain base64 — tambahkan header PNG
        return 'data:image/png;base64,' . $this->signature;
    }
}