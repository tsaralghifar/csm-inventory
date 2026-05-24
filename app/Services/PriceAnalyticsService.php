<?php

namespace App\Services;

use App\Models\ItemPriceHistory;
use App\Models\PriceAlertSetting;
use App\Models\PriceAnomaly;
use Illuminate\Support\Facades\DB;

class PriceAnalyticsService
{
    // ─── Dashboard Summary ────────────────────────────────────────────────────

    /**
     * Ringkasan untuk dashboard analitik harga.
     */
    public function getDashboardSummary(): array
    {
        $today     = now()->toDateString();
        $thisMonth = now()->format('Y-m');
        $last30    = now()->subDays(30)->toDateString();

        // Total perubahan harga 30 hari terakhir
        $totalChanges = ItemPriceHistory::where('transaction_date', '>=', $last30)
            ->whereNotNull('price_change_pct')
            ->where('price_change_pct', '!=', 0)
            ->count();

        // Breakdown severity
        $bySeverity = ItemPriceHistory::where('transaction_date', '>=', $last30)
            ->whereNotNull('severity')
            ->where('severity', '!=', 'normal')
            ->selectRaw('severity, count(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->toArray();

        // Anomali belum dibaca
        $unreadAnomalies = PriceAnomaly::unread()->count();
        $criticalAnomalies = PriceAnomaly::unread()->critical()->count();

        // Top 5 kenaikan harga bulan ini
        $topIncreases = ItemPriceHistory::with('item')
            ->whereRaw("TO_CHAR(transaction_date, 'YYYY-MM') = ?", [$thisMonth])
            ->where('price_change_pct', '>', 0)
            ->orderByDesc('price_change_pct')
            ->take(5)
            ->get()
            ->map(fn($h) => [
                'item_id'      => $h->item_id,
                'item_name'    => $h->item->name ?? '-',
                'part_number'  => $h->item->part_number ?? '-',
                'prev_price'   => (float) $h->prev_purchase_price,
                'new_price'    => (float) $h->purchase_price,
                'change_pct'   => (float) $h->price_change_pct,
                'severity'     => $h->severity,
                'supplier'     => $h->supplier_name,
                'date'         => $h->transaction_date?->format('Y-m-d'),
            ]);

        // Top 5 penurunan harga bulan ini
        $topDecreases = ItemPriceHistory::with('item')
            ->whereRaw("TO_CHAR(transaction_date, 'YYYY-MM') = ?", [$thisMonth])
            ->where('price_change_pct', '<', 0)
            ->orderBy('price_change_pct')
            ->take(5)
            ->get()
            ->map(fn($h) => [
                'item_id'      => $h->item_id,
                'item_name'    => $h->item->name ?? '-',
                'part_number'  => $h->item->part_number ?? '-',
                'prev_price'   => (float) $h->prev_purchase_price,
                'new_price'    => (float) $h->purchase_price,
                'change_pct'   => (float) $h->price_change_pct,
                'supplier'     => $h->supplier_name,
                'date'         => $h->transaction_date?->format('Y-m-d'),
            ]);

        // Anomali terbaru
        $recentAnomalies = PriceAnomaly::with('item')
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn($a) => [
                'id'           => $a->id,
                'type'         => $a->anomaly_type,
                'type_label'   => $a->getTypeLabel(),
                'severity'     => $a->severity,
                'severity_label'=> $a->getSeverityLabel(),
                'item_name'    => $a->item->name ?? 'N/A',
                'part_number'  => $a->item->part_number ?? '-',
                'change_pct'   => (float) $a->change_pct,
                'value_before' => (float) $a->value_before,
                'value_after'  => (float) $a->value_after,
                'reference_no' => $a->reference_no,
                'supplier'     => $a->supplier_name,
                'is_read'      => $a->is_read,
                'created_at'   => $a->created_at?->format('Y-m-d H:i'),
            ]);

        return [
            'total_changes'      => $totalChanges,
            'by_severity'        => $bySeverity,
            'unread_anomalies'   => $unreadAnomalies,
            'critical_anomalies' => $criticalAnomalies,
            'top_increases'      => $topIncreases,
            'top_decreases'      => $topDecreases,
            'recent_anomalies'   => $recentAnomalies,
        ];
    }

