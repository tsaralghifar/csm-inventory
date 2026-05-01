<?php

namespace App\Services;

use App\Models\ItemStock;
use App\Models\MaterialRequest;
use App\Models\PermintaanMaterial;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Kumpulkan semua data dashboard dalam satu struktur.
     *
     * @return array{kpi: array, critical_list: mixed, minus_list: mixed, recent_movements: mixed, pending_mrs: mixed, monthly_chart: mixed, warehouse_stocks: mixed}
     */
    public function getData($user): array
    {
        $warehouseIds = $this->getAccessibleWarehouseIds($user);

        return [
            'kpi'              => $this->kpi($user, $warehouseIds),
            'critical_list'    => $this->criticalList($warehouseIds),
            'minus_list'       => $this->minusList($warehouseIds),
            'recent_movements' => $this->recentMovements($warehouseIds),
            'pending_mrs'      => $this->pendingItems($user, $warehouseIds),
            'monthly_chart'    => $this->monthlyChart($warehouseIds),
            'warehouse_stocks' => $this->warehouseStocks($warehouseIds),
        ];
    }

    /**
     * Empat KPI card: stok kritis, stok minus, pending MR/PM, mutasi hari ini.
     *
     * @return array{critical_items: int, minus_items: int, pending_mr: int, today_movements: int}
     */
    public function kpi($user, array $warehouseIds): array
    {
        $criticalItems = ItemStock::join('items', 'item_stocks.item_id', '=', 'items.id')
            ->whereIn('item_stocks.warehouse_id', $warehouseIds)
            ->whereColumn('item_stocks.qty', '<=', 'items.min_stock')
            ->where('items.min_stock', '>', 0)
            ->count();

        $minusItems = ItemStock::whereIn('warehouse_id', $warehouseIds)
            ->where('qty', '<', 0)
            ->count();

        // Purchasing tidak melihat pending MR model lama maupun PM
        $isPurchasing = $user->hasRole('purchasing');

        $pendingMR = $isPurchasing ? 0 : MaterialRequest::where(
            fn($q) => $q->whereIn('from_warehouse_id', $warehouseIds)->orWhereIn('to_warehouse_id', $warehouseIds)
        )->whereIn('status', ['submitted', 'approved'])->count();

        $pmStatuses = ['approved', 'manager_approved', 'pending_purchasing'];
        $pendingPM  = $isPurchasing ? 0 : PermintaanMaterial::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', $pmStatuses)
            ->count();

        $todayMovements = StockMovement::whereIn(
            DB::raw('COALESCE(from_warehouse_id, to_warehouse_id)'),
            $warehouseIds
        )->whereDate('created_at', today())->count();

        return [
            'critical_items'  => $criticalItems,
            'minus_items'     => $minusItems,
            'pending_mr'      => $pendingMR + $pendingPM,
            'today_movements' => $todayMovements,
        ];
    }

    /**
     * Daftar 10 item dengan stok paling kritis (qty <= min_stock).
     */
    public function criticalList(array $warehouseIds)
    {
        return ItemStock::with(['item.category', 'warehouse'])
            ->join('items', 'item_stocks.item_id', '=', 'items.id')
            ->whereIn('item_stocks.warehouse_id', $warehouseIds)
            ->whereColumn('item_stocks.qty', '<=', 'items.min_stock')
            ->where('items.min_stock', '>', 0)
            ->select('item_stocks.*')
            ->orderBy('item_stocks.qty')
            ->limit(10)
            ->get();
    }

    /**
     * Daftar 10 item dengan stok minus (qty < 0).
     */
    public function minusList(array $warehouseIds)
    {
        return ItemStock::with(['item.category', 'warehouse'])
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('qty', '<', 0)
            ->orderBy('qty')
            ->limit(10)
            ->get();
    }

    /**
     * 15 mutasi stok terbaru lintas semua gudang yang dapat diakses.
     */
    public function recentMovements(array $warehouseIds)
    {
        return StockMovement::with(['item', 'fromWarehouse', 'toWarehouse', 'creator'])
            ->where(
                fn($q) => $q->whereIn('from_warehouse_id', $warehouseIds)->orWhereIn('to_warehouse_id', $warehouseIds)
            )
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();
    }

    /**
     * Gabungan pending MR (model lama) + pending PM (PermintaanMaterial), max 10 item,
     * diurutkan dari yang paling lama dibuat (butuh tindak lanjut segera).
     */
    public function pendingItems($user, array $warehouseIds)
    {
        $pmStatuses = ['approved', 'manager_approved', 'pending_purchasing'];

        $pendingMRs = collect();
        if (!$user->hasRole('purchasing')) {
            $pendingMRs = MaterialRequest::with(['fromWarehouse', 'toWarehouse', 'requester'])
                ->where(
                    fn($q) => $q->whereIn('from_warehouse_id', $warehouseIds)->orWhereIn('to_warehouse_id', $warehouseIds)
                )
                ->whereIn('status', ['submitted', 'approved'])
                ->orderBy('created_at')
                ->limit(5)
                ->get()
                ->map(fn($mr) => [
                    'id'         => $mr->id,
                    'type'       => 'mr',
                    'nomor'      => $mr->mr_number,
                    'status'     => $mr->status,
                    'warehouse'  => $mr->fromWarehouse,
                    'requester'  => $mr->requester,
                    'created_at' => $mr->created_at,
                    'url'        => "/mr/{$mr->id}",
                ]);
        }

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

        return $pendingMRs->concat($pendingPMs)
            ->sortBy('created_at')
            ->take(10)
            ->values();
    }

    /**
     * Chart mutasi stok 6 bulan terakhir, dikelompokkan per bulan + tipe.
     */
    public function monthlyChart(array $warehouseIds)
    {
        return StockMovement::selectRaw("DATE_TRUNC('month', movement_date) as month, type, SUM(qty) as total")
            ->where(
                fn($q) => $q->whereIn('from_warehouse_id', $warehouseIds)->orWhereIn('to_warehouse_id', $warehouseIds)
            )
            ->where('movement_date', '>=', now()->subMonths(6))
            ->groupBy('month', 'type')
            ->orderBy('month')
            ->get();
    }

    /**
     * Total stok per gudang yang dapat diakses.
     */
    public function warehouseStocks(array $warehouseIds)
    {
        return Warehouse::whereIn('id', $warehouseIds)
            ->withSum('itemStocks', 'qty')
            ->get(['id', 'name', 'type', 'code']);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /**
     * Tentukan warehouse yang boleh dilihat berdasarkan role user.
     */
    public function getAccessibleWarehouseIds($user): array
    {
        if (
            $user->isSuperuser() ||
            $user->isAdminHO() ||
            $user->hasRole('purchasing') ||
            $user->hasRole('manager')
        ) {
            return Warehouse::pluck('id')->toArray();
        }

        return $user->warehouse_id ? [$user->warehouse_id] : [];
    }
}