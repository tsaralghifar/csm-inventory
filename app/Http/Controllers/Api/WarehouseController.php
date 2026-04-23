<?php

namespace App\Http\Controllers\Api;

use App\Events\MasterDataUpdated;
use App\Http\Controllers\Controller;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Warehouse::withCount(['itemStocks', 'units'])
            ->with('users')
            ->orderBy('type')->orderBy('name');

        // Superuser, admin_ho, atau user dengan permission manage-warehouses bisa lihat semua gudang
        if (!$user->isSuperuser() && !$user->isAdminHO() && !$user->hasPermissionTo('manage-warehouses')) {
            $query->where('id', $user->warehouse_id);
        }

        if ($request->type) $query->where('type', $request->type);
        if ($request->search) $query->where('name', 'ilike', "%{$request->search}%");
        if ($request->has('active')) $query->where('is_active', $request->boolean('active'));

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-warehouses');

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:warehouses',
            'name' => 'required|string|max:255',
            'type' => 'required|in:ho,site',
            'location' => 'nullable|string',
            'address' => 'nullable|string',
            'pic_name' => 'nullable|string',
            'pic_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $warehouse = Warehouse::create($validated);
        broadcast(new MasterDataUpdated('gudang', 'created', $warehouse->id))->toOthers();
        return response()->json(['success' => true, 'data' => $warehouse, 'message' => 'Gudang berhasil ditambahkan'], 201);
    }

    public function show(Warehouse $warehouse)
    {
        $warehouse->load('users', 'units');
        $summary = [
            'total_items' => ItemStock::where('warehouse_id', $warehouse->id)->where('qty', '>', 0)->count(),
            'critical_items' => ItemStock::where('warehouse_id', $warehouse->id)
                ->whereColumn('qty', '<=', 'items.min_stock')
                ->join('items', 'item_stocks.item_id', 'items.id')
                ->where('items.min_stock', '>', 0)->count(),
            'minus_items' => ItemStock::where('warehouse_id', $warehouse->id)->where('qty', '<', 0)->count(),
        ];

        return response()->json(['success' => true, 'data' => array_merge($warehouse->toArray(), ['summary' => $summary])]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $this->authorize('manage-warehouses');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'nullable|string',
            'address' => 'nullable|string',
            'pic_name' => 'nullable|string',
            'pic_phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $warehouse->update($validated);
        broadcast(new MasterDataUpdated('gudang', 'updated', $warehouse->id))->toOthers();
        return response()->json(['success' => true, 'data' => $warehouse, 'message' => 'Gudang berhasil diperbarui']);
    }

    public function stocks(Request $request, Warehouse $warehouse)
    {
        $user = $request->user();
        if (!$user->canAccessWarehouse($warehouse->id)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $query = ItemStock::with(['item.category'])
            ->where('warehouse_id', $warehouse->id);

        if ($request->search) {
            $query->whereHas('item', fn($q) => $q->search($request->search));
        }
        if ($request->category_id) {
            $query->whereHas('item', fn($q) => $q->where('category_id', $request->category_id));
        }
        if ($request->filter === 'critical') {
            $query->whereHas('item', fn($q) => $q->whereColumn('item_stocks.qty', '<=', 'items.min_stock')->where('min_stock', '>', 0));
        }
        if ($request->filter === 'minus') {
            $query->where('qty', '<', 0);
        }

        $stocks = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $stocks->items(),
            'meta' => [
                'total' => $stocks->total(),
                'page' => $stocks->currentPage(),
                'last_page' => $stocks->lastPage(),
                'per_page' => $stocks->perPage(),
            ],
        ]);
    }
}