<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\ItemStock;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $service) {}

    public function stockReport(Request $request)
    {
        $request->validate(['warehouse_id' => 'nullable|exists:warehouses,id']);

        $result = $this->service->stockReport(
            warehouseId: $request->warehouse_id ? (int) $request->warehouse_id : null,
            categoryId:  $request->category_id  ? (int) $request->category_id  : null,
            filter:      $request->filter,
        );

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'summary' => $result['summary'],
        ]);
    }

    public function movementReport(Request $request)
    {
        $movements = $this->service->movementReport(
            warehouseId: $request->warehouse_id ? (int) $request->warehouse_id : null,
            type:        $request->type,
            dateFrom:    $request->date_from,
            dateTo:      $request->date_to,
            itemId:      $request->item_id ? (int) $request->item_id : null,
            perPage:     (int) ($request->per_page ?? 50),
        );

        return response()->json([
            'success' => true,
            'data'    => $movements->items(),
            'meta'    => [
                'total'     => $movements->total(),
                'page'      => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
            ],
        ]);
    }

    public function pengeluaranReport(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date|after_or_equal:date_from',
        ]);

        $result = $this->service->pengeluaranReport(
            warehouseId: (int) $request->warehouse_id,
            dateFrom:    $request->date_from,
            dateTo:      $request->date_to,
        );

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'summary' => $result['summary'],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $type        = $request->type ?? 'stock';
        $warehouseId = $request->warehouse_id;

        $data = match ($type) {
            'stock'    => ItemStock::with(['item.category', 'warehouse'])
                ->where('warehouse_id', $warehouseId)
                ->get(),
            'movement' => StockMovement::with(['item', 'fromWarehouse', 'toWarehouse'])
                ->when($warehouseId, fn($q) => $q->where(
                    fn($q2) => $q2->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)
                ))
                ->whereBetween('movement_date', [
                    $request->date_from ?? now()->startOfMonth(),
                    $request->date_to   ?? now(),
                ])
                ->orderBy('movement_date')
                ->get(),
            default    => collect(),
        };

        $pdf = Pdf::loadView("reports.{$type}", ['data' => $data, 'request' => $request->all()]);

        return $pdf->download("laporan_{$type}_" . now()->format('Ymd') . '.pdf');
    }
}