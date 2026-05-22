<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemPriceHistory;
use App\Models\PriceAlertSetting;
use App\Models\PriceAnomaly;
use App\Models\User;
use App\Notifications\PriceChangeNotification;
use Illuminate\Support\Facades\DB;

class PriceAlertService
{
    /**
     * Dipanggil setiap kali barang diterima dari PO (Tanda Terima).
     * Analisis perubahan harga dan kirim notifikasi jika perlu.
     */
    public function analyzeAndNotify(
        int    $itemId,
        int    $warehouseId,
        float  $newPrice,
        float  $qtyReceived,
        string $referenceNo,
        string $supplierName = '',
        ?int   $supplierId = null,
        ?int   $userId = null,
        ?string $transactionDate = null,
    ): ?ItemPriceHistory {

        $settings = PriceAlertSetting::allSettings();

        // Ambil harga terakhir item ini (dari supplier manapun)
        $lastHistory = ItemPriceHistory::where('item_id', $itemId)
            ->whereNotNull('purchase_price')
            ->where('purchase_price', '>', 0)
            ->latest('transaction_date')
            ->latest('id')
            ->first();

        $prevPrice  = $lastHistory ? (float) $lastHistory->purchase_price : 0;
        $avgBefore  = $this->getCurrentAvgPrice($itemId, $warehouseId);

        // Hitung perubahan
        $changePct = 0;
        $severity  = 'normal';

        if ($prevPrice > 0) {
            $changePct = round((($newPrice - $prevPrice) / $prevPrice) * 100, 2);
            $severity  = $this->classifySeverity($changePct, $settings);
        }

        // Hitung avg baru (sederhana — diperbarui oleh StockService juga)
        $currentStock = DB::table('item_stocks')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->value('qty') ?? 0;

        $totalQty   = $currentStock + $qtyReceived;
        $avgAfter   = $totalQty > 0
            ? round(($currentStock * $avgBefore + $qtyReceived * $newPrice) / $totalQty, 2)
            : $newPrice;

        // Simpan ke item_price_history
        $history = ItemPriceHistory::create([
            'item_id'              => $itemId,
            'warehouse_id'         => $warehouseId,
            'purchase_price'       => $newPrice,
            'prev_purchase_price'  => $prevPrice,
            'avg_price_before'     => $avgBefore,
            'avg_price_after'      => $avgAfter,
            'qty_received'         => $qtyReceived,
            'reference_no'         => $referenceNo,
            'source_type'          => 'po',
            'supplier_name'        => $supplierName,
            'supplier_id'          => $supplierId,
            'price_change_pct'     => $changePct,
            'severity'             => $severity,
            'created_by'           => $userId,
            'transaction_date'     => $transactionDate ?? today()->toDateString(),
        ]);

        // Kirim notifikasi jika ada perubahan harga
        if ($prevPrice > 0 && $severity !== 'normal') {
            $this->notifyPurchasing($history);
        }

        // Deteksi anomali
        $this->detectAnomalies($history, $settings, $userId);

        return $history;
    }

    /**
     * Klasifikasi severity berdasarkan % perubahan dan setting threshold.
     */
    public function classifySeverity(float $changePct, array $settings): string
    {
        $thresholdLow      = (float) ($settings['threshold_up_low']      ?? 5);
        $thresholdHigh     = (float) ($settings['threshold_up_high']     ?? 20);
        $thresholdCritical = (float) ($settings['threshold_up_critical'] ?? 50);

        if ($changePct < 0)               return 'down';
        if ($changePct == 0)              return 'normal';
        if ($changePct < $thresholdLow)   return 'normal';
        if ($changePct < $thresholdHigh)  return 'up_low';
        if ($changePct < $thresholdCritical) return 'up_high';
        return 'up_critical';
    }

    /**
     * Kirim notifikasi ke semua user dengan role Purchasing.
     */
    public function notifyPurchasing(ItemPriceHistory $history): void
    {
        $purchasingUsers = User::role('Purchasing')->get();

        foreach ($purchasingUsers as $user) {
            $user->notify(new PriceChangeNotification($history, 'price_change'));
        }
    }

    /**
     * Deteksi berbagai jenis anomali harga.
     */
    public function detectAnomalies(
        ItemPriceHistory $history,
        array $settings,
        ?int $userId
    ): void {
        // 1. Kenaikan berturut-turut
        $this->detectConsecutiveIncrease($history, $settings, $userId);

        // 2. PO vs Tanda Terima (jika ada referensi PO)
        $this->detectPoVsReceive($history, $settings, $userId);
    }

    /**
     * Deteksi kenaikan harga berturut-turut.
     */
    private function detectConsecutiveIncrease(
        ItemPriceHistory $history,
        array $settings,
        ?int $userId
    ): void {
        $minCount = (int) ($settings['consecutive_increase_count'] ?? 3);

        // Ambil N riwayat terakhir item ini
        $recent = ItemPriceHistory::where('item_id', $history->item_id)
            ->where('id', '<=', $history->id)
            ->whereNotNull('price_change_pct')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->take($minCount)
            ->pluck('price_change_pct')
            ->toArray();

        if (count($recent) < $minCount) return;

        // Cek apakah semua N terakhir naik
        $allIncreasing = collect($recent)->every(fn($pct) => (float) $pct > 0);

        if ($allIncreasing) {
            // Pastikan anomali ini belum dicatat baru-baru ini (dalam 7 hari)
            $exists = PriceAnomaly::where('item_id', $history->item_id)
                ->where('anomaly_type', 'consecutive_increase')
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if (!$exists) {
                $anomaly = PriceAnomaly::create([
                    'item_id'      => $history->item_id,
                    'warehouse_id' => $history->warehouse_id,
                    'anomaly_type' => 'consecutive_increase',
                    'severity'     => 'warning',
                    'value_before' => null,
                    'value_after'  => (float) $history->purchase_price,
                    'change_pct'   => array_sum($recent),
                    'reference_no' => $history->reference_no,
                    'supplier_name'=> $history->supplier_name,
                    'meta'         => [
                        'count'          => $minCount,
                        'recent_changes' => $recent,
                    ],
                    'created_by' => $userId,
                ]);

                // Notifikasi
                User::role('Purchasing')->get()->each(function ($user) use ($anomaly, $history) {
                    $user->notify(new PriceChangeNotification(
                        $history,
                        'anomaly',
                        ['anomaly_type' => 'consecutive_increase', 'anomaly_id' => $anomaly->id]
                    ));
                });
            }
        }
    }

