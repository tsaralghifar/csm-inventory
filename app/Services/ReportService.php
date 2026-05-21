<?php

namespace App\Services;

use App\Models\FuelLog;
use App\Models\ItemPriceHistory;
use App\Models\ItemStock;
use App\Models\StockLayer;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Laporan stok per item.
     * Harga yang ditampilkan = weighted average dari FIFO layers yang masih ada.
     * Jika tidak ada layer, fallback ke avg_price ItemStock.
     *
     * @return array{data: Collection, summary: array}
     */
    public function stockReport(
        ?int $warehouseId,
        ?int $categoryId = null,
        ?string $filter = null,
    ): array {
        if ($warehouseId) {
            $data = $this->stockReportSingleWarehouse($warehouseId, $categoryId, $filter);
        } else {
            $data = $this->stockReportAllWarehouses($categoryId, $filter);
        }

        $summary = [
            'total_items' => $data->count(),
            'total_value' => $data->sum(fn($s) => max(0, data_get($s, 'qty', 0)) * data_get($s, 'fifo_price', data_get($s, 'avg_price', 0))),
            'critical'    => $data->filter(
                fn($s) => data_get($s, 'qty', 0) >= 0
                    && data_get($s, 'qty', 0) <= (data_get($s, 'item.min_stock') ?? 0)
                    && (data_get($s, 'item.min_stock') ?? 0) > 0
            )->count(),
            'minus'       => $data->filter(fn($s) => data_get($s, 'qty', 0) < 0)->count(),
        ];

        return compact('data', 'summary');
    }

    /**
     * Laporan mutasi stok dengan filter opsional.
     */
    public function movementReport(
        ?int $warehouseId,
        ?string $type,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $itemId,
        int $perPage = 50,
    ) {
        $query = StockMovement::with(['item.category', 'fromWarehouse', 'toWarehouse', 'creator'])
            ->orderBy('movement_date', 'desc');

        if ($warehouseId) {
            $query->where(
                fn($q) => $q->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)
            );
        }
        if ($type)     $query->where('type', $type);
        if ($dateFrom) $query->where('movement_date', '>=', $dateFrom);
        if ($dateTo)   $query->where('movement_date', '<=', $dateTo);
        if ($itemId)   $query->where('item_id', $itemId);

        return $query->paginate($perPage);
    }

    /**
     * Laporan pengeluaran barang (out + transfer_out) per gudang dalam rentang tanggal.
     * Nilai dihitung dari fifo_price jika tersedia, fallback ke price (avg).
     *
     * @return array{data: Collection, summary: array}
     */
    public function pengeluaranReport(int $warehouseId, string $dateFrom, string $dateTo): array
    {
        $data = StockMovement::with(['item.category', 'fromWarehouse'])
            ->whereIn('type', ['out', 'transfer_out'])
            ->where('from_warehouse_id', $warehouseId)
            ->whereBetween('movement_date', [$dateFrom, $dateTo])
            ->orderBy('movement_date')
            ->get()
            ->map(function ($m) {
                // Gunakan fifo_price jika tersedia, fallback ke price
                $harga = (float) $m->fifo_price > 0
                    ? (float) $m->fifo_price
                    : (float) $m->price;

                $m->harga_display   = $harga;
                $m->subtotal_fifo   = $harga * (float) $m->qty;
                $m->is_fifo         = (float) $m->fifo_price > 0;
                return $m;
            });

        $summary = [
            'total_records' => $data->count(),
            'total_qty'     => $data->sum('qty'),
            'total_value'   => $data->sum('subtotal_fifo'),
        ];

        return compact('data', 'summary');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Hitung harga FIFO weighted average dari layer yang masih ada untuk satu item di satu gudang.
     * Digunakan untuk menampilkan "nilai stok saat ini" di laporan.
     */
    private function getFifoPriceForStock(int $itemId, int $warehouseId): float
    {
        $layers = StockLayer::forStock($itemId, $warehouseId)
            ->available()
            ->get(['qty_sisa', 'harga_satuan']);

        if ($layers->isEmpty()) return 0.0;

        $totalQty   = $layers->sum(fn($l) => (float) $l->qty_sisa);
        $totalValue = $layers->sum(fn($l) => (float) $l->qty_sisa * (float) $l->harga_satuan);

        return $totalQty > 0 ? round($totalValue / $totalQty, 2) : 0.0;
    }

    private function stockReportSingleWarehouse(int $warehouseId, ?int $categoryId, ?string $filter): Collection
    {
        $query = ItemStock::with(['item.category', 'warehouse'])
            ->where('warehouse_id', $warehouseId);

        if ($categoryId) {
            $query->whereHas('item', fn($q) => $q->where('category_id', $categoryId));
        }
        if ($filter === 'critical') {
            $query->whereHas('item', fn($q) => $q->whereColumn('item_stocks.qty', '<=', 'items.min_stock'));
        }
        if ($filter === 'minus') {
            $query->where('qty', '<', 0);
        }

        $rawStocks = $query->get();

        return $rawStocks->map(function ($s) use ($warehouseId) {
            $fifoPrice = $this->getFifoPriceForStock($s->item_id, $warehouseId);
            $avgPrice  = $fifoPrice > 0 ? $fifoPrice : (float) $s->avg_price;

            return [
                'id'         => $s->id,
                'item_id'    => $s->item_id,
                'item'       => $s->item,
                'qty'        => (float) $s->qty,
                'fifo_price' => $fifoPrice,
                'avg_price'  => $avgPrice,
                'gudang'     => [['id' => $s->warehouse_id, 'name' => $s->warehouse->name, 'qty' => (float) $s->qty]],
            ];
        });
    }

    private function stockReportAllWarehouses(?int $categoryId, ?string $filter): Collection
    {
        $query = ItemStock::with(['item.category', 'warehouse']);

        if ($categoryId) {
            $query->whereHas('item', fn($q) => $q->where('category_id', $categoryId));
        }

        $allStocks = $query->get();

        $grouped = $allStocks->groupBy('item_id')->map(function ($rows) {
            $first    = $rows->first();
            $totalQty = $rows->sum(fn($s) => (float) $s->qty);
            $gudang   = $rows->filter(fn($s) => $s->qty != 0)
                ->map(fn($s) => ['id' => $s->warehouse_id, 'name' => $s->warehouse->name, 'qty' => (float) $s->qty])
                ->values();

            // Hitung FIFO weighted avg lintas semua gudang untuk item ini
            $allLayers = StockLayer::where('item_id', $first->item_id)
                ->available()
                ->get(['qty_sisa', 'harga_satuan']);

            $fifoPrice = 0.0;
            if ($allLayers->isNotEmpty()) {
                $totalLayerQty   = $allLayers->sum(fn($l) => (float) $l->qty_sisa);
                $totalLayerValue = $allLayers->sum(fn($l) => (float) $l->qty_sisa * (float) $l->harga_satuan);
                $fifoPrice = $totalLayerQty > 0 ? round($totalLayerValue / $totalLayerQty, 2) : 0.0;
            }

            $avgPrice = $fifoPrice > 0
                ? $fifoPrice
                : $rows->avg(fn($s) => (float) $s->avg_price);

            return [
                'id'         => $first->id,
                'item_id'    => $first->item_id,
                'item'       => $first->item,
                'qty'        => $totalQty,
                'fifo_price' => $fifoPrice,
                'avg_price'  => $avgPrice,
                'gudang'     => $gudang,
            ];
        })->values();

        // Filter setelah grouping (qty sudah dijumlahkan)
        if ($filter === 'critical') {
            $grouped = $grouped->filter(
                fn($s) => $s['qty'] >= 0
                    && $s['qty'] <= ($s['item']->min_stock ?? 0)
                    && ($s['item']->min_stock ?? 0) > 0
            )->values();
        } elseif ($filter === 'minus') {
            $grouped = $grouped->filter(fn($s) => $s['qty'] < 0)->values();
        }

        return $grouped->sortBy(fn($s) => $s['item']->name)->values();
    }
}

    /**
     * Laporan stok per item.
     *
     * Jika warehouse_id diberikan → satu baris per item di gudang tersebut.
     * Jika tidak → gabungkan semua gudang, jumlahkan qty per item.
     *
     * @return array{data: Collection, summary: array}
     */
    public function stockReport(
        ?int $warehouseId,
        ?int $categoryId = null,
        ?string $filter = null,
    ): array {
        if ($warehouseId) {
            $data = $this->stockReportSingleWarehouse($warehouseId, $categoryId, $filter);
        } else {
            $data = $this->stockReportAllWarehouses($categoryId, $filter);
        }

        $summary = [
            'total_items' => $data->count(),
            'total_value' => $data->sum(fn($s) => max(0, data_get($s, 'qty', 0)) * data_get($s, 'avg_price', 0)),
            'critical'    => $data->filter(
                fn($s) => data_get($s, 'qty', 0) >= 0
                    && data_get($s, 'qty', 0) <= (data_get($s, 'item.min_stock') ?? 0)
                    && (data_get($s, 'item.min_stock') ?? 0) > 0
            )->count(),
            'minus'       => $data->filter(fn($s) => data_get($s, 'qty', 0) < 0)->count(),
        ];

        return compact('data', 'summary');
    }

    /**
     * Laporan mutasi stok dengan filter opsional.
     */
    public function movementReport(
        ?int $warehouseId,
        ?string $type,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $itemId,
        int $perPage = 50,
    ) {
        $query = StockMovement::with(['item.category', 'fromWarehouse', 'toWarehouse', 'creator'])
            ->orderBy('movement_date', 'desc');

        if ($warehouseId) {
            $query->where(
                fn($q) => $q->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)
            );
        }
        if ($type)     $query->where('type', $type);
        if ($dateFrom) $query->where('movement_date', '>=', $dateFrom);
        if ($dateTo)   $query->where('movement_date', '<=', $dateTo);
        if ($itemId)   $query->where('item_id', $itemId);

        return $query->paginate($perPage);
    }

    /**
     * Laporan pengeluaran barang (out + transfer_out) per gudang dalam rentang tanggal.
     *
     * @return array{data: Collection, summary: array}
     */
    public function pengeluaranReport(int $warehouseId, string $dateFrom, string $dateTo): array
    {
        $data = StockMovement::with(['item.category', 'fromWarehouse'])
            ->whereIn('type', ['out', 'transfer_out'])
            ->where('from_warehouse_id', $warehouseId)
            ->whereBetween('movement_date', [$dateFrom, $dateTo])
            ->orderBy('movement_date')
            ->get();

        $summary = [
            'total_records' => $data->count(),
            'total_qty'     => $data->sum('qty'),
            'total_value'   => $data->sum(fn($m) => $m->qty * $m->price),
        ];

        return compact('data', 'summary');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function stockReportSingleWarehouse(int $warehouseId, ?int $categoryId, ?string $filter): Collection
    {
        $query = ItemStock::with(['item.category', 'warehouse'])
            ->where('warehouse_id', $warehouseId);

        if ($categoryId) {
            $query->whereHas('item', fn($q) => $q->where('category_id', $categoryId));
        }
        if ($filter === 'critical') {
            $query->whereHas('item', fn($q) => $q->whereColumn('item_stocks.qty', '<=', 'items.min_stock'));
        }
        if ($filter === 'minus') {
            $query->where('qty', '<', 0);
        }

        $rawStocks = $query->get();

        $historyAvg = ItemPriceHistory::whereIn('item_id', $rawStocks->pluck('item_id'))
            ->where('warehouse_id', $warehouseId)
            ->select('item_id', DB::raw('AVG(purchase_price) as simple_avg'))
            ->groupBy('item_id')
            ->pluck('simple_avg', 'item_id');

        return $rawStocks->map(fn($s) => [
            'id'        => $s->id,
            'item_id'   => $s->item_id,
            'item'      => $s->item,
            'qty'       => (float) $s->qty,
            'avg_price' => $historyAvg->has($s->item_id)
                ? round((float) $historyAvg[$s->item_id], 2)
                : (float) $s->avg_price,
            'gudang'    => [['id' => $s->warehouse_id, 'name' => $s->warehouse->name, 'qty' => (float) $s->qty]],
        ]);
    }

    private function stockReportAllWarehouses(?int $categoryId, ?string $filter): Collection
    {
        $query = ItemStock::with(['item.category', 'warehouse']);

        if ($categoryId) {
            $query->whereHas('item', fn($q) => $q->where('category_id', $categoryId));
        }

        $allStocks = $query->get();

        $historyAvg = ItemPriceHistory::whereIn('item_id', $allStocks->pluck('item_id')->unique())
            ->select('item_id', DB::raw('AVG(purchase_price) as simple_avg'))
            ->groupBy('item_id')
            ->pluck('simple_avg', 'item_id');

        $grouped = $allStocks->groupBy('item_id')->map(function ($rows) use ($historyAvg) {
            $first    = $rows->first();
            $totalQty = $rows->sum(fn($s) => (float) $s->qty);
            $gudang   = $rows->filter(fn($s) => $s->qty != 0)
                ->map(fn($s) => ['id' => $s->warehouse_id, 'name' => $s->warehouse->name, 'qty' => (float) $s->qty])
                ->values();

            $avgPrice = $historyAvg->has($first->item_id)
                ? round((float) $historyAvg[$first->item_id], 2)
                : $rows->avg(fn($s) => (float) $s->avg_price);

            return [
                'id'        => $first->id,
                'item_id'   => $first->item_id,
                'item'      => $first->item,
                'qty'       => $totalQty,
                'avg_price' => $avgPrice,
                'gudang'    => $gudang,
            ];
        })->values();

        // Filter setelah grouping (qty sudah dijumlahkan)
        if ($filter === 'critical') {
            $grouped = $grouped->filter(
                fn($s) => $s['qty'] >= 0
                    && $s['qty'] <= ($s['item']->min_stock ?? 0)
                    && ($s['item']->min_stock ?? 0) > 0
            )->values();
        } elseif ($filter === 'minus') {
            $grouped = $grouped->filter(fn($s) => $s['qty'] < 0)->values();
        }

        return $grouped->sortBy(fn($s) => $s['item']->name)->values();
    }
}