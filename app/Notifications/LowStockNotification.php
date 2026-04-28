<?php

namespace App\Notifications;

use App\Models\ItemStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * LowStockNotification
 *
 * Mengirim notifikasi stok menipis via:
 *   - Database (in-app notification bell)
 *   - Broadcast (realtime via Laravel Reverb yang sudah ada)
 *   - (Opsional) Email — aktifkan dengan uncomment method toMail()
 *
 * Setup migration untuk tabel notifications:
 *   php artisan notifications:table
 *   php artisan migrate
 */
class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $payload;

    public function __construct(
        private readonly ItemStock $stock,
        private readonly string $level  // 'minus' | 'critical' | 'low'
    ) {
        $this->payload = [
            'type'         => 'low_stock',
            'level'        => $level,
            'item_id'      => $stock->item_id,
            'item_name'    => $stock->item->name,
            'part_number'  => $stock->item->part_number,
            'warehouse_id' => $stock->warehouse_id,
            'warehouse'    => $stock->warehouse->name,
            'qty'          => (float) $stock->qty,
            'min_stock'    => (float) $stock->item->min_stock,
            'unit'         => $stock->item->unit,
            'url'          => '/stok/ho?search=' . urlencode($stock->item->part_number ?? $stock->item->name),
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    // ── Database (in-app notification) ────────────────────────────────────────

    public function toDatabase(object $notifiable): array
    {
        return array_merge($this->payload, [
            'title'   => $this->buildTitle(),
            'message' => $this->buildMessage(),
            'icon'    => $this->levelIcon(),
            'color'   => $this->levelColor(),
        ]);
    }

    // ── Broadcast (Reverb realtime) ────────────────────────────────────────────

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge($this->payload, [
            'title'   => $this->buildTitle(),
            'message' => $this->buildMessage(),
            'icon'    => $this->levelIcon(),
            'color'   => $this->levelColor(),
        ]));
    }

    // ── Email (opsional, uncomment jika diperlukan) ───────────────────────────

    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->subject('[CSM] ' . $this->buildTitle())
    //         ->greeting('Halo ' . $notifiable->name . ',')
    //         ->line($this->buildMessage())
    //         ->line('Gudang: ' . $this->payload['warehouse'])
    //         ->line('Stok saat ini: ' . $this->payload['qty'] . ' ' . $this->payload['unit'])
    //         ->line('Minimum stok: ' . $this->payload['min_stock'] . ' ' . $this->payload['unit'])
    //         ->action('Lihat Stok', url($this->payload['url']))
    //         ->salutation('Sistem CSM Inventory');
    // }

    // ──────────────────────────────────────────────────────────────────────────

    private function buildTitle(): string
    {
        return match ($this->level) {
            'minus'    => "⛔ Stok Minus: {$this->stock->item->name}",
            'critical' => "🔴 Stok Kritis: {$this->stock->item->name}",
            default    => "⚠️ Stok Menipis: {$this->stock->item->name}",
        };
    }

    private function buildMessage(): string
    {
        $qty      = number_format((float) $this->stock->qty, 2, ',', '.');
        $minStock = number_format((float) $this->stock->item->min_stock, 2, ',', '.');
        $unit     = $this->stock->item->unit;
        $wh       = $this->stock->warehouse->name;

        return match ($this->level) {
            'minus'    => "Stok {$wh} sebesar {$qty} {$unit} (minus). Segera lakukan koreksi.",
            'critical' => "Stok {$wh} sebesar {$qty} {$unit}. Minimum: {$minStock} {$unit}. Segera restok.",
            default    => "Stok {$wh} sebesar {$qty} {$unit} mendekati minimum ({$minStock} {$unit}).",
        };
    }

    private function levelIcon(): string
    {
        return match ($this->level) {
            'minus'    => 'bi-exclamation-octagon-fill',
            'critical' => 'bi-exclamation-triangle-fill',
            default    => 'bi-exclamation-circle',
        };
    }

    private function levelColor(): string
    {
        return match ($this->level) {
            'minus'    => 'danger',
            'critical' => 'warning',
            default    => 'info',
        };
    }
}
