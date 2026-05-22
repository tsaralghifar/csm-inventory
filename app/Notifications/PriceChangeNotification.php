<?php

namespace App\Notifications;

use App\Models\ItemPriceHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PriceChangeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ItemPriceHistory $priceHistory,
        public readonly string $notifType = 'price_change', // price_change | digest | anomaly | budget
        public readonly array $extra = [],
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $item     = $this->priceHistory->item;
        $changePct = (float) $this->priceHistory->price_change_pct;
        $severity  = $this->priceHistory->severity ?? 'normal';

        $icon = match ($severity) {
            'up_critical' => '🔴',
            'up_high'     => '🟠',
            'up_low'      => '🟡',
            'down'        => '🟢',
            default       => '⚪',
        };

        $arah = $changePct > 0 ? "▲ Naik" : ($changePct < 0 ? "▼ Turun" : "=");

        return [
            'type'          => $this->notifType,
            'title'         => "{$icon} Perubahan Harga — {$item->name}",
            'body'          => "Part: {$item->part_number} | Harga: Rp " .
                               number_format($this->priceHistory->prev_purchase_price, 0, ',', '.') .
                               " → Rp " .
                               number_format($this->priceHistory->purchase_price, 0, ',', '.') .
                               " ({$arah} " . abs(round($changePct, 1)) . "%)",
            'severity'      => $severity,
            'item_id'       => $item->id,
            'item_name'     => $item->name,
            'part_number'   => $item->part_number,
            'supplier_name' => $this->priceHistory->supplier_name,
            'prev_price'    => (float) $this->priceHistory->prev_purchase_price,
            'new_price'     => (float) $this->priceHistory->purchase_price,
            'change_pct'    => $changePct,
            'reference_no'  => $this->priceHistory->reference_no,
            'url'           => '/analitik-harga?item_id=' . $item->id,
            'extra'         => $this->extra,
        ];
    }
}
