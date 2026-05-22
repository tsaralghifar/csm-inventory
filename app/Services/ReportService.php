<?php

namespace App\Services;

use App\Models\ItemStock;
use App\Models\StockLayer;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Laporan stok per item.
     * Harga = weighted average dari FIFO layers yang masih ada.
     * Fallback ke avg_price ItemStock jika belum ada layer.
     */
    public function stockReport(
        ?int $warehouseId,
        ?int $categoryId = null,
        ?string $filter = null,
    ): array {
        $data = $warehouseId
            ? $this->stockReportSingleWarehouse($warehouseId, $categoryId, $filter)
            : $this->stockReportAllWarehouses($categoryId, $filter);

        $summary = [
            'total_items' => $data->count(),
            'total_value' => $data->sum(function ($s) {
                $harga = $s['fifo_price'] > 0 ? $s['fifo_price'] : $s['avg_price'];
                return max(0, $s['qty']) * $harga;
            }),
            'critical' => $data->filter(
                fn($s) => $s['qty'] >= 0
                    && ($s['item']->min_stock ?? 0) > 0
                    && $s['qty'] <= ($s['item']->min_stock ?? 0)
            )->count(),
            'minus' => $data->filter(fn($s) => $s['qty'] < 0)->count(),
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
            $query->where(fn($q) =>
                $q->where('from_warehouse_id', $warehouseId)
                  ->orWhere('to_warehouse_id', $warehouseId)
            );
        }
        if ($type)     $query->where('type', $type);
        if ($dateFrom) $query->where('movement_date', '>=', $dateFrom);
        if ($dateTo)   $query->where('movement_date', '<=', $dateTo);
        if ($itemId)   $query->where('item_id', $itemId);

        return $query->paginate($perPage);
    }

    /**
     * Laporan pengeluaran barang per gudang dalam rentang tanggal.
     * Data diambil dari BonPengeluaran yang sudah issued, dengan detail
     * per layer FIFO jika tersedia.
     */
    public function pengeluaranReport(int $warehouseId, string $dateFrom, string $dateTo): array
    {
        $bons = \App\Models\BonPengeluaran::with([
                'items.item.category',
                'items.fifoLayers',
            ])
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$dateFrom, $dateTo])
            ->orderBy('issue_date')
            ->orderBy('bon_number')
            ->get();

        $rows    = collect();
        $totalQty   = 0;
        $totalValue = 0;

        foreach ($bons as $bon) {
            foreach ($bon->items as $bonItem) {
                $fifoLayers = $bonItem->fifoLayers;

                if ($fifoLayers->isNotEmpty()) {
                    // Ada detail per layer — pecah per batch
                    foreach ($fifoLayers as $layer) {
                        $nilai = (float) $layer->nilai;
                        $rows->push([
                            'bon_number'    => $bon->bon_number,
                            'bon_id'        => $bon->id,
                            'issue_date'    => $bon->issue_date,
                            'item'          => $bonItem->item,
                            'nama_barang'   => $bonItem->nama_barang,
                            'unit_code'     => $bon->unit_code,
                            'unit_type'     => $bon->unit_type,
                            'hm_km'         => $bon->hm_km,
                            'mechanic'      => $bon->mechanic,
                            'site_name'     => $bon->site_name,
                            'satuan'        => $bonItem->satuan,
                            'qty'           => (float) $layer->qty,
                            'harga_satuan'  => (float) $layer->harga_satuan,
                            'nilai'         => $nilai,
                            'is_fifo'       => true,
                            'is_layer_row'  => true,
                            'tanggal_masuk' => $layer->tanggal_masuk,
                            'source_type'   => $layer->source_type,
                            'reference_po'  => $layer->reference_no,
                            'total_qty_item'=> (float) $bonItem->qty,
                        ]);
                        $totalQty   += (float) $layer->qty;
                        $totalValue += $nilai;
                    }
                } else {
                    // Tidak ada layer detail — tampilkan 1 baris dengan fifo_price
                    $harga = (float) $bonItem->fifo_price > 0
                        ? (float) $bonItem->fifo_price
                        : (float) $bonItem->harga_satuan;
                    $nilai = $harga * (float) $bonItem->qty;
                    $rows->push([
                        'bon_number'    => $bon->bon_number,
                        'bon_id'        => $bon->id,
                        'issue_date'    => $bon->issue_date,
                        'item'          => $bonItem->item,
                        'nama_barang'   => $bonItem->nama_barang,
                        'unit_code'     => $bon->unit_code,
                        'unit_type'     => $bon->unit_type,
                        'hm_km'         => $bon->hm_km,
                        'mechanic'      => $bon->mechanic,
                        'site_name'     => $bon->site_name,
                        'satuan'        => $bonItem->satuan,
                        'qty'           => (float) $bonItem->qty,
                        'harga_satuan'  => $harga,
                        'nilai'         => $nilai,
                        'is_fifo'       => (float) $bonItem->fifo_price > 0,
                        'is_layer_row'  => false,
                        'tanggal_masuk' => null,
                        'source_type'   => null,
                        'reference_po'  => null,
                        'total_qty_item'=> (float) $bonItem->qty,
                    ]);
                    $totalQty   += (float) $bonItem->qty;
                    $totalValue += $nilai;
                }
            }
        }

        $summary = [
            'total_records' => $bons->count(),
            'total_qty'     => $totalQty,
            'total_value'   => $totalValue,
        ];

        $data = $rows;
        return compact('data', 'summary');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Hitung harga FIFO weighted average dari layer aktif untuk satu item di satu gudang.
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
            $query->whereHas('item', fn($q) => $q->whereColumn('item_stocks.qty', '<=', 'items.min_stock')
                ->where('item_stocks.qty', '>=', 0));
        }
        if ($filter === 'minus') {
            $query->where('qty', '<', 0);
        }

        return $query->get()->map(function ($s) use ($warehouseId) {
            $layers = StockLayer::forStock($s->item_id, $warehouseId)
                ->available()
                ->fifo()
                ->get(['id', 'qty_awal', 'qty_sisa', 'harga_satuan', 'tanggal_masuk', 'source_type', 'reference_no'])
                ->map(fn($l) => [
                    'id'            => $l->id,
                    'qty_awal'      => (float) $l->qty_awal,
                    'qty_sisa'      => (float) $l->qty_sisa,
                    'harga_satuan'  => (float) $l->harga_satuan,
                    'tanggal_masuk' => $l->tanggal_masuk?->format('Y-m-d'),
                    'source_type'   => $l->source_type,
                    'reference_no'  => $l->reference_no,
                    'nilai'         => (float) $l->qty_sisa * (float) $l->harga_satuan,
                ]);

            $fifoPrice = $this->getFifoPriceForStock($s->item_id, $warehouseId);
            $avgPrice  = $fifoPrice > 0 ? $fifoPrice : (float) $s->avg_price;

            return [
                'id'         => $s->id,
                'item_id'    => $s->item_id,
                'item'       => $s->item,
                'qty'        => (float) $s->qty,
                'fifo_price' => $fifoPrice,
                'avg_price'  => $avgPrice,
                'layers'     => $layers,
                'gudang'     => [[
                    'id'   => $s->warehouse_id,
                    'name' => $s->warehouse->name,
                    'qty'  => (float) $s->qty,
                ]],
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
                ->map(fn($s) => [
                    'id'   => $s->warehouse_id,
                    'name' => $s->warehouse->name,
                    'qty'  => (float) $s->qty,
                ])->values();

            // Ambil semua layer aktif untuk item ini lintas gudang
            $allLayers = StockLayer::where('item_id', $first->item_id)
                ->available()
                ->fifo()
                ->get(['id', 'warehouse_id', 'qty_awal', 'qty_sisa', 'harga_satuan', 'tanggal_masuk', 'source_type', 'reference_no'])
                ->map(fn($l) => [
                    'id'            => $l->id,
                    'warehouse_id'  => $l->warehouse_id,
                    'qty_awal'      => (float) $l->qty_awal,
                    'qty_sisa'      => (float) $l->qty_sisa,
                    'harga_satuan'  => (float) $l->harga_satuan,
                    'tanggal_masuk' => $l->tanggal_masuk?->format('Y-m-d'),
                    'source_type'   => $l->source_type,
                    'reference_no'  => $l->reference_no,
                    'nilai'         => (float) $l->qty_sisa * (float) $l->harga_satuan,
                ]);

            $fifoPrice = 0.0;
            if ($allLayers->isNotEmpty()) {
                $totalLayerQty   = $allLayers->sum('qty_sisa');
                $totalLayerValue = $allLayers->sum('nilai');
                $fifoPrice = $totalLayerQty > 0 ? round($totalLayerValue / $totalLayerQty, 2) : 0.0;
            }

            $avgPrice = $fifoPrice > 0
                ? $fifoPrice
                : (float) $rows->avg(fn($s) => (float) $s->avg_price);

            return [
                'id'         => $first->id,
                'item_id'    => $first->item_id,
                'item'       => $first->item,
                'qty'        => $totalQty,
                'fifo_price' => $fifoPrice,
                'avg_price'  => $avgPrice,
                'layers'     => $allLayers->values(),
                'gudang'     => $gudang,
            ];
        })->values();

        // Filter setelah grouping
        if ($filter === 'critical') {
            $grouped = $grouped->filter(
                fn($s) => $s['qty'] >= 0
                    && ($s['item']->min_stock ?? 0) > 0
                    && $s['qty'] <= ($s['item']->min_stock ?? 0)
            )->values();
        } elseif ($filter === 'minus') {
            $grouped = $grouped->filter(fn($s) => $s['qty'] < 0)->values();
        }

        return $grouped->sortBy(fn($s) => $s['item']->name)->values();
    }
}