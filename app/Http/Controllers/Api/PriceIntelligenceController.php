<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceAlertSetting;
use App\Models\PriceAnomaly;
use App\Services\PriceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PriceIntelligenceController extends Controller
{
    public function __construct(
        private readonly PriceAnalyticsService $analytics
    ) {}

    // GET /price-intelligence/dashboard
    public function dashboard(): JsonResponse
    {
        $data = $this->analytics->getDashboardSummary();
        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /price-intelligence/trend?item_id=&date_from=&date_to=
    public function trend(Request $request): JsonResponse
    {
        $request->validate([
            'item_id'   => 'required|integer|exists:items,id',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $dateFrom = $request->date_from ?? now()->subMonths(6)->toDateString();
        $dateTo   = $request->date_to   ?? now()->toDateString();

        $data = $this->analytics->getPriceTrend(
            $request->item_id,
            $dateFrom,
            $dateTo,
        );

        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /price-intelligence/supplier-comparison?item_id=
    public function supplierComparison(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|integer|exists:items,id',
        ]);

        $data = $this->analytics->getSupplierComparison($request->item_id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /price-intelligence/budget?months=12
    public function budget(Request $request): JsonResponse
    {
        $months = min((int) ($request->months ?? 12), 24);
        $data   = $this->analytics->getBudgetMonitor($months);
        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /price-intelligence/history
    public function history(Request $request): JsonResponse
    {
        $paginated = $this->analytics->getPriceHistoryList(
            $request->only([
                'item_id', 'supplier', 'severity',
                'date_from', 'date_to', 'warehouse_id', 'changes_only',
            ]),
            $request->per_page ?? 30,
        );

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'meta'    => [
                'total'     => $paginated->total(),
                'page'      => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page'  => $paginated->perPage(),
            ],
        ]);
    }

    // GET /price-intelligence/anomalies
    public function anomalies(Request $request): JsonResponse
    {
        $paginated = $this->analytics->getAnomalyList(
            $request->only(['type', 'severity', 'unread', 'item_id']),
            $request->per_page ?? 20,
        );

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'meta'    => [
                'total'     => $paginated->total(),
                'page'      => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    // POST /price-intelligence/anomalies/{id}/read
    public function markRead(int $id): JsonResponse
    {
        $this->analytics->markAnomalyRead($id);
        return response()->json(['success' => true]);
    }

    // POST /price-intelligence/anomalies/read-all
    public function markAllRead(): JsonResponse
    {
        $this->analytics->markAllAnomaliesRead();
        return response()->json(['success' => true]);
    }

    // GET /price-intelligence/settings
    public function getSettings(): JsonResponse
    {
        $settings = PriceAlertSetting::orderBy('key')->get();
        return response()->json(['success' => true, 'data' => $settings]);
    }

    // PUT /price-intelligence/settings
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings'         => 'required|array',
            'settings.*.key'   => 'required|string',
            'settings.*.value' => 'required|string',
        ]);

        foreach ($data['settings'] as $s) {
            PriceAlertSetting::set($s['key'], $s['value']);
        }

        Cache::forget('price_alert_settings');

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi berhasil disimpan.',
        ]);
    }
}
