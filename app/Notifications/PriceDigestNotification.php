<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PriceDigestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly array $changes,   // array of price changes yesterday
        public readonly array $anomalies, // array of anomalies yesterday
        public readonly array $budget,    // budget summary
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $totalChanges   = count($this->changes);
        $totalAnomalies = count($this->anomalies);
        $criticalCount  = collect($this->changes)->where('severity', 'up_critical')->count();

        $title = $criticalCount > 0
            ? "🔴 Digest Harian — {$criticalCount} perubahan harga kritis"
            : "📊 Digest Harian — {$totalChanges} perubahan harga kemarin";

        return [
            'type'           => 'digest',
            'title'          => $title,
            'body'           => "{$totalChanges} perubahan harga, {$totalAnomalies} anomali terdeteksi kemarin.",
            'severity'       => $criticalCount > 0 ? 'up_critical' : 'normal',
            'total_changes'  => $totalChanges,
            'total_anomalies'=> $totalAnomalies,
            'critical_count' => $criticalCount,
            'changes'        => $this->changes,
            'anomalies'      => $this->anomalies,
            'budget'         => $this->budget,
            'url'            => '/analitik-harga',
        ];
    }
}
