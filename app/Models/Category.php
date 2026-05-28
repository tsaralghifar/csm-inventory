<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description'];

    // TTL cache: 1 jam — kategori sangat jarang berubah
    private const CACHE_TTL = 3600;
    private const CACHE_KEY = 'categories.all';

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Ambil semua kategori dari cache.
     * Gunakan method ini di controller/service sebagai pengganti Category::all().
     */
    public static function getCached()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn() => self::orderBy('name')->get());
    }

    /**
     * Invalidate cache kategori.
     * Dipanggil otomatis via boot() saat ada perubahan data.
     */
    public static function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
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
    }
}