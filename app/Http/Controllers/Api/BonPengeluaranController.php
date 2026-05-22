<?php

namespace App\Http\Controllers\Api;

use App\Events\BonPengeluaranUpdated;
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

        broadcast(new BonPengeluaranUpdated($bon->fresh(), 'created'))->toOthers();

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
            'data'    => $bonPengeluaran->load('items.item', 'items.fifoLayers', 'warehouse', 'creator', 'approver', 'materialRequest', 'permintaanMaterial'),
        ]);
    }

    // PUT /bon-pengeluaran/{id}/items
    // Edit item bon (hanya saat draft atau rejected_by_mechanic)
    public function updateItems(Request $request, BonPengeluaran $bonPengeluaran): JsonResponse
    {
        $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.item_id'      => 'nullable|exists:items,id',
            'items.*.nama_barang'  => 'required|string|max:255',
            'items.*.qty'          => 'required|numeric|min:0.01',
            'items.*.satuan'       => 'required|string|max:50',
            'items.*.keterangan'   => 'nullable|string',
        ]);

        $bon = $this->bonService->updateItems($bonPengeluaran, $request->items);

        broadcast(new BonPengeluaranUpdated($bon->fresh(), 'updated'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $bon,
            'message' => 'Item bon berhasil diperbarui',
        ]);
    }

    // POST /bon-pengeluaran/{id}/request-confirmation
    // Admin sudah siapkan barang → minta konfirmasi mekanik
    public function requestConfirmation(Request $request, BonPengeluaran $bonPengeluaran): JsonResponse
    {
        $bon = $this->bonService->requestConfirmation($bonPengeluaran, $request->user()->id);

        broadcast(new BonPengeluaranUpdated($bon->fresh(), 'pending_confirmation'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $bon,
            'message' => 'Permintaan konfirmasi telah dikirim ke mekanik ' . ($bon->mechanic ?? ''),
        ]);
    }

    // POST /bon-pengeluaran/{id}/confirm
    // Mekanik konfirmasi barang sesuai
    public function confirmByMechanic(Request $request, BonPengeluaran $bonPengeluaran): JsonResponse
    {
        $request->validate([
            'confirmed_by' => 'required|string|max:150',
        ]);

        $bon = $this->bonService->confirmByMechanic(
            $bonPengeluaran,
            $request->confirmed_by
        );

        broadcast(new BonPengeluaranUpdated($bon->fresh(), 'confirmed'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $bon,
            'message' => 'Barang dikonfirmasi sesuai oleh ' . $request->confirmed_by . '. Admin dapat segera mengeluarkan barang.',
        ]);
    }

    // POST /bon-pengeluaran/{id}/reject-mechanic
    // Mekanik menolak — barang tidak sesuai
    public function rejectByMechanic(Request $request, BonPengeluaran $bonPengeluaran): JsonResponse
    {
        $request->validate([
            'reason'      => 'required|string|min:5|max:500',
            'rejected_by' => 'required|string|max:150',
        ]);

        $bon = $this->bonService->rejectByMechanic(
            $bonPengeluaran,
            $request->reason,
            $request->rejected_by
        );

        broadcast(new BonPengeluaranUpdated($bon->fresh(), 'rejected_by_mechanic'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $bon,
            'message' => 'Barang ditolak oleh mekanik. Admin perlu melakukan revisi.',
        ]);
    }

    // POST /bon-pengeluaran/{id}/revise
    // Admin revisi bon yang ditolak mekanik → kembali ke draft
    public function revise(Request $request, BonPengeluaran $bonPengeluaran): JsonResponse
    {
        $bon = $this->bonService->revise($bonPengeluaran);

        broadcast(new BonPengeluaranUpdated($bon->fresh(), 'revised'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $bon,
            'message' => 'Bon dikembalikan ke draft untuk direvisi.',
        ]);
    }

    // POST /bon-pengeluaran/{id}/issue
    // Admin keluarkan barang final → kurangi stok
    // Hanya bisa jika status = confirmed (atau draft untuk kasus darurat/auto_issue)
    public function issue(Request $request, BonPengeluaran $bonPengeluaran): JsonResponse
    {
        $bon = $this->bonService->approve($bonPengeluaran, $request->user()->id);

        broadcast(new BonPengeluaranUpdated($bon->fresh(), 'issued'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $bon,
            'message' => 'Bon Pengeluaran berhasil dikeluarkan, stok telah dikurangi',
        ]);
    }
}