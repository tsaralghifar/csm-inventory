<?php

namespace App\Http\Controllers\Api;

use App\Events\ItemUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\StockInRequest;
use App\Http\Requests\StockOutRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Models\ItemPriceHistory;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Item::with('category')->active();

        if ($request->search)       $query->search($request->search);
        if ($request->category_id)  $query->where('category_id', $request->category_id);
        if ($request->warehouse_id) {
            $query->whereHas('itemStocks', fn($q) => $q->where('warehouse_id', $request->warehouse_id)->where('qty', '>', 0));
        }

        $items = $query->orderBy('name')->paginate($request->per_page ?? 20);

        $itemIds   = $items->getCollection()->pluck('id');
        $avgPrices = ItemPriceHistory::whereIn('item_id', $itemIds)
            ->select('item_id', DB::raw('AVG(purchase_price) as simple_avg'))
            ->groupBy('item_id')
            ->pluck('simple_avg', 'item_id');

        $items->getCollection()->transform(function ($item) use ($request, $avgPrices) {
            if ($avgPrices->has($item->id)) {
                $item->price = round((float) $avgPrices[$item->id], 2);
            }
            if ($request->warehouse_id) {
                $stock               = $item->itemStocks->where('warehouse_id', $request->warehouse_id)->first();
                $item->current_stock = $stock ? (float) $stock->qty : 0;
                $item->is_critical   = $stock ? $stock->isCritical() : false;
            }
            return $item;
        });

        return $this->paginated($items);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $item = Item::create($request->validated());

        broadcast(new ItemUpdated($item->fresh(), 'created'))->toOthers();

        return $this->created($item->load('category'), 'Barang berhasil ditambahkan');
    }

    public function show(Item $item): JsonResponse
    {
        $item->load('category', 'itemStocks.warehouse');
        return $this->success($item);
    }

    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $item->update($request->validated());

        broadcast(new ItemUpdated($item->fresh(), 'updated'))->toOthers();

        return $this->success($item->load('category'), 'Barang berhasil diperbarui');
    }

    public function destroy(Item $item): JsonResponse
    {
        $this->authorize('manage-items');

        $deletedItem = $item->replicate(); // simpan data sebelum dihapus untuk broadcast
        $item->delete();

        broadcast(new ItemUpdated($deletedItem, 'deleted'))->toOthers();

        return $this->deleted('Barang berhasil dihapus');
    }

    public function stockIn(StockInRequest $request, Item $item): JsonResponse
    {
        $movement = $this->stockService->stockIn(
            array_merge($request->validated(), ['item_id' => $item->id]),
            $request->user()->id
        );

        broadcast(new ItemUpdated($item->fresh(), 'stock_in', $request->validated()['warehouse_id'] ?? null))->toOthers();

        return $this->created($movement, 'Stok berhasil ditambahkan');
    }

    public function stockOut(StockOutRequest $request, Item $item): JsonResponse
    {
        $movement = $this->stockService->stockOut(
            array_merge($request->validated(), ['item_id' => $item->id]),
            $request->user()->id
        );

        broadcast(new ItemUpdated($item->fresh(), 'stock_out', $request->validated()['warehouse_id'] ?? null))->toOthers();

        return $this->created($movement, 'Stok keluar berhasil dicatat');
    }

    public function movements(Request $request, Item $item): JsonResponse
    {
        $query = $item->stockMovements()
            ->with(['fromWarehouse', 'toWarehouse', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->warehouse_id) {
            $query->where(fn($q) => $q
                ->where('from_warehouse_id', $request->warehouse_id)
                ->orWhere('to_warehouse_id', $request->warehouse_id)
            );
        }

        return $this->paginated($query->paginate(20));
    }
}