    /**
     * Deteksi selisih harga PO vs harga aktual Tanda Terima.
     */
    private function detectPoVsReceive(
        ItemPriceHistory $history,
        array $settings,
        ?int $userId
    ): void {
        if (!$history->reference_no) return;

        $threshold = (float) ($settings['po_vs_receive_threshold'] ?? 5);

        // Cari harga di PO
        $poPrice = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.po_number', $history->reference_no)
            ->where('purchase_order_items.item_id', $history->item_id)
            ->value('purchase_order_items.harga_satuan');

        if (!$poPrice || (float) $poPrice <= 0) return;

        $poPrice    = (float) $poPrice;
        $actualPrice= (float) $history->purchase_price;
        $diffPct    = abs(round((($actualPrice - $poPrice) / $poPrice) * 100, 2));

        if ($diffPct >= $threshold) {
            PriceAnomaly::create([
                'item_id'      => $history->item_id,
                'warehouse_id' => $history->warehouse_id,
                'anomaly_type' => 'po_vs_receive',
                'severity'     => $diffPct >= 20 ? 'critical' : 'warning',
                'value_before' => $poPrice,
                'value_after'  => $actualPrice,
                'change_pct'   => (($actualPrice - $poPrice) / $poPrice) * 100,
                'reference_no' => $history->reference_no,
                'supplier_name'=> $history->supplier_name,
                'meta'         => [
                    'po_price'     => $poPrice,
                    'actual_price' => $actualPrice,
                    'diff_pct'     => $diffPct,
                ],
                'created_by' => $userId,
            ]);

            // Notifikasi
            User::role('Purchasing')->get()->each(function ($user) use ($history, $poPrice, $actualPrice, $diffPct) {
                $user->notify(new PriceChangeNotification(
                    $history,
                    'anomaly',
                    [
                        'anomaly_type'  => 'po_vs_receive',
                        'po_price'      => $poPrice,
                        'actual_price'  => $actualPrice,
                        'diff_pct'      => $diffPct,
                    ]
                ));
            });
        }
    }

    /**
     * Cek budget alert untuk bulan ini.
     * Dipanggil setiap kali ada penerimaan barang.
     */
    public function checkBudgetAlert(?int $userId = null): void
    {
        $settings  = PriceAlertSetting::allSettings();
        $threshold = (float) ($settings['budget_alert_threshold'] ?? 20);
        $months    = (int)   ($settings['budget_alert_months']    ?? 3);

        // Total pembelian bulan ini
        $thisMonthTotal = (float) DB::table('surat_jalan_items')
            ->join('surat_jalan', 'surat_jalan.id', '=', 'surat_jalan_items.surat_jalan_id')
            ->where('surat_jalan.status', 'received')
            ->whereYear('surat_jalan.received_at', now()->year)
            ->whereMonth('surat_jalan.received_at', now()->month)
            ->sum(DB::raw('surat_jalan_items.qty_received * surat_jalan_items.harga_satuan'));

        if ($thisMonthTotal <= 0) return;

        // Rata-rata N bulan sebelumnya
        $avgPrevious = 0;
        $prevTotals  = [];
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

        if (empty($prevTotals)) return;
        $avgPrevious = array_sum($prevTotals) / count($prevTotals);

        if ($avgPrevious <= 0) return;

        $diffPct = round((($thisMonthTotal - $avgPrevious) / $avgPrevious) * 100, 2);

        if ($diffPct >= $threshold) {
            // Cek sudah notif bulan ini belum
            $alreadyNotified = PriceAnomaly::where('anomaly_type', 'budget_exceeded')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->exists();

            if (!$alreadyNotified) {
                PriceAnomaly::create([
                    'item_id'      => 0,
                    'anomaly_type' => 'budget_exceeded',
                    'severity'     => $diffPct >= ($threshold * 2) ? 'critical' : 'warning',
                    'value_before' => $avgPrevious,
                    'value_after'  => $thisMonthTotal,
                    'change_pct'   => $diffPct,
                    'meta'         => [
                        'month'        => now()->format('Y-m'),
                        'avg_months'   => $months,
                        'prev_totals'  => $prevTotals,
                        'threshold'    => $threshold,
                    ],
                    'created_by' => $userId,
                ]);

                User::role('Purchasing')->get()->each(function ($user) use ($thisMonthTotal, $avgPrevious, $diffPct) {
                    $user->notify(new \App\Notifications\BudgetAlertNotification(
                        $thisMonthTotal,
                        $avgPrevious,
                        $diffPct,
                    ));
                });
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function getCurrentAvgPrice(int $itemId, int $warehouseId): float
    {
        return (float) DB::table('item_stocks')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->value('avg_price') ?? 0;
    }
}
