<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemStock;
use App\Models\ItemPriceHistory;
use App\Models\StockMovement;
use App\Models\MaterialRequest;
use App\Models\FuelLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function stockReport(Request $request)
    {
        $request->validate(['warehouse_id' => 'nullable|exists:warehouses,id']);

        $query = ItemStock::with(['item.category', 'warehouse']);

        if ($request->warehouse_id) {
            // Filter per gudang — tampilkan per baris normal
            $query->where('warehouse_id', $request->warehouse_id);
            if ($request->category_id) $query->whereHas('item', fn($q) => $q->where('category_id', $request->category_id));
            if ($request->filter === 'critical') $query->whereHas('item', fn($q) => $q->whereColumn('item_stocks.qty', '<=', 'items.min_stock'));
            if ($request->filter === 'minus') $query->where('qty', '<', 0);

            $rawStocks = $query->get();

            // Hitung simple average dari item_price_history per item+gudang
            $historyAvg = ItemPriceHistory::whereIn('item_id', $rawStocks->pluck('item_id'))
                ->where('warehouse_id', $request->warehouse_id)
                ->select('item_id', DB::raw('AVG(purchase_price) as simple_avg'))
                ->groupBy('item_id')
                ->pluck('simple_avg', 'item_id');

            $stocks = $rawStocks->map(fn($s) => [
                'id'        => $s->id,
                'item_id'   => $s->item_id,
                'item'      => $s->item,
                'qty'       => (float) $s->qty,
                'avg_price' => $historyAvg->has($s->item_id) ? round((float) $historyAvg[$s->item_id], 2) : (float) $s->avg_price,
                'gudang'    => [['id' => $s->warehouse_id, 'name' => $s->warehouse->name, 'qty' => (float) $s->qty]],
            ]);
        } else {
            // Semua gudang — gabungkan per item, jumlahkan stok
            if ($request->category_id) $query->whereHas('item', fn($q) => $q->where('category_id', $request->category_id));

            $allStocks = $query->get();

            // Hitung simple average dari item_price_history per item (semua gudang)
            $historyAvg = ItemPriceHistory::whereIn('item_id', $allStocks->pluck('item_id')->unique())
                ->select('item_id', DB::raw('AVG(purchase_price) as simple_avg'))
                ->groupBy('item_id')
                ->pluck('simple_avg', 'item_id');

            // Group by item_id, jumlahkan qty, kumpulkan info gudang
            $grouped = $allStocks->groupBy('item_id')->map(function ($rows) use ($historyAvg) {
                $first    = $rows->first();
                $totalQty = $rows->sum(fn($s) => (float) $s->qty);
                $gudang   = $rows->filter(fn($s) => $s->qty != 0)
                    ->map(fn($s) => ['id' => $s->warehouse_id, 'name' => $s->warehouse->name, 'qty' => (float) $s->qty])
                    ->values();

                // Pakai simple average dari history, fallback ke avg_price di item_stocks
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

            // Apply filter setelah grouping
            if ($request->filter === 'critical') {
                $grouped = $grouped->filter(fn($s) => $s['qty'] >= 0 && $s['qty'] <= $s['item']->min_stock && $s['item']->min_stock > 0)->values();
            } elseif ($request->filter === 'minus') {
                $grouped = $grouped->filter(fn($s) => $s['qty'] < 0)->values();
            }

            $stocks = $grouped->sortBy(fn($s) => $s['item']->name)->values();
        }

        $summary = [
            'total_items' => $stocks->count(),
            'total_value' => $stocks->sum(fn($s) => max(0, $s['qty'] ?? $s->qty ?? 0) * ($s['avg_price'] ?? $s->avg_price ?? 0)),
            'critical'    => $stocks->filter(fn($s) => ($s['qty'] ?? 0) >= 0 && ($s['qty'] ?? 0) <= ($s['item']->min_stock ?? 0) && ($s['item']->min_stock ?? 0) > 0)->count(),
            'minus'       => $stocks->filter(fn($s) => ($s['qty'] ?? 0) < 0)->count(),
        ];

        return response()->json(['success' => true, 'data' => $stocks, 'summary' => $summary]);
    }

    public function movementReport(Request $request)
    {
        $query = StockMovement::with(['item.category', 'fromWarehouse', 'toWarehouse', 'creator'])
            ->orderBy('movement_date', 'desc');

        if ($request->warehouse_id) {
            $query->where(fn($q) => $q->where('from_warehouse_id', $request->warehouse_id)->orWhere('to_warehouse_id', $request->warehouse_id));
        }
        if ($request->type) $query->where('type', $request->type);
        if ($request->date_from) $query->where('movement_date', '>=', $request->date_from);
        if ($request->date_to) $query->where('movement_date', '<=', $request->date_to);
        if ($request->item_id) $query->where('item_id', $request->item_id);

        $movements = $query->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'data' => $movements->items(),
            'meta' => ['total' => $movements->total(), 'page' => $movements->currentPage(), 'last_page' => $movements->lastPage()],
        ]);
    }

    public function pengeluaranReport(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $data = StockMovement::with(['item.category', 'fromWarehouse'])
            ->whereIn('type', ['out', 'transfer_out'])
            ->where('from_warehouse_id', $request->warehouse_id)
            ->whereBetween('movement_date', [$request->date_from, $request->date_to])
            ->orderBy('movement_date')
            ->get();

        $summary = [
            'total_records' => $data->count(),
            'total_qty' => $data->sum('qty'),
            'total_value' => $data->sum(fn($m) => $m->qty * $m->price),
        ];

        return response()->json(['success' => true, 'data' => $data, 'summary' => $summary]);
    }

    public function exportPdf(Request $request)
    {
        $type = $request->type ?? 'stock';
        $warehouseId = $request->warehouse_id;

        $data = match($type) {
            'stock' => ItemStock::with(['item.category', 'warehouse'])->where('warehouse_id', $warehouseId)->get(),
            'movement' => StockMovement::with(['item', 'fromWarehouse', 'toWarehouse'])
                ->when($warehouseId, fn($q) => $q->where(fn($q2) => $q2->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)))
                ->whereBetween('movement_date', [$request->date_from ?? now()->startOfMonth(), $request->date_to ?? now()])
                ->orderBy('movement_date')->get(),
            default => collect(),
        };

        $pdf = Pdf::loadView("reports.{$type}", ['data' => $data, 'request' => $request->all()]);
        return $pdf->download("laporan_{$type}_" . now()->format('Ymd') . '.pdf');
    }
}