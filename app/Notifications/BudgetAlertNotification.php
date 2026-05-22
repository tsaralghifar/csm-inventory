<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BudgetAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly float $thisMonth,
        public readonly float $avgPrevious,
        public readonly float $diffPct,
    ) {}

    public function via($notifiable): array { return ['database']; }

    public function toDatabase($notifiable): array
    {
        $severity = $this->diffPct >= 40 ? 'up_critical' : 'up_high';
        $icon     = $this->diffPct >= 40 ? '🔴' : '🟠';

        return [
            'type'         => 'budget_alert',
            'title'        => "{$icon} Budget Alert — Total pembelian bulan ini melampaui batas",
            'body'         => "Total bulan ini: Rp " . number_format($this->thisMonth, 0, ',', '.') .
                              " (▲ " . round($this->diffPct, 1) . "% dari rata-rata Rp " .
                              number_format($this->avgPrevious, 0, ',', '.') . ")",
            'severity'     => $severity,
            'this_month'   => $this->thisMonth,
            'avg_previous' => $this->avgPrevious,
            'diff_pct'     => $this->diffPct,
            'url'          => '/analitik-harga?tab=budget',
        ];
    }
}
