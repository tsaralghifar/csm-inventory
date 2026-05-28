<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $service) {}

    // ─── Stok ─────────────────────────────────────────────────────────────────

    // GET /reports/stock
    public function stockReport(Request $request): JsonResponse
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

    // GET /reports/export-pdf
    public function exportPdf(Request $request): Response|BinaryFileResponse
    {
        $warehouseId = $request->warehouse_id ? (int) $request->warehouse_id : null;

        $result = $this->service->stockReport(
            warehouseId: $warehouseId,
            categoryId:  $request->category_id ? (int) $request->category_id : null,
            filter:      $request->filter,
        );

        $warehouseName = $request->warehouse_name
            ?? ($warehouseId ? Warehouse::find($warehouseId)?->name ?? 'Semua Gudang' : 'Semua Gudang');

        $pdf = Pdf::loadView('pdf.stock', [
            'data'    => $result['data'],
            'summary' => $result['summary'],
            'request' => array_merge($request->all(), ['warehouse_name' => $warehouseName]),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Laporan_Stok_' . now()->format('Ymd') . '.pdf');
    }

    // ─── Mutasi ───────────────────────────────────────────────────────────────

    // GET /reports/movements
    // Parameter tambahan: moveable_type — untuk filter khusus APD (moveable_type=apd_distribution)
    public function movementReport(Request $request): JsonResponse
    {
        $movements = $this->service->movementReport(
            warehouseId:   $request->warehouse_id   ? (int) $request->warehouse_id : null,
            type:          $request->type,
            dateFrom:      $request->date_from,
            dateTo:        $request->date_to,
            itemId:        $request->item_id         ? (int) $request->item_id : null,
            moveableType:  $request->moveable_type,
            perPage:       (int) ($request->per_page ?? 50),
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

    // ─── Pengeluaran ──────────────────────────────────────────────────────────

    // GET /reports/pengeluaran
    public function pengeluaranReport(Request $request): JsonResponse
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

    // ─── Pembelian ────────────────────────────────────────────────────────────

    // GET /reports/purchase
    public function purchaseReport(Request $request): JsonResponse
    {
        $result = $this->service->purchaseReport(
            dateFrom:    $request->date_from,
            dateTo:      $request->date_to,
            paymentType: $request->payment_type,
            status:      $request->status,
            supplierId:  $request->supplier_id ? (int) $request->supplier_id : null,
        );

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'summary' => $result['summary'],
        ]);
    }

    // GET /reports/purchase-pdf
    public function purchasePdf(Request $request): Response|BinaryFileResponse
    {
        $result = $this->service->purchaseReport(
            dateFrom:    $request->date_from,
            dateTo:      $request->date_to,
            paymentType: $request->payment_type,
            status:      $request->status,
            supplierId:  $request->supplier_id ? (int) $request->supplier_id : null,
        );

        $supplierName = $request->supplier_name
            ?? ($request->supplier_id
                ? Supplier::find($request->supplier_id)?->name ?? 'Semua Supplier'
                : 'Semua Supplier');

        $pdf = Pdf::loadView('pdf.purchase', [
            'data'    => $result['data'],
            'summary' => $result['summary'],
            'request' => array_merge($request->all(), ['supplier_name' => $supplierName]),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Laporan_Pembelian_' . now()->format('Ymd') . '.pdf');
    }
}