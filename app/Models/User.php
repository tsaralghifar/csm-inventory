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
        'warehouse_id', 'password', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

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
}