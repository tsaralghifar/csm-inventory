<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    // Paksa Spatie selalu pakai guard 'web' meskipun request via sanctum
    protected $guard_name = 'web';

    protected $fillable = [
        'name', 'email', 'phone', 'employee_id', 'position',
        'signature_path',                     // ← path relatif di Storage::disk('local')
        'warehouse_id', 'password', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    // ── Hirarki Role ───────────────────────────────────────────────────────
    //
    // Level lebih tinggi = angka lebih besar.
    // Dipakai oleh signableUsers() (CELAH 1) dan resolveSigners() (CELAH 3).

    public const ROLE_HIERARCHY = [
        'logistik_site' => 1,
        'admin_site'    => 1,
        'viewer'        => 1,
        'chief_mekanik' => 2,
        'purchasing'    => 2,
        'accounting'    => 2,
        'manager'       => 3,
        'logistik_ho'   => 4,
        'admin_ho'      => 4,
        'superuser'     => 5,
    ];

    // Level minimum yang boleh menjadi penandatangan dokumen (CELAH 3)
    public const MIN_SIGNER_LEVEL = 3; // manager ke atas

    /**
     * Kembalikan level hirarki user ini (0 jika role tidak dikenal).
     */
    public function roleLevel(): int
    {
        $roleName = $this->roles->first()?->name;
        return self::ROLE_HIERARCHY[$roleName] ?? 0;
    }

    /**
     * Apakah user memiliki level hirarki cukup untuk menandatangani dokumen?
     */
    public function canSign(): bool
    {
        return $this->roleLevel() >= self::MIN_SIGNER_LEVEL;
    }

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

    /**
     * Scope: user yang sudah upload tanda tangan (signature_path tidak null).
     */
    public function scopeHasSignature($query)
    {
        return $query->whereNotNull('signature_path');
    }

    /**
     * Scope: user yang rolenya memenuhi level minimum penandatangan.
     * Dipakai oleh resolveSigners() untuk validasi awal di DB level.
     */
    public function scopeCanSign($query)
    {
        $eligibleRoles = array_keys(array_filter(
            self::ROLE_HIERARCHY,
            fn($level) => $level >= self::MIN_SIGNER_LEVEL
        ));

        return $query->role($eligibleRoles);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isSuperuser(): bool
    {
        return $this->hasRole('superuser');
    }

    public function isAdminHO(): bool
    {
        return $this->hasRole('admin_ho');
    }

    public function isLogistikHO(): bool
    {
        return $this->hasRole('logistik_ho');
    }

    public function isLogistikSite(): bool
    {
        return $this->hasRole('logistik_site');
    }

    public function canAccessWarehouse(int $warehouseId): bool
    {
        if ($this->isSuperuser() || $this->isAdminHO() || $this->isLogistikHO()) return true;
        return $this->warehouse_id === $warehouseId;
    }

    // ── Helpers tanda tangan ───────────────────────────────────────────────

    /**
     * Cek apakah user sudah upload tanda tangan.
     */
    public function hasSignature(): bool
    {
        return ! empty($this->signature_path)
            && Storage::disk('local')->exists($this->signature_path);
    }

    /**
     * Simpan file tanda tangan ke Storage::disk('local') private.
     * Path: signatures/{userId}.png
     *
     * @param  string  $pngBinary  Binary PNG data
     * @return string  Path yang disimpan di kolom signature_path
     */
    public function storeSignatureFile(string $pngBinary): string
    {
        $path = "signatures/{$this->id}.png";
        Storage::disk('local')->put($path, $pngBinary);
        return $path;
    }

    /**
     * Hapus file tanda tangan dari disk dan kosongkan kolom.
     */
    public function deleteSignatureFile(): void
    {
        if (! empty($this->signature_path)) {
            Storage::disk('local')->delete($this->signature_path);
        }
        $this->update(['signature_path' => null]);
    }

    /**
     * Kembalikan data URI siap pakai di <img src="..."> atau DomPDF.
     * Membaca dari Storage::disk('local') private.
     * Jika tidak ada signature, kembalikan null.
     */
    public function signatureDataUri(): ?string
    {
        if (empty($this->signature_path)) return null;

        if (! Storage::disk('local')->exists($this->signature_path)) return null;

        $binary = Storage::disk('local')->get($this->signature_path);
        $base64 = base64_encode($binary);

        return 'data:image/png;base64,' . $base64;
    }
}