    // ─── Tren Harga per Item ──────────────────────────────────────────────────

    /**
     * Data tren harga untuk satu item dalam rentang periode.
     */
    public function getPriceTrend(int $itemId, string $dateFrom, string $dateTo): array
    {
        $histories = ItemPriceHistory::with('warehouse')
            ->where('item_id', $itemId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $chartData = $histories->map(fn($h) => [
            'date'          => $h->transaction_date?->format('Y-m-d'),
            'price'         => (float) $h->purchase_price,
            'avg_price'     => (float) $h->avg_price_after,
            'qty'           => (float) $h->qty_received,
            'supplier'      => $h->supplier_name ?? '-',
            'reference_no'  => $h->reference_no,
            'severity'      => $h->severity,
            'change_pct'    => (float) $h->price_change_pct,
        ]);

        // Statistik
        $prices = $histories->pluck('purchase_price')->map(fn($p) => (float) $p);

        return [
            'chart'   => $chartData,
            'stats'   => [
                'min'     => $prices->min() ?? 0,
                'max'     => $prices->max() ?? 0,
                'avg'     => $prices->isNotEmpty() ? round($prices->avg(), 2) : 0,
                'latest'  => $prices->last() ?? 0,
                'count'   => $prices->count(),
            ],
        ];
    }

    // ─── Perbandingan Harga Antar Supplier ────────────────────────────────────

    /**
     * Perbandingan harga item dari berbagai supplier.
     */
    public function getSupplierComparison(int $itemId): array
    {
        // Ambil semua riwayat per supplier untuk item ini
        $allBySupplier = ItemPriceHistory::where('item_id', $itemId)
            ->whereNotNull('supplier_name')
            ->where('supplier_name', '!=', '')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->groupBy('supplier_name')
            ->map(fn($rows, $supplierName) => [
                'supplier'    => $supplierName,
                'data'        => $rows->map(fn($h) => [
                    'date'  => $h->transaction_date?->format('Y-m-d'),
                    'price' => (float) $h->purchase_price,
                    'ref'   => $h->reference_no,
                ])->values(),
                'latest_price'=> (float) $rows->last()->purchase_price,
                'last_date'   => $rows->last()->transaction_date?->format('Y-m-d'),
                'total_orders'=> $rows->count(),
                'avg_price'   => round($rows->avg(fn($h) => (float) $h->purchase_price), 2),
                'min_price'   => (float) $rows->min(fn($h) => (float) $h->purchase_price),
                'max_price'   => (float) $rows->max(fn($h) => (float) $h->purchase_price),
            ])
            ->values();

        // Jika tidak ada data supplier — kembalikan empty
        if ($allBySupplier->isEmpty()) {
            return ['suppliers' => [], 'cheapest' => null];
        }

        // Tandai yang termurah
        $cheapest = $allBySupplier->sortBy('latest_price')->first()['supplier'] ?? null;

        return [
            'suppliers' => $allBySupplier->map(fn($s) => array_merge($s, [
                'is_cheapest' => $s['supplier'] === $cheapest,
            ]))->values(),
            'cheapest'  => $cheapest,
        ];
    }

    // ─── Budget Monitor ───────────────────────────────────────────────────────

    /**
     * Data total pembelian per bulan untuk grafik budget monitor.
     */
    public function getBudgetMonitor(int $months = 12): array
    {
        $settings  = PriceAlertSetting::allSettings();
        $threshold = (float) ($settings['budget_alert_threshold'] ?? 20);
        $avgMonths = (int)   ($settings['budget_alert_months']    ?? 3);

        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date  = now()->subMonths($i);
            $label = $date->format('M Y');
            $ym    = $date->format('Y-m');

            $total = (float) DB::table('surat_jalan_items')
                ->join('surat_jalan', 'surat_jalan.id', '=', 'surat_jalan_items.surat_jalan_id')
                ->where('surat_jalan.status', 'received')
                ->whereYear('surat_jalan.received_date', $date->year)
                ->whereMonth('surat_jalan.received_date', $date->month)
                ->sum(DB::raw('surat_jalan_items.qty_received * surat_jalan_items.harga_satuan'));

            $data[] = [
                'label'  => $label,
                'ym'     => $ym,
                'total'  => $total,
                'month'  => $date->month,
                'year'   => $date->year,
            ];
        }

        // Hitung rata-rata N bulan sebelumnya untuk setiap titik
        $dataCollection = collect($data);
        $dataWithAvg = $dataCollection->map(function ($point, $idx) use ($dataCollection, $avgMonths) {
            $prevItems = $dataCollection->take($idx)->take(-$avgMonths);
            $avg = $prevItems->isNotEmpty()
                ? round($prevItems->avg('total'), 2)
                : 0;
            return array_merge($point, ['avg_prev' => $avg]);
        });

        // Hitung summary
        $thisMonth = $data[count($data) - 1];
        $prevData  = array_slice($data, -($avgMonths + 1), $avgMonths);
        $avgPrev   = count($prevData) > 0
            ? array_sum(array_column($prevData, 'total')) / count($prevData)
            : 0;
        $diffPct = $avgPrev > 0
            ? round((($thisMonth['total'] - $avgPrev) / $avgPrev) * 100, 2)
            : 0;

        return [
            'chart'     => $dataWithAvg->values(),
            'summary'   => [
                'this_month'   => $thisMonth['total'],
                'avg_previous' => round($avgPrev, 2),
                'diff_pct'     => $diffPct,
                'threshold'    => $threshold,
                'avg_months'   => $avgMonths,
                'is_exceeded'  => $diffPct >= $threshold,
            ],
        ];
    }

