<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'type', 'location', 'address',
        'pic_name', 'pic_phone', 'is_active', 'notes',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // TTL cache: 1 jam — data warehouse jarang berubah
    private const CACHE_TTL     = 3600;
    private const CACHE_ALL     = 'warehouses.all';
    private const CACHE_ALL_IDS = 'warehouse.all_ids';

    // ─── Cached queries ──────────────────────────────────────────────────────

    /**
     * Ambil semua warehouse aktif dari cache.
     * Gunakan sebagai pengganti Warehouse::active()->get() di tempat yang tidak butuh real-time.
     */
    public static function getCached()
    {
        return Cache::remember(self::CACHE_ALL, self::CACHE_TTL, fn() => self::active()->orderBy('name')->get());
    }

    /**
     * Ambil semua ID warehouse dari cache (digunakan DashboardService).
     */
    public static function getAllIdsCached(): array
    {
        return Cache::remember(self::CACHE_ALL_IDS, self::CACHE_TTL, fn() => self::pluck('id')->toArray());
    }

    /**
     * Invalidate semua cache warehouse.
     * Dipanggil otomatis via boot() saat ada perubahan data.
     */
    public static function invalidateCache(): void
    {
        Cache::forget(self::CACHE_ALL);
        Cache::forget(self::CACHE_ALL_IDS);
    }

    /**
     * Auto-invalidate cache saat create / update / delete.
     */
    protected static function boot(): void
    {
        parent::boot();

        $invalidate = fn() => self::invalidateCache();

        static::created($invalidate);
        static::updated($invalidate);
        static::deleted($invalidate);
        static::restored($invalidate);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function itemStocks()
    {
        return $this->hasMany(ItemStock::class);
    }

    public function stockMovementsFrom()
    {
        return $this->hasMany(StockMovement::class, 'from_warehouse_id');
    }

    public function stockMovementsTo()
    {
        return $this->hasMany(StockMovement::class, 'to_warehouse_id');
    }

    public function materialRequestsFrom()
    {
        return $this->hasMany(MaterialRequest::class, 'from_warehouse_id');
    }

    public function materialRequestsTo()
    {
        return $this->hasMany(MaterialRequest::class, 'to_warehouse_id');
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHO($query)
    {
        return $query->where('type', 'ho');
    }

    public function scopeSite($query)
    {
        return $query->where('type', 'site');
    }
}