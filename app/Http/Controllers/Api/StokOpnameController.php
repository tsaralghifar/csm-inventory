<?php

namespace App\Http\Controllers\Api;

use App\Events\StokOpnameUpdated;
use App\Http\Controllers\Controller;
use App\Models\StokOpname;
use App\Services\StockService;
use App\Services\StokOpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StokOpnameController extends Controller
{
    public function __construct(
        private readonly StokOpnameService $stokOpnameService,
        private readonly StockService $stockService
    ) {}

    // GET /stok-opname
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = StokOpname::with(['warehouse', 'dibuatOleh', 'disetujuiOleh'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if (!$user->isSuperuser() && !$user->isAdminHO() && !$user->isLogistikHO()) {
            $query->where('warehouse_id', $user->warehouse_id);
        }

        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->status)       $query->where('status', $request->status);
        if ($request->date_from)    $query->whereDate('tanggal_opname', '>=', $request->date_from);
        if ($request->date_to)      $query->whereDate('tanggal_opname', '<=', $request->date_to);
        if ($request->search)       $query->where('nomor', 'ilike', "%{$request->search}%");

        $data = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /stok-opname
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'       => 'required|exists:warehouses,id',
            'tipe'               => 'required|string|max:100',
            'no_referensi'       => 'required|string|max:100',
            'keterangan'         => 'nullable|string',
            'tanggal_opname'     => 'required|date',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|exists:items,id',
            'items.*.qty_fisik'  => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ]);

        $opname = $this->stokOpnameService->create($validated, $request->user()->id);

        broadcast(new StokOpnameUpdated($opname->fresh(), 'created'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $opname,
            'message' => "Dokumen {$opname->nomor} berhasil dibuat",
        ], 201);
    }

    // GET /stok-opname/{id}
    public function show(StokOpname $stokOpname): JsonResponse
    {
        $stokOpname->load(['warehouse', 'dibuatOleh', 'disetujuiOleh', 'items.item.category']);
        return response()->json(['success' => true, 'data' => $stokOpname]);
    }

    // PUT /stok-opname/{id}
    public function update(Request $request, StokOpname $stokOpname): JsonResponse
    {
        $validated = $request->validate([
            'tipe'               => 'required|string|max:100',
            'no_referensi'       => 'required|string|max:100',
            'keterangan'         => 'nullable|string',
            'tanggal_opname'     => 'required|date',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|exists:items,id',
            'items.*.qty_fisik'  => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ]);

        $opname = $this->stokOpnameService->update($stokOpname, $validated);

        broadcast(new StokOpnameUpdated($opname->fresh(), 'updated'))->toOthers();

        return response()->json(['success' => true, 'data' => $opname, 'message' => 'Dokumen berhasil diperbarui']);
    }

    // POST /stok-opname/{id}/ajukan
    public function ajukan(StokOpname $stokOpname, Request $request): JsonResponse
    {
        if ($stokOpname->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya dokumen draft yang bisa diajukan'], 422);
        }

        if ($stokOpname->dibuat_oleh !== $request->user()->id && !$request->user()->isSuperuser()) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak mengajukan dokumen ini'], 403);
        }

        $stokOpname->update(['status' => 'menunggu_approval', 'diajukan_at' => now()]);

        broadcast(new StokOpnameUpdated($stokOpname->fresh(), 'ajukan'))->toOthers();

        return response()->json(['success' => true, 'message' => "Dokumen {$stokOpname->nomor} diajukan untuk persetujuan"]);
    }

    // POST /stok-opname/{id}/setujui
    public function setujui(StokOpname $stokOpname, Request $request): JsonResponse
    {
        if (!$request->user()->isSuperuser() && !$request->user()->isAdminHO() && !$request->user()->isLogistikHO()) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak menyetujui dokumen ini'], 403);
        }

        $opname = $this->stokOpnameService->approve($stokOpname, $this->stockService, $request->user()->id);

        broadcast(new StokOpnameUpdated($opname->fresh(), 'setujui'))->toOthers();

        return response()->json(['success' => true, 'data' => $opname, 'message' => "Dokumen {$opname->nomor} disetujui dan stok sudah disesuaikan"]);
    }

    // POST /stok-opname/{id}/tolak
    public function tolak(StokOpname $stokOpname, Request $request): JsonResponse
    {
        if ($stokOpname->status !== 'menunggu_approval') {
            return response()->json(['success' => false, 'message' => 'Dokumen tidak dalam status menunggu approval'], 422);
        }

        if (!$request->user()->isSuperuser() && !$request->user()->isAdminHO() && !$request->user()->isLogistikHO()) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak menolak dokumen ini'], 403);
        }

        $request->validate(['alasan_penolakan' => 'required|string|min:5']);

        $stokOpname->update([
            'status'           => 'ditolak',
            'alasan_penolakan' => $request->alasan_penolakan,
            'disetujui_oleh'   => $request->user()->id,
            'disetujui_at'     => now(),
        ]);

        broadcast(new StokOpnameUpdated($stokOpname->fresh(), 'tolak'))->toOthers();

        return response()->json(['success' => true, 'message' => "Dokumen {$stokOpname->nomor} ditolak"]);
    }

    // DELETE /stok-opname/{id}
    public function destroy(StokOpname $stokOpname, Request $request): JsonResponse
    {
        if ($stokOpname->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya dokumen draft yang bisa dihapus'], 422);
        }

        if ($stokOpname->dibuat_oleh !== $request->user()->id && !$request->user()->isSuperuser()) {
            return response()->json(['success' => false, 'message' => 'Tidak berhak menghapus dokumen ini'], 403);
        }

        $nomor = $stokOpname->nomor;
        $warehouseId = $stokOpname->warehouse_id;

        // Buat dummy object untuk broadcast sebelum dihapus
        $payload = (object) ['id' => $stokOpname->id, 'nomor' => $nomor, 'status' => 'deleted', 'warehouse_id' => $warehouseId];

        $stokOpname->delete();

        // Broadcast manual karena model sudah dihapus
        broadcast(new StokOpnameUpdated(new StokOpname(['id' => $payload->id, 'nomor' => $payload->nomor, 'status' => $payload->status, 'warehouse_id' => $payload->warehouse_id]), 'deleted'))->toOthers();

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil dihapus']);
    }
}