<?php

namespace App\Services;

use App\Models\ItemStock;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * LowStockAlertService
 *
 * Bertanggung jawab mendeteksi item stok menipis/kritis dan
 * mengirimkan notifikasi ke user yang relevan.
 *
 * Penggunaan:
 *   // Di StockService setelah setiap mutasi stok:
 *   app(LowStockAlertService::class)->checkAndAlert($warehouseId, $itemId);
 *
 *   // Atau scan seluruh gudang (bisa dijadwalkan via Artisan):
 *   app(LowStockAlertService::class)->scanAll();
 */
class LowStockAlertService
{
    // Cooldown: alert yang sama tidak akan dikirim ulang dalam X menit
    private const ALERT_COOLDOWN_MINUTES = 60;

    /**
     * Cek stok item tertentu di warehouse tertentu setelah ada mutasi.
     * Dipanggil dari StockService.
     */
    public function checkAndAlert(int $warehouseId, int $itemId): void
    {
        $stock = ItemStock::with('item', 'warehouse')
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->first();

        if (!$stock || !$stock->item) {
            return;
        }

        $level = $this->getAlertLevel($stock);

        if ($level === null) {
            return; // Stok aman, tidak perlu notifikasi
        }

        $cacheKey = "low_stock_alerted:{$warehouseId}:{$itemId}:{$level}";

        if (Cache::has($cacheKey)) {
            return; // Sudah pernah dinotifikasi dalam cooldown window
        }

        $this->sendNotification($stock, $level);

        Cache::put($cacheKey, true, now()->addMinutes(self::ALERT_COOLDOWN_MINUTES));
    }

    /**
     * Scan semua item stok — untuk dijalankan via Artisan Command / Scheduler.
     *
     * Tambahkan di app/Console/Kernel.php:
     *   $schedule->call(fn() => app(LowStockAlertService::class)->scanAll())
     *            ->hourly()
     *            ->name('low-stock-scan');
     */
    public function scanAll(): array
    {
        $alerts = [];

        $criticalStocks = ItemStock::with('item.category', 'warehouse')
            ->join('items', 'item_stocks.item_id', '=', 'items.id')
            ->whereColumn('item_stocks.qty', '<=', 'items.min_stock')
            ->where('items.min_stock', '>', 0)
            ->select('item_stocks.*')
            ->get();

        foreach ($criticalStocks as $stock) {
            $level    = $this->getAlertLevel($stock);
            $cacheKey = "low_stock_alerted:{$stock->warehouse_id}:{$stock->item_id}:{$level}";

            if ($level && !Cache::has($cacheKey)) {
                $this->sendNotification($stock, $level);
                Cache::put($cacheKey, true, now()->addMinutes(self::ALERT_COOLDOWN_MINUTES));
                $alerts[] = [
                    'item'      => $stock->item->name,
                    'warehouse' => $stock->warehouse->name,
                    'qty'       => $stock->qty,
                    'level'     => $level,
                ];
            }
        }

        return $alerts;
    }

    /**
     * Ambil daftar stok menipis yang sudah diformat untuk response API / dashboard.
     */
    public function getLowStockSummary(array $warehouseIds): array
    {
        $stocks = ItemStock::with('item.category', 'warehouse')
            ->join('items', 'item_stocks.item_id', '=', 'items.id')
            ->whereIn('item_stocks.warehouse_id', $warehouseIds)
            ->where(function ($q) {
                $q->whereColumn('item_stocks.qty', '<=', 'items.min_stock')
                  ->where('items.min_stock', '>', 0)
                  ->orWhere('item_stocks.qty', '<', 0);
            })
            ->select('item_stocks.*')
            ->orderBy('item_stocks.qty')
            ->get();

        return $stocks->map(function (ItemStock $s) {
            $level = $this->getAlertLevel($s);
            return [
                'id'           => $s->id,
                'item_id'      => $s->item_id,
                'item_name'    => $s->item->name,
                'part_number'  => $s->item->part_number,
                'category'     => $s->item->category?->name,
                'warehouse_id' => $s->warehouse_id,
                'warehouse'    => $s->warehouse->name,
                'qty'          => (float) $s->qty,
                'min_stock'    => (float) $s->item->min_stock,
                'unit'         => $s->item->unit,
                'alert_level'  => $level, // 'minus' | 'critical' | 'low'
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Tentukan level alert berdasarkan qty vs min_stock.
     *
     * minus    → qty < 0
     * critical → qty == 0 atau qty <= min_stock * 0.5
     * low      → qty <= min_stock
     */
    private function getAlertLevel(ItemStock $stock): ?string
    {
        $qty      = (float) $stock->qty;
        $minStock = (float) ($stock->item->min_stock ?? 0);

        if ($qty < 0) {
            return 'minus';
        }

        if ($minStock <= 0) {
            return null; // Tidak ada min_stock → tidak perlu alert
        }

        if ($qty === 0.0 || $qty <= ($minStock * 0.5)) {
            return 'critical';
        }

        if ($qty <= $minStock) {
            return 'low';
        }

        return null;
    }

    private function sendNotification(ItemStock $stock, string $level): void
    {
        // Kirim ke semua user yang punya akses ke warehouse ini
        $users = User::where(function ($q) use ($stock) {
            $q->whereNull('warehouse_id')         // HO / Admin / Purchasing
              ->orWhere('warehouse_id', $stock->warehouse_id);
        })
        ->where('is_active', true)
        ->get();

        // Filter: hanya role yang relevan
        $recipients = $users->filter(function (User $user) {
            return $user->isSuperuser()
                || $user->isAdminHO()
                || $user->hasRole('purchasing')
                || $user->hasRole('manager')
                || $user->hasRole('warehouse');
        });

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new LowStockNotification($stock, $level));
    }
}
