<?php

namespace App\Http\Controllers\Api;

use App\Events\FuelLogUpdated;
use App\Http\Controllers\Controller;
use App\Models\FuelLog;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class FuelLogController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = FuelLog::with(['warehouse', 'unit', 'creator'])
            ->orderBy('log_date', 'desc')
            ->orderBy('id', 'desc');

        if (!$user->isSuperuser() && !$user->isAdminHO() && !$user->isLogistikHO()) {
            $query->where('warehouse_id', $user->warehouse_id);
        }

        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->month)        $query->whereRaw("TO_CHAR(log_date,'YYYY-MM') = ?", [$request->month]);
        if ($request->unit_code)    $query->where('unit_code', 'ilike', "%{$request->unit_code}%");

        // Filter by type: 'in' (stock_in > 0), 'out' (liter_out > 0), default all
        if ($request->type === 'in')  $query->where('stock_in', '>', 0);
        if ($request->type === 'out') $query->where('liter_out', '>', 0);

        $logs = $query->paginate($request->per_page ?? 25);

        // Summary always calculated for selected warehouse+month
        $summaryQuery = FuelLog::query();
        if (!$user->isSuperuser() && !$user->isAdminHO() && !$user->isLogistikHO()) {
            $summaryQuery->where('warehouse_id', $user->warehouse_id);
        }
        if ($request->warehouse_id) $summaryQuery->where('warehouse_id', $request->warehouse_id);
        if ($request->month)        $summaryQuery->whereRaw("TO_CHAR(log_date,'YYYY-MM') = ?", [$request->month]);

        $summaryAgg = (clone $summaryQuery)->selectRaw(
            'SUM(liter_out) as total_out, SUM(stock_in) as total_in,
             MIN(stock_before) as stock_start,
             COUNT(*) as total_entries,
             COUNT(DISTINCT CASE WHEN unit_code IS NOT NULL AND unit_code != \'\' THEN unit_code END) as total_units'
        )->first();

        // Stok akhir = stock_after dari log TERBARU (bukan MAX)
        $lastLogForSummary = (clone $summaryQuery)
            ->orderBy('log_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $summary = $summaryAgg;
        if ($summary) {
            $summary->stock_end = $lastLogForSummary ? (float) $lastLogForSummary->stock_after : 0;
        }

        // Per-unit consumption for laporan tab
        $unitConsumption = null;
        if ($request->with_units) {
            $unitQ = FuelLog::query();
            if (!$user->isSuperuser() && !$user->isAdminHO() && !$user->isLogistikHO()) {
                $unitQ->where('warehouse_id', $user->warehouse_id);
            }
            if ($request->warehouse_id) $unitQ->where('warehouse_id', $request->warehouse_id);
            if ($request->month)        $unitQ->whereRaw("TO_CHAR(log_date,'YYYY-MM') = ?", [$request->month]);

            $unitConsumption = $unitQ
                ->selectRaw('unit_code, unit_type, division, SUM(liter_out) as total_out, COUNT(*) as fill_count, AVG(liter_out) as avg_per_fill')
                ->groupBy('unit_code', 'unit_type', 'division')
                ->orderByDesc('total_out')
                ->get();
        }

        // Stock per warehouse (for alert)
        $stockAlerts = null;
        if ($request->with_alerts) {
            $stockAlerts = Warehouse::select('id', 'name')
                ->get()
                ->map(function ($w) use ($request) {
                    $last = FuelLog::where('warehouse_id', $w->id)
                        ->orderBy('log_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                    return [
                        'warehouse_id'   => $w->id,
                        'warehouse_name' => $w->name,
                        'stock_current'  => $last ? (float) $last->stock_after : 0,
                        'last_updated'   => $last ? $last->log_date : null,
                    ];
                })
                ->filter(fn($s) => $s['stock_current'] >= 0);
        }

        // Stok terkini per warehouse (tanpa filter bulan, untuk validasi form keluar)
        $stockCurrent = null;
        if ($request->warehouse_id) {
            $latestLog = FuelLog::where('warehouse_id', $request->warehouse_id)
                ->orderBy('log_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            $stockCurrent = $latestLog ? (float) $latestLog->stock_after : 0;
        }

        return response()->json([
            'success'          => true,
            'data'             => $logs->items(),
            'meta'             => ['total' => $logs->total(), 'page' => $logs->currentPage(), 'last_page' => $logs->lastPage()],
            'summary'          => $summary,
            'stock_current'    => $stockCurrent,
            'unit_consumption' => $unitConsumption,
            'stock_alerts'     => $stockAlerts,
        ]);
    }

    public function store(Request $request)
    {
        // Bersihkan fill_time kosong agar tidak gagal validasi date_format
        if ($request->fill_time === '' || $request->fill_time === null) {
            $request->merge(['fill_time' => null]);
        }

        $validated = $request->validate([
            'log_date'      => 'required|date',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'unit_id'       => 'nullable|exists:units,id',
            'unit_code'     => 'nullable|string',
            'unit_type'     => 'nullable|string',
            'division'      => 'nullable|string',
            'hm_km'         => 'nullable|numeric',
            'fill_time'     => 'nullable|date_format:H:i',
            'liter_out'     => 'required|numeric|min:0',
            'stock_in'      => 'nullable|numeric|min:0',
            'operator_name' => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        $lastLog = FuelLog::where('warehouse_id', $validated['warehouse_id'])
            ->orderBy('log_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $stockBefore = $lastLog ? (float) $lastLog->stock_after : 0;
        $stockIn     = (float) ($validated['stock_in'] ?? 0);
        $literOut    = (float) $validated['liter_out'];
        $stockAfter  = $stockBefore + $stockIn - $literOut;

        // Validate: cannot issue more than available stock
        if ($literOut > 0 && $stockAfter < 0) {
            return response()->json([
                'success' => false,
                'message' => "Stok solar tidak mencukupi. Stok saat ini: {$stockBefore} L, permintaan keluar: {$literOut} L.",
            ], 422);
        }

        $validated['stock_before'] = $stockBefore;
        $validated['stock_after']  = $stockAfter;
        $validated['created_by']   = $request->user()->id;

        $log = FuelLog::create($validated);

        broadcast(new FuelLogUpdated($log->fresh(), 'created'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $log->load('warehouse', 'unit'),
            'message' => 'Log BBM berhasil dicatat',
        ], 201);
    }

    public function update(Request $request, FuelLog $fuelLog)
    {
        $validated = $request->validate([
            'log_date'      => 'sometimes|date',
            'unit_code'     => 'nullable|string',
            'unit_type'     => 'nullable|string',
            'hm_km'         => 'nullable|numeric',
            'liter_out'     => 'sometimes|numeric|min:0',
            'stock_in'      => 'nullable|numeric|min:0',
            'operator_name' => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        $fuelLog->update($validated);

        // Recalculate stock_after for this log
        $fuelLog->stock_after = $fuelLog->stock_before + ($fuelLog->stock_in ?? 0) - $fuelLog->liter_out;
        $fuelLog->save();

        broadcast(new FuelLogUpdated($fuelLog->fresh(), 'updated'))->toOthers();

        return response()->json(['success' => true, 'data' => $fuelLog, 'message' => 'Log BBM diperbarui']);
    }

    public function destroy(FuelLog $fuelLog)
    {
        $warehouseId = $fuelLog->warehouse_id;
        $logId       = $fuelLog->id;
        $logDate     = $fuelLog->log_date;

        $fuelLog->delete();

        broadcast(new FuelLogUpdated(new FuelLog(['id' => $logId, 'warehouse_id' => $warehouseId, 'log_date' => $logDate]), 'deleted'))->toOthers();

        return response()->json(['success' => true, 'message' => 'Log BBM dihapus']);
    }
}