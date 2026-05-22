<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PriceAlertSetting extends Model
{
    protected $table = 'price_alert_settings';

    protected $fillable = ['key', 'value', 'label', 'type'];

    /**
     * Ambil semua setting sebagai key-value array (cached 10 menit).
     */
    public static function allSettings(): array
    {
        return Cache::remember('price_alert_settings', 600, function () {
            try {
                return static::pluck('value', 'key')->toArray();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * Ambil satu nilai setting.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allSettings()[$key] ?? $default;
    }

    /**
     * Set nilai setting dan hapus cache.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('price_alert_settings');
    }
}