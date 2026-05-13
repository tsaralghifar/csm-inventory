<?php

namespace App\Http\Controllers\Api;

use App\Events\ReturBarangUpdated;
use App\Http\Controllers\Controller;
use App\Models\ReturBarang;
use App\Services\ReturBarangService;
use Illuminate\Http\Request;

class ReturBarangController extends Controller
{
    public function __construct(protected ReturBarangService $service) {}

    // GET /retur-barang
    public function index(Request $request)
    {
        $data = $this->service->list($request);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => [
                'total'     => $data->total(),
                'page'      => $data->currentPage(),
                'last_page' => $data->lastPage(),
            ],
        ]);
    }

    // POST /retur-barang
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id'              => 'required|exists:purchase_orders,id',
            'warehouse_id'                   => 'required|exists:warehouses,id',
            'vendor_name'                    => 'required|string|max:255',
            'vendor_contact'                 => 'nullable|string|max:255',
            'retur_date'                     => 'required|date',
            'alasan'                         => 'nullable|string',
            'notes'                          => 'nullable|string',
            'items'                          => 'required|array|min:1',
            'items.*.item_id'                => 'nullable|exists:items,id',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.nama_barang'            => 'required|string|max:255',
            'items.*.part_number'            => 'nullable|string|max:100',
            'items.*.kode_unit'              => 'nullable|string|max:100',
            'items.*.tipe_unit'              => 'nullable|string|max:100',
            'items.*.qty'                    => 'required|numeric|min:0.01',
            'items.*.satuan'                 => 'required|string|max:50',
            'items.*.harga_satuan'           => 'nullable|numeric|min:0',
            'items.*.jenis'                  => 'required|in:returnable,non_returnable',
            'items.*.alasan_item'            => 'nullable|string',
        ]);

        $retur = $this->service->store($validated, $request->user()->id);

        broadcast(new ReturBarangUpdated($retur->fresh(), 'created'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $retur,
            'message' => 'Retur berhasil dibuat',
        ], 201);
    }

    // GET /retur-barang/{id}
    public function show(ReturBarang $returBarang)
    {
        return response()->json([
            'success' => true,
            'data'    => $returBarang->load(
                'items.item',
                'items.purchaseOrderItem',
                'purchaseOrder.items',
                'warehouse',
                'creator',
                'confirmer'
            ),
        ]);
    }

    // POST /retur-barang/{id}/confirm
    public function confirm(ReturBarang $returBarang, Request $request)
    {
        $retur = $this->service->confirm($returBarang, $request->user()->id);

        broadcast(new ReturBarangUpdated($retur->fresh(), 'confirmed'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $retur,
            'message' => 'Retur berhasil dikonfirmasi. Stok telah diperbarui.',
        ]);
    }
}