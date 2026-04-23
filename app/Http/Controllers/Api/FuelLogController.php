<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FuelLog;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuelLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = FuelLog::with(['warehouse', 'unit', 'creator'])->orderBy('log_date', 'desc')->orderBy('id', 'desc');

        if (!$user->isSuperuser() && !$user->isAdminHO()) {
            $query->where('warehouse_id', $user->warehouse_id);
        }

        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->month) $query->whereRaw("TO_CHAR(log_date,'YYYY-MM') = ?", [$request->month]);
        if ($request->unit_code) $query->where('unit_code', 'ilike', "%{$request->unit_code}%");

        $logs = $query->paginate($request->per_page ?? 20);

        // Calculate stock summary per warehouse per month
        $summary = null;
        if ($request->warehouse_id && $request->month) {
            $summary = FuelLog::where('warehouse_id', $request->warehouse_id)
                ->whereRaw("TO_CHAR(log_date,'YYYY-MM') = ?", [$request->month])
                ->selectRaw('SUM(liter_out) as total_out, SUM(stock_in) as total_in, MAX(stock_after) as stock_end, MIN(stock_before) as stock_start')
                ->first();
        }

        return response()->json(['success' => true, 'data' => $logs->items(), 'meta' => ['total' => $logs->total(), 'page' => $logs->currentPage(), 'last_page' => $logs->lastPage()], 'summary' => $summary]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'log_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'unit_id' => 'nullable|exists:units,id',
            'unit_code' => 'nullable|string',
            'unit_type' => 'nullable|string',
            'division' => 'nullable|string',
            'hm_km' => 'nullable|numeric',
            'fill_time' => 'nullable|date_format:H:i',
            'liter_out' => 'required|numeric|min:0',
            'stock_in' => 'nullable|numeric|min:0',
            'operator_name' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Calculate stock_before from last log for this warehouse
        $lastLog = FuelLog::where('warehouse_id', $validated['warehouse_id'])
            ->orderBy('log_date', 'desc')->orderBy('id', 'desc')->first();

        $validated['stock_before'] = $lastLog ? (float) $lastLog->stock_after : 0;
        $validated['stock_after'] = $validated['stock_before'] + ($validated['stock_in'] ?? 0) - $validated['liter_out'];
        $validated['created_by'] = $request->user()->id;

        $log = FuelLog::create($validated);
        return response()->json(['success' => true, 'data' => $log->load('warehouse', 'unit'), 'message' => 'Log BBM berhasil dicatat'], 201);
    }

    public function update(Request $request, FuelLog $fuelLog)
    {
        $validated = $request->validate([
            'log_date' => 'sometimes|date',
            'unit_code' => 'nullable|string',
            'unit_type' => 'nullable|string',
            'hm_km' => 'nullable|numeric',
            'liter_out' => 'sometimes|numeric|min:0',
            'stock_in' => 'nullable|numeric|min:0',
            'operator_name' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $fuelLog->update($validated);
        return response()->json(['success' => true, 'data' => $fuelLog, 'message' => 'Log BBM diperbarui']);
    }

    public function destroy(FuelLog $fuelLog)
    {
        $fuelLog->delete();
        return response()->json(['success' => true, 'message' => 'Log BBM dihapus']);
    }
}
