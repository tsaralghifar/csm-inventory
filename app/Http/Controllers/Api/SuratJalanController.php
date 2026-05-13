<?php

namespace App\Http\Controllers\Api;

use App\Events\SuratJalanUpdated;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SuratJalan;
use App\Services\SuratJalanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuratJalanController extends Controller
{
    /**
     * SuratJalanController — Tanda Terima Barang dari Purchase Order
     *
     * Mendukung partial delivery:
     *   - Satu PO bisa memiliki banyak Surat Jalan (TTB)
     *   - Setiap TTB mencatat qty yang diterima per item (boleh kurang dari qty PO)
     *   - qty_received di purchase_order_items terakumulasi dari semua TTB
     *   - delivery_status PO otomatis berubah: null → partial → completed
     *   - Stok masuk ke gudang saat TTB di-confirm
     */
    public function __construct(
        private readonly SuratJalanService $suratJalanService
    ) {}

    // GET /surat-jalan
    public function index(Request $request): JsonResponse
    {
        $data = $this->suratJalanService->list($request);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => [
                'total'     => $data->total(),
                'page'      => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page'  => $data->perPage(),
            ],
        ]);
    }

    // GET /surat-jalan/{id}
    public function show(SuratJalan $suratJalan): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $suratJalan->load(['purchaseOrder.items.item', 'warehouse', 'creator', 'receiver', 'items.item']),
        ]);
    }

    // GET /surat-jalan/po/{po}/remaining
    public function remaining(PurchaseOrder $po): JsonResponse
    {
        $items = $po->items()->with('item')->get()->map(function ($poItem) {
            $remaining = max(0, $poItem->qty - $poItem->qty_received);
            return [
                'purchase_order_item_id' => $poItem->id,
                'item_id'                => $poItem->item_id,
                'nama_barang'            => $poItem->nama_barang,
                'satuan'                 => $poItem->satuan,
                'harga_satuan'           => $poItem->harga_satuan,
                'kode_unit'              => $poItem->kode_unit,
                'tipe_unit'              => $poItem->tipe_unit,
                'qty_ordered'            => $poItem->qty,
                'qty_received'           => $poItem->qty_received,
                'qty_remaining'          => $remaining,
                'is_fully_received'      => $remaining <= 0,
            ];
        });

        $allReceived = $items->every(fn($i) => $i['is_fully_received']);

        return response()->json([
            'success'      => true,
            'data'         => $items,
            'all_received' => $allReceived,
            'po_status'    => $po->delivery_status,
        ]);
    }

    // POST /surat-jalan
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id'                       => 'required|exists:purchase_orders,id',
            'warehouse_id'                            => 'required|exists:warehouses,id',
            'vendor_name'                             => 'nullable|string|max:255',
            'driver_name'                             => 'nullable|string|max:255',
            'vehicle_plate'                           => 'nullable|string|max:50',
            'received_date'                           => 'required|date',
            'notes'                                   => 'nullable|string',
            'items'                                   => 'required|array|min:1',
            'items.*.purchase_order_item_id'          => 'required|exists:purchase_order_items,id',
            'items.*.item_id'                         => 'nullable|exists:items,id',
            'items.*.qty_received'                    => 'required|numeric|min:0.01',
            'items.*.masuk_stok'                      => 'boolean',
            'items.*.keterangan'                      => 'nullable|string',
        ]);

        $po = PurchaseOrder::with('items')->findOrFail($validated['purchase_order_id']);

        $sj = $this->suratJalanService->create($validated, $po, $request->user()->id);

        broadcast(new SuratJalanUpdated($sj->fresh(), 'created'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $sj->load('items.item'),
            'message' => 'Tanda Terima Barang berhasil disimpan.',
        ], 201);
    }

    // POST /surat-jalan/{suratJalan}/receive
    public function receive(Request $request, SuratJalan $suratJalan): JsonResponse
    {
        $validated = $request->validate([
            'received_by' => 'required|string|max:255',
            'notes'       => 'nullable|string',
        ]);

        $sj = $this->suratJalanService->markReceived($suratJalan, $validated, $request->user()->id);

        broadcast(new SuratJalanUpdated($sj->fresh(), 'received'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $sj->load('items.item'),
            'message' => 'Tanda Terima Barang berhasil dikonfirmasi penerimaan.',
        ]);
    }
}