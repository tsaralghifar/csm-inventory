<?php

namespace App\Jobs;

use App\Models\ItemPriceHistory;
use App\Models\PriceAlertSetting;
use App\Models\PriceAnomaly;
use App\Models\User;
use App\Notifications\PriceDigestNotification;
use App\Services\PriceAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendDailyPriceDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $settings = PriceAlertSetting::allSettings();

        // Cek apakah digest diaktifkan
        if (!($settings['digest_enabled'] ?? true)) return;

        $yesterday = now()->subDay()->toDateString();

        // Kumpulkan perubahan harga kemarin
        $changes = ItemPriceHistory::with('item', 'warehouse')
            ->whereDate('transaction_date', $yesterday)
            ->whereNotNull('price_change_pct')
            ->where('price_change_pct', '!=', 0)
            ->orderByDesc('price_change_pct')
            ->get()
            ->map(fn($h) => [
                'item_name'    => $h->item->name ?? '-',
                'part_number'  => $h->item->part_number ?? '-',
                'prev_price'   => (float) $h->prev_purchase_price,
                'new_price'    => (float) $h->purchase_price,
                'change_pct'   => (float) $h->price_change_pct,
                'severity'     => $h->severity,
                'supplier'     => $h->supplier_name,
                'reference_no' => $h->reference_no,
            ])
            ->toArray();

        // Kumpulkan anomali kemarin
        $anomalies = PriceAnomaly::with('item')
            ->whereDate('created_at', $yesterday)
            ->get()
            ->map(fn($a) => [
                'type'         => $a->getTypeLabel(),
                'severity'     => $a->severity,
                'item_name'    => $a->item->name ?? 'N/A',
                'change_pct'   => (float) $a->change_pct,
                'reference_no' => $a->reference_no,
            ])
            ->toArray();

        // Budget summary bulan ini
        $budget = $this->getBudgetSummary($settings);

        // Jika tidak ada perubahan dan tidak ada anomali — skip
        if (empty($changes) && empty($anomalies)) return;

        // Kirim ke semua Purchasing
        User::role('Purchasing')->get()->each(function ($user) use ($changes, $anomalies, $budget) {
            $user->notify(new PriceDigestNotification($changes, $anomalies, $budget));
        });
    }

    private function getBudgetSummary(array $settings): array
    {
        $months = (int) ($settings['budget_alert_months'] ?? 3);

        $thisMonthTotal = (float) DB::table('surat_jalan_items')
            ->join('surat_jalan', 'surat_jalan.id', '=', 'surat_jalan_items.surat_jalan_id')
            ->where('surat_jalan.status', 'received')
            ->whereYear('surat_jalan.received_at', now()->year)
            ->whereMonth('surat_jalan.received_at', now()->month)
            ->sum(DB::raw('surat_jalan_items.qty_received * surat_jalan_items.harga_satuan'));

        $prevTotals = [];
        for ($i = 1; $i <= $months; $i++) {
            $date  = now()->subMonths($i);
            $total = (float) DB::table('surat_jalan_items')
                ->join('surat_jalan', 'surat_jalan.id', '=', 'surat_jalan_items.surat_jalan_id')
                ->where('surat_jalan.status', 'received')
                ->whereYear('surat_jalan.received_at', $date->year)
                ->whereMonth('surat_jalan.received_at', $date->month)
                ->sum(DB::raw('surat_jalan_items.qty_received * surat_jalan_items.harga_satuan'));
            if ($total > 0) $prevTotals[] = $total;
        }

        $avg = !empty($prevTotals) ? array_sum($prevTotals) / count($prevTotals) : 0;

        return [
            'this_month'   => $thisMonthTotal,
            'avg_previous' => $avg,
            'diff_pct'     => $avg > 0 ? round((($thisMonthTotal - $avg) / $avg) * 100, 2) : 0,
        ];
    }
}
