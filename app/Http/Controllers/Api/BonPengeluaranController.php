<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BonPengeluaran;
use App\Services\BonPengeluaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonPengeluaranController extends Controller
{
    public function __construct(
        private readonly BonPengeluaranService $bonService
    ) {}

    // GET /bon-pengeluaran
    public function index(Request $request): JsonResponse
    {
        $data = $this->bonService->list($request);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /bon-pengeluaran
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'material_request_id'    => 'nullable|exists:material_requests,id',
            'permintaan_material_id' => 'nullable|exists:permintaan_material,id',
            'warehouse_id'           => 'required|exists:warehouses,id',
            'received_by'            => 'required|string|max:255',
            'issue_date'             => 'required|date',
            'notes'                  => 'nullable|string',
            'unit_code'              => 'nullable|string|max:50',
            'unit_type'              => 'nullable|string|max:100',
            'hm_km'                  => 'nullable|numeric',
            'mechanic'               => 'nullable|string|max:150',
            'po_number'              => 'nullable|string|max:100',
            'auto_issue'             => 'nullable|boolean',
            'items'                  => 'required|array|min:1',
            'items.*.item_id'        => 'nullable|exists:items,id',
            'items.*.nama_barang'    => 'required|string|max:255',
            'items.*.qty'            => 'required|numeric|min:0.01',
            'items.*.satuan'         => 'required|string|max:50',
            'items.*.keterangan'     => 'nullable|string',
        ]);

        $bon = $this->bonService->create($validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => $bon,
            'message' => 'Bon Pengeluaran berhasil dibuat',
        ], 201);
    }

    // GET /bon-pengeluaran/{id}
    public function show(BonPengeluaran $bonPengeluaran): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $bonPengeluaran->load('items.item', 'warehouse', 'creator', 'approver', 'materialRequest', 'permintaanMaterial'),
        ]);
    }

    // POST /bon-pengeluaran/{id}/issue
    public function issue(Request $request, BonPengeluaran $bonPengeluaran): JsonResponse
    {
        $bon = $this->bonService->approve($bonPengeluaran, $request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => $bon,
            'message' => 'Bon Pengeluaran berhasil dikeluarkan, stok telah dikurangi',
        ]);
    }
}