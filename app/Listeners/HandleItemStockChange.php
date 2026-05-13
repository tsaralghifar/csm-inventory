<?php

namespace App\Listeners;

use App\Events\ItemUpdated;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * HandleItemStockChange
 *
 * Dijalankan setiap kali ItemUpdated di-fire.
 * Untuk action stock_in / stock_out, cek apakah stok setelah perubahan
 * sudah masuk kategori low/critical/minus dan kirim notifikasi ke admin.
 */
class HandleItemStockChange implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(ItemUpdated $event): void
    {
        if (!in_array($event->action, ['stock_in', 'stock_out'])) {
            return;
        }

        $item        = $event->item;
        $warehouseId = $event->warehouseId;

        if (!$warehouseId) {
            return;
        }

        $stock = $item->itemStocks()
            ->with(['item', 'warehouse'])
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$stock) {
            return;
        }

        $level = null;

        if ((float) $stock->qty < 0) {
            $level = 'minus';
        } elseif ($item->min_stock > 0 && (float) $stock->qty <= ($item->min_stock * 0.25)) {
            $level = 'critical';
        } elseif ($item->min_stock > 0 && (float) $stock->qty <= $item->min_stock) {
            $level = 'low';
        }

        if (!$level) {
            return;
        }

        $recipients = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['superuser', 'admin_ho'])
        )->get();

        foreach ($recipients as $user) {
            $user->notify(new LowStockNotification($stock, $level));
        }

        $warehouseAdmins = User::where('warehouse_id', $warehouseId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin_gudang'))
            ->get();

        foreach ($warehouseAdmins as $admin) {
            $admin->notify(new LowStockNotification($stock, $level));
        }
    }

    public function failed(ItemUpdated $event, \Throwable $exception): void
    {
        \Log::error('HandleItemStockChange failed', [
            'item_id'      => $event->item->id,
            'action'       => $event->action,
            'warehouse_id' => $event->warehouseId,
            'error'        => $exception->getMessage(),
        ]);
    }
}