    // ─── Price History List ───────────────────────────────────────────────────

    /**
     * List riwayat harga dengan filter.
     */
    public function getPriceHistoryList(array $filters, int $perPage = 30)
    {
        $query = ItemPriceHistory::with(['item.category', 'warehouse'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if (!empty($filters['item_id']))    $query->where('item_id', $filters['item_id']);
        if (!empty($filters['supplier']))   $query->where('supplier_name', 'ilike', '%' . $filters['supplier'] . '%');
        if (!empty($filters['severity']))   $query->where('severity', $filters['severity']);
        if (!empty($filters['date_from']))  $query->where('transaction_date', '>=', $filters['date_from']);
        if (!empty($filters['date_to']))    $query->where('transaction_date', '<=', $filters['date_to']);
        if (!empty($filters['warehouse_id'])) $query->where('warehouse_id', $filters['warehouse_id']);

        // Hanya yang ada perubahan harga
        if (!empty($filters['changes_only'])) {
            $query->whereNotNull('price_change_pct')->where('price_change_pct', '!=', 0);
        }

        return $query->paginate($perPage);
    }

    // ─── Anomaly List ─────────────────────────────────────────────────────────

    /**
     * List anomali dengan filter.
     */
    public function getAnomalyList(array $filters, int $perPage = 20)
    {
        $query = PriceAnomaly::with('item')
            ->orderByDesc('created_at');

        if (!empty($filters['type']))     $query->where('anomaly_type', $filters['type']);
        if (!empty($filters['severity'])) $query->where('severity', $filters['severity']);
        if (!empty($filters['unread']))   $query->unread();
        if (!empty($filters['item_id']))  $query->where('item_id', $filters['item_id']);

        return $query->paginate($perPage);
    }

    /**
     * Tandai anomali sebagai sudah dibaca.
     */
    public function markAnomalyRead(int $anomalyId): void
    {
        PriceAnomaly::where('id', $anomalyId)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function markAllAnomaliesRead(): void
    {
        PriceAnomaly::unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}