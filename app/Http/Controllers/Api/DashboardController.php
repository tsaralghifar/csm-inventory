<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemStock;
use App\Models\MaterialRequest;
use App\Models\PermintaanMaterial;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $warehouseIds = $this->getAccessibleWarehouseIds($user);

        // KPI Cards
        $criticalItems = ItemStock::join('items', 'item_stocks.item_id', '=', 'items.id')
            ->whereIn('item_stocks.warehouse_id', $warehouseIds)
            ->whereColumn('item_stocks.qty', '<=', 'items.min_stock')
            ->where('items.min_stock', '>', 0)
            ->count();

        $minusItems = ItemStock::whereIn('warehouse_id', $warehouseIds)
            ->where('qty', '<', 0)->count();

        $pendingMR = $user->hasRole('purchasing') ? 0 : MaterialRequest::where(fn($q) => $q
                ->whereIn('from_warehouse_id', $warehouseIds)
                ->orWhereIn('to_warehouse_id', $warehouseIds))
            ->whereIn('status', ['submitted', 'approved'])
            ->count();

        // PM yang butuh tindak lanjut segera (belum selesai diproses)
        $pmStatuses = ['approved', 'manager_approved'];
        $pendingPM = PermintaanMaterial::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', $pmStatuses)
            ->count();

        $pendingMR += $pendingPM;

        $todayMovements = StockMovement::whereIn(DB::raw('COALESCE(from_warehouse_id, to_warehouse_id)'), $warehouseIds)
            ->whereDate('created_at', today())
            ->count();

        // Critical items list
        $criticalList = ItemStock::with(['item.category', 'warehouse'])
            ->join('items', 'item_stocks.item_id', '=', 'items.id')
            ->whereIn('item_stocks.warehouse_id', $warehouseIds)
            ->whereColumn('item_stocks.qty', '<=', 'items.min_stock')
            ->where('items.min_stock', '>', 0)
            ->select('item_stocks.*')
            ->orderBy('item_stocks.qty')
            ->limit(10)
            ->get();

        // Minus items list
        $minusList = ItemStock::with(['item.category', 'warehouse'])
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('qty', '<', 0)
            ->orderBy('qty')
            ->limit(10)
            ->get();

        // Recent movements (last 7 days)
        $recentMovements = StockMovement::with(['item', 'fromWarehouse', 'toWarehouse', 'creator'])
            ->where(fn($q) => $q->whereIn('from_warehouse_id', $warehouseIds)->orWhereIn('to_warehouse_id', $warehouseIds))
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        // Pending MRs (model lama) — skip untuk role purchasing
        $pendingMRs = collect();
        if (!$user->hasRole('purchasing')) {
            $pendingMRs = MaterialRequest::with(['fromWarehouse', 'toWarehouse', 'requester'])
                ->where(fn($q) => $q->whereIn('from_warehouse_id', $warehouseIds)->orWhereIn('to_warehouse_id', $warehouseIds))
                ->whereIn('status', ['submitted', 'approved'])
                ->orderBy('created_at')
                ->limit(5)
                ->get()
                ->map(fn($mr) => [
                    'id'             => $mr->id,
                    'type'           => 'mr',
                    'nomor'          => $mr->mr_number,
                    'status'         => $mr->status,
                    'warehouse'      => $mr->fromWarehouse,
                    'requester'      => $mr->requester,
                    'created_at'     => $mr->created_at,
                    'url'            => "/mr/{$mr->id}",
                ]);
        }

        // Pending PM (PermintaanMaterial) — butuh tindak lanjut
        $pendingPMs = PermintaanMaterial::with(['warehouse', 'requester'])
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', $pmStatuses)
            ->orderBy('created_at')
            ->limit(5)
            ->get()
            ->map(fn($pm) => [
                'id'         => $pm->id,
                'type'       => 'pm',
                'nomor'      => $pm->nomor,
                'status'     => $pm->status,
                'warehouse'  => $pm->warehouse,
                'requester'  => $pm->requester,
                'created_at' => $pm->created_at,
                'url'        => "/permintaan-material/{$pm->id}",
            ]);

        $allPending = $pendingMRs->concat($pendingPMs)
            ->sortBy('created_at')
            ->take(10)
            ->values();

        // Monthly movement chart (last 6 months)
        $monthlyChart = StockMovement::selectRaw("DATE_TRUNC('month', movement_date) as month, type, SUM(qty) as total")
            ->where(fn($q) => $q->whereIn('from_warehouse_id', $warehouseIds)->orWhereIn('to_warehouse_id', $warehouseIds))
            ->where('movement_date', '>=', now()->subMonths(6))
            ->groupBy('month', 'type')
            ->orderBy('month')
            ->get();

        // Stock by warehouse
        $warehouseStocks = Warehouse::whereIn('id', $warehouseIds)
            ->withSum('itemStocks', 'qty')
            ->get(['id', 'name', 'type', 'code']);

        return response()->json([
            'success' => true,
            'data' => [
                'kpi' => [
                    'critical_items' => $criticalItems,
                    'minus_items' => $minusItems,
                    'pending_mr' => $pendingMR,
                    'today_movements' => $todayMovements,
                ],
                'critical_list' => $criticalList,
                'minus_list' => $minusList,
                'recent_movements' => $recentMovements,
                'pending_mrs' => $allPending,
                'monthly_chart' => $monthlyChart,
                'warehouse_stocks' => $warehouseStocks,
            ],
        ]);
    }

    private function getAccessibleWarehouseIds($user): array
    {
        if ($user->isSuperuser() || $user->isAdminHO() || $user->hasRole('purchasing') || $user->hasRole('manager')) {
            return Warehouse::pluck('id')->toArray();
        }
        return $user->warehouse_id ? [$user->warehouse_id] : [];
    }
}