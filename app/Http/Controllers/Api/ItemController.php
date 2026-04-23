<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Services\StockService;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request)
    {
        $query = Item::with('category')->active();

        if ($request->search) $query->search($request->search);
        if ($request->category_id) $query->where('category_id', $request->category_id);
        if ($request->warehouse_id) {
            $query->whereHas('itemStocks', fn($q) => $q->where('warehouse_id', $request->warehouse_id)->where('qty', '>', 0));
        }

        $items = $query->orderBy('name')->paginate($request->per_page ?? 20);

        // Append stock for specific warehouse if requested
        if ($request->warehouse_id) {
            $items->getCollection()->transform(function ($item) use ($request) {
                $stock = $item->itemStocks->where('warehouse_id', $request->warehouse_id)->first();
                $item->current_stock = $stock ? (float) $stock->qty : 0;
                $item->is_critical = $stock ? $stock->isCritical() : false;
                return $item;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'total' => $items->total(),
                'page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-items');

        $validated = $request->validate([
            'part_number' => 'required|string|max:100|unique:items',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'brand' => 'nullable|string',
            'unit' => 'required|string|max:20',
            'min_stock' => 'required|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'location_code' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $item = Item::create($validated);
        return response()->json(['success' => true, 'data' => $item->load('category'), 'message' => 'Barang berhasil ditambahkan'], 201);
    }

    public function show(Item $item)
    {
        $item->load('category', 'itemStocks.warehouse');
        return response()->json(['success' => true, 'data' => $item]);
    }

    public function update(Request $request, Item $item)
    {
        $this->authorize('manage-items');

        $validated = $request->validate([
            'part_number' => "sometimes|string|max:100|unique:items,part_number,{$item->id}",
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'brand' => 'nullable|string',
            'unit' => 'sometimes|string|max:20',
            'min_stock' => 'sometimes|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'location_code' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $item->update($validated);
        return response()->json(['success' => true, 'data' => $item->load('category'), 'message' => 'Barang berhasil diperbarui']);
    }

    public function destroy(Item $item)
    {
        $this->authorize('manage-items');
        $item->delete();
        return response()->json(['success' => true, 'message' => 'Barang berhasil dihapus']);
    }

    public function stockIn(Request $request, Item $item)
    {
        $this->authorize('create-stock-in');

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'qty' => 'required|numeric|min:0.01',
            'price' => 'nullable|numeric|min:0',
            'po_number' => 'nullable|string',
            'invoice_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'movement_date' => 'required|date',
        ]);

        $movement = $this->stockService->stockIn(
            array_merge($validated, ['item_id' => $item->id]),
            $request->user()->id
        );

        return response()->json(['success' => true, 'data' => $movement, 'message' => 'Stok berhasil ditambahkan']);
    }

    public function stockOut(Request $request, Item $item)
    {
        $this->authorize('create-stock-out');

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'qty' => 'required|numeric|min:0.01',
            'unit_code' => 'nullable|string',
            'unit_type' => 'nullable|string',
            'hm_km' => 'nullable|numeric',
            'po_number' => 'nullable|string',
            'mechanic' => 'nullable|string',
            'site_name' => 'nullable|string',
            'notes' => 'nullable|string',
            'movement_date' => 'required|date',
        ]);

        $movement = $this->stockService->stockOut(
            array_merge($validated, ['item_id' => $item->id]),
            $request->user()->id
        );

        return response()->json(['success' => true, 'data' => $movement, 'message' => 'Stok keluar berhasil dicatat']);
    }

    public function movements(Request $request, Item $item)
    {
        $query = $item->stockMovements()
            ->with(['fromWarehouse', 'toWarehouse', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->warehouse_id) {
            $query->where(fn($q) => $q->where('from_warehouse_id', $request->warehouse_id)->orWhere('to_warehouse_id', $request->warehouse_id));
        }

        $movements = $query->paginate(20);
        return response()->json(['success' => true, 'data' => $movements]);
    }
}
