<?php

namespace App\Services;

use App\Models\BonPengeluaran;
use App\Models\ItemStock;
use App\Models\PurchaseOrder;
use App\Models\StockLayer;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportService
{
    // ═══════════════════════════════════════════════════════════════════════════
    //  STOK
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Laporan stok per item.
     * Harga dihitung dari weighted-average FIFO layers yang masih aktif.
     */
    public function stockReport(
        ?int    $warehouseId,
        ?int    $categoryId = null,
        ?string $filter     = null,
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

    // ═══════════════════════════════════════════════════════════════════════════
    //  MUTASI
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Laporan mutasi stok dengan filter opsional.
     */
    public function movementReport(
        ?int    $warehouseId,
        ?string $type,
        ?string $dateFrom,
        ?string $dateTo,
        ?int    $itemId,
        ?string $moveableType = null,
        int     $perPage = 50,
    ): LengthAwarePaginator {
        $query = StockMovement::with(['item.category', 'fromWarehouse', 'toWarehouse', 'creator'])
            ->orderBy('movement_date', 'desc');

        if ($warehouseId) {
            $query->where(fn($q) =>
                $q->where('from_warehouse_id', $warehouseId)
                  ->orWhere('to_warehouse_id', $warehouseId)
            );
        }

        // Filter di DB — lebih efisien dari ->get()->filter()
        if ($type)         $query->where('type', $type);
        if ($dateFrom)     $query->where('movement_date', '>=', $dateFrom);
        if ($dateTo)       $query->where('movement_date', '<=', $dateTo);
        if ($itemId)       $query->where('item_id', $itemId);

        // Filter moveable_type — dipakai untuk isolasi mutasi APD dari pengeluaran bon biasa
        if ($moveableType) $query->where('moveable_type', $moveableType);

        return $query->paginate($perPage);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  PENGELUARAN
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Laporan pengeluaran barang per gudang dalam rentang tanggal.
     * Detail per FIFO layer jika tersedia.
     */
    public function pengeluaranReport(
        int    $warehouseId,
        string $dateFrom,
        string $dateTo,
    ): array {
        $bons = BonPengeluaran::with([
                'items.item.category',
                'items.fifoLayers',
            ])
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$dateFrom, $dateTo])
            ->orderBy('issue_date')
            ->orderBy('bon_number')
            ->get();

        $rows       = collect();
        $totalQty   = 0;
        $totalValue = 0;

        foreach ($bons as $bon) {
            foreach ($bon->items as $bonItem) {
                $fifoLayers = $bonItem->fifoLayers;

                if ($fifoLayers->isNotEmpty()) {
                    foreach ($fifoLayers as $layer) {
                        $nilai = (float) $layer->nilai;
                        $rows->push($this->buildPengeluaranRow($bon, $bonItem, [
                            'qty'           => (float) $layer->qty,
                            'harga_satuan'  => (float) $layer->harga_satuan,
                            'nilai'         => $nilai,
                            'is_fifo'       => true,
                            'is_layer_row'  => true,
                            'tanggal_masuk' => $layer->tanggal_masuk,
                            'source_type'   => $layer->source_type,
                            'reference_po'  => $layer->reference_no,
                            'total_qty_item'=> (float) $bonItem->qty,
                        ]));
                        $totalQty   += (float) $layer->qty;
                        $totalValue += $nilai;
                    }
                } else {
                    $harga = (float) $bonItem->fifo_price > 0
                        ? (float) $bonItem->fifo_price
                        : (float) $bonItem->harga_satuan;
                    $nilai = $harga * (float) $bonItem->qty;
                    $rows->push($this->buildPengeluaranRow($bon, $bonItem, [
                        'qty'           => (float) $bonItem->qty,
                        'harga_satuan'  => $harga,
                        'nilai'         => $nilai,
                        'is_fifo'       => (float) $bonItem->fifo_price > 0,
                        'is_layer_row'  => false,
                        'tanggal_masuk' => null,
                        'source_type'   => null,
                        'reference_po'  => null,
                        'total_qty_item'=> (float) $bonItem->qty,
                    ]));
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

    // ═══════════════════════════════════════════════════════════════════════════
    //  PEMBELIAN
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Laporan pembelian barang dari Purchase Order.
     * Mendukung filter: tanggal, payment_type (cash/kredit), status PO, supplier.
     *
     * Semua filter diterapkan di DB (bukan di Collection) untuk efisiensi memori.
     */
    public function purchaseReport(
        ?string $dateFrom    = null,
        ?string $dateTo      = null,
        ?string $paymentType = null,
        ?string $status      = null,
        ?int    $supplierId  = null,
    ): array {
        $query = PurchaseOrder::with([
                'items.item.category',
                'warehouse',
                'supplier',
                'creator',
                'supplierInvoices',
            ])
            ->withCount('items');

        // ── Filter di DB level (bukan setelah ->get()) ────────────────────────
        if ($dateFrom) $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $query->whereDate('created_at', '<=', $dateTo);

        if ($paymentType && in_array($paymentType, ['cash', 'kredit'], true)) {
            $query->where('payment_type', $paymentType);
        }
        if ($status)     $query->where('status', $status);
        if ($supplierId) $query->where('supplier_id', $supplierId);

        $orders = $query->orderByDesc('created_at')->get();

        // ── Summary — dihitung dari hasil query yang sudah terfilter ──────────
        // Agregasi ringan di Collection karena data sudah terfilter di DB
        $cashOrders   = $orders->where('payment_type', 'cash');
        $kreditOrders = $orders->where('payment_type', 'kredit');

        $summary = [
            'total_po'     => $orders->count(),
            'total_nilai'  => $orders->sum(fn($o) => (float) $o->grand_total),
            'total_cash'   => $cashOrders->count(),
            'nilai_cash'   => $cashOrders->sum(fn($o) => (float) $o->grand_total),
            'total_kredit' => $kreditOrders->count(),
            'nilai_kredit' => $kreditOrders->sum(fn($o) => (float) $o->grand_total),
            'total_ppn'    => $orders->sum(fn($o) => (float) $o->ppn_amount),
            'total_diskon' => $orders->sum(fn($o) => (float) $o->diskon_amount),
        ];

        // ── Serialize ─────────────────────────────────────────────────────────

        $today = now()->toDateString();

        $data = $orders->map(fn($po) => [
            'id'                => $po->id,
            'po_number'         => $po->po_number,
            'vendor_name'       => $po->vendor_name,
            'vendor_contact'    => $po->vendor_contact,
            'supplier'          => $po->supplier
                ? ['id' => $po->supplier->id, 'name' => $po->supplier->name]
                : null,
            'warehouse'         => $po->warehouse
                ? ['id' => $po->warehouse->id, 'name' => $po->warehouse->name]
                : null,
            'created_at'        => $po->created_at?->toDateTimeString(),
            'expected_date'     => $po->expected_date?->toDateString(),
            'status'            => $po->status,
            'payment_type'      => $po->payment_type,
            'payment_term_days' => $po->payment_term_days,
            'payment_due_date'  => $po->payment_due_date?->toDateString(),
            'total_amount'      => (float) $po->total_amount,
            'diskon_persen'     => (float) $po->diskon_persen,
            'diskon_amount'     => (float) $po->diskon_amount,
            'ppn_percent'       => (float) $po->ppn_percent,
            'ppn_amount'        => (float) $po->ppn_amount,
            'grand_total'       => (float) $po->grand_total,
            'notes'             => $po->notes,
            'items_count'       => $po->items_count,
            'items'             => $po->items->map(fn($item) => [
                'id'            => $item->id,
                'part_number'   => $item->part_number,
                'nama_barang'   => $item->nama_barang,
                'kode_unit'     => $item->kode_unit,
                'tipe_unit'     => $item->tipe_unit,
                'qty'           => (float) $item->qty,
                'qty_received'  => (float) $item->qty_received,
                'satuan'        => $item->satuan,
                'harga_satuan'  => (float) $item->harga_satuan,
                'diskon_persen' => (float) $item->diskon_persen,
                'diskon_amount' => (float) $item->diskon_amount,
                'total_harga'   => (float) $item->total_harga,
                'keterangan'    => $item->keterangan,
                'item'          => $item->item ? [
                    'id'       => $item->item->id,
                    'name'     => $item->item->name,
                    'category' => $item->item->category?->name,
                ] : null,
            ])->values(),
            'supplier_invoices' => $po->supplierInvoices->map(fn($inv) => [
                'id'               => $inv->id,
                'invoice_number'   => $inv->invoice_number,
                'invoice_date'     => $inv->invoice_date?->toDateString(),
                'due_date'         => $inv->due_date?->toDateString(),
                'total_amount'     => (float) $inv->total_amount,
                'paid_amount'      => (float) $inv->paid_amount,
                'remaining_amount' => (float) $inv->remaining_amount,
                'status'           => $inv->status,
                'is_overdue'       => $inv->due_date?->toDateString() < $today
                                        && $inv->status !== 'paid',
            ])->values(),
        ])->values();

        return compact('data', 'summary');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Hitung harga FIFO weighted-average dari layer aktif untuk 1 item / 1 gudang.
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

        // Filter stok kritis dan minus langsung di DB ──────────────────────────
        if ($filter === 'critical') {
            $query->join('items', 'item_stocks.item_id', '=', 'items.id')
                ->whereColumn('item_stocks.qty', '<=', 'items.min_stock')
                ->where('item_stocks.qty', '>=', 0)
                ->where('items.min_stock', '>', 0)
                ->select('item_stocks.*');
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

        // Filter minus langsung di DB sebelum grouping ─────────────────────────
        if ($filter === 'minus') {
            $query->where('qty', '<', 0);
        }

        $grouped = $query->get()->groupBy('item_id')->map(function ($rows) {
            $first    = $rows->first();
            $totalQty = $rows->sum(fn($s) => (float) $s->qty);
            $gudang   = $rows->filter(fn($s) => $s->qty != 0)
                ->map(fn($s) => [
                    'id'   => $s->warehouse_id,
                    'name' => $s->warehouse->name,
                    'qty'  => (float) $s->qty,
                ])->values();

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

            return [
                'id'         => $first->id,
                'item_id'    => $first->item_id,
                'item'       => $first->item,
                'qty'        => $totalQty,
                'fifo_price' => $fifoPrice,
                'avg_price'  => $fifoPrice > 0
                    ? $fifoPrice
                    : (float) $rows->avg(fn($s) => (float) $s->avg_price),
                'layers'     => $allLayers->values(),
                'gudang'     => $gudang,
            ];
        })->values();

        // Filter critical setelah grouping (perlu totalQty lintas gudang)
        // Filter minus sudah dikerjakan di DB di atas, cukup skip di sini.
        if ($filter === 'critical') {
            $grouped = $grouped->filter(
                fn($s) => $s['qty'] >= 0
                    && ($s['item']->min_stock ?? 0) > 0
                    && $s['qty'] <= ($s['item']->min_stock ?? 0)
            )->values();
        }

        return $grouped->sortBy(fn($s) => $s['item']->name)->values();
    }

    /**
     * Builder baris pengeluaran — menghindari duplikasi properti bon & bonItem.
     */
    private function buildPengeluaranRow(
        BonPengeluaran $bon,
        mixed          $bonItem,
        array          $extra,
    ): array {
        return array_merge([
            'bon_number'  => $bon->bon_number,
            'bon_id'      => $bon->id,
            'issue_date'  => $bon->issue_date,
            'item'        => $bonItem->item,
            'nama_barang' => $bonItem->nama_barang,
            'unit_code'   => $bon->unit_code,
            'unit_type'   => $bon->unit_type,
            'hm_km'       => $bon->hm_km,
            'mechanic'    => $bon->mechanic,
            'site_name'   => $bon->site_name,
            'satuan'      => $bonItem->satuan,
        ], $extra);
    }
}