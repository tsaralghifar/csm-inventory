<?php

namespace App\Http\Controllers\Api;

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

/**
 * Contoh ItemController yang sudah direfactor:
 * - Menggunakan Form Request (StoreItemRequest, UpdateItemRequest, dll)
 * - Menggunakan ApiResponse trait dari base Controller
 *
 * Perubahan dari versi lama:
 *   SEBELUM: $request->validate([...])  → validasi inline di controller
 *   SESUDAH: StoreItemRequest           → validasi + authorize di class tersendiri
 *
 *   SEBELUM: response()->json(['success' => true, 'data' => ...])
 *   SESUDAH: $this->success($data, 'Pesan')  → format konsisten via trait
 */
class ItemController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Item::with('category')->active();

        if ($request->search) $query->search($request->search);
        if ($request->category_id) $query->where('category_id', $request->category_id);
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
                $stock                = $item->itemStocks->where('warehouse_id', $request->warehouse_id)->first();
                $item->current_stock  = $stock ? (float) $stock->qty : 0;
                $item->is_critical    = $stock ? $stock->isCritical() : false;
            }
            return $item;
        });

        // Menggunakan $this->paginated() dari ApiResponse trait
        return $this->paginated($items);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        // authorize() & validate() sudah otomatis dipanggil oleh StoreItemRequest
        $item = Item::create($request->validated());

        // Menggunakan $this->created() dari ApiResponse trait
        return $this->created($item->load('category'), 'Barang berhasil ditambahkan');
    }

    public function show(Item $item): JsonResponse
    {
        $item->load('category', 'itemStocks.warehouse');

        // Menggunakan $this->success() dari ApiResponse trait
        return $this->success($item);
    }

    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $item->update($request->validated());

        return $this->success($item->load('category'), 'Barang berhasil diperbarui');
    }

    public function destroy(Item $item): JsonResponse
    {
        $this->authorize('manage-items');
        $item->delete();

        // Menggunakan $this->deleted() dari ApiResponse trait
        return $this->deleted('Barang berhasil dihapus');
    }

    public function stockIn(StockInRequest $request, Item $item): JsonResponse
    {
        $movement = $this->stockService->stockIn(
            array_merge($request->validated(), ['item_id' => $item->id]),
            $request->user()->id
        );

        return $this->created($movement, 'Stok berhasil ditambahkan');
    }

    public function stockOut(StockOutRequest $request, Item $item): JsonResponse
    {
        $movement = $this->stockService->stockOut(
            array_merge($request->validated(), ['item_id' => $item->id]),
            $request->user()->id
        );

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