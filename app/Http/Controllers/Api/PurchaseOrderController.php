<?php

namespace App\Http\Controllers\Api;

use App\Events\PurchaseOrderUpdated;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {}

    // GET /purchase-orders
    public function index(Request $request): JsonResponse
    {
        $data = $this->purchaseOrderService->list($request);

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

    // POST /purchase-orders
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'material_request_id'                 => 'nullable|exists:material_requests,id',
            'permintaan_material_ids'             => 'nullable|array|min:1',
            'permintaan_material_ids.*'           => 'exists:permintaan_material,id',
            'permintaan_material_id'              => 'nullable|exists:permintaan_material,id',
            'warehouse_id'                        => 'required|exists:warehouses,id',
            'vendor_name'                         => 'required|string|max:255',
            'vendor_contact'                      => 'nullable|string|max:255',
            'expected_date'                       => 'nullable|date',
            'notes'                               => 'nullable|string',
            'ppn_percent'                         => 'nullable|numeric|min:0|max:100',
            'diskon_persen'                       => 'nullable|numeric|min:0|max:100',
            'items'                               => 'required|array|min:1',
            'items.*.item_id'                     => 'nullable|exists:items,id',
            'items.*.permintaan_material_item_id' => 'nullable|exists:permintaan_material_items,id',
            'items.*.qty_pm'                      => 'nullable|numeric|min:0',
            'items.*.part_number'                 => 'nullable|string|max:100',
            'items.*.nama_barang'                 => 'required|string|max:255',
            'items.*.kode_unit'                   => 'nullable|string',
            'items.*.tipe_unit'                   => 'nullable|string',
            'items.*.qty'                         => 'required|numeric|min:0.01',
            'items.*.satuan'                      => 'required|string',
            'items.*.harga_satuan'                => 'nullable|numeric|min:0',
            'items.*.diskon_persen'               => 'nullable|numeric|min:0|max:100',
            'items.*.keterangan'                  => 'nullable|string',
        ]);

        $po = $this->purchaseOrderService->create($validated, $request->user()->id);

        broadcast(new PurchaseOrderUpdated($po->fresh(), 'created'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $po,
            'message' => 'Purchase Order berhasil dibuat',
        ], 201);
    }

    // GET /purchase-orders/{id}
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $purchaseOrder->load(
                'items.item',
                'items.permintaanMaterialItem',
                'warehouse',
                'creator',
                'materialRequest',
                'permintaanMaterials.items',
                'suratJalan'
            ),
        ]);
    }

    // POST /purchase-orders/{id}/send
    public function sendToVendor(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $po = $this->purchaseOrderService->sendToVendor($purchaseOrder);

        broadcast(new PurchaseOrderUpdated($po->fresh(), 'sent_to_vendor'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $po,
            'message' => 'PO dikirim ke vendor',
        ]);
    }
}