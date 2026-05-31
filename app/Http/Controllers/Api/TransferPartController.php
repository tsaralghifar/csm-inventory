<?php

namespace App\Http\Controllers\Api;

use App\Events\TransferBarangUpdated;
use App\Http\Controllers\Controller;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfer Part Darurat
 *
 * Alur: draft → pending_chief → pending_manager → approved → (eksekusi fisik) → selesai
 * Part dilepas dari unit asal dan dikirim ke unit/site tujuan karena urgent.
 * PO pengganti dibuat terpisah dan di-link via linked_mr_transfer_id.
 */
class TransferPartController extends Controller
{
    public function __construct(private readonly StockService $stockService) {}

    // ────────────────────────────────────────────────────────────────────────
    // GET /transfer-part
    // ────────────────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();

        if (!$user->hasPermissionTo('view-transfer-part')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $query = MaterialRequest::with(['fromWarehouse', 'toWarehouse', 'requester',
                    'chiefAuthorizer', 'managerApprover', 'linkedPo'])
            ->where('type', 'transfer_part')
            ->latest();

        // Filter per gudang sesuai akses user
        if (!$user->isSuperuser() && !$user->isAdminHO() && !$user->isLogistikHO()) {
            $query->where(function ($q) use ($user) {
                $q->where('from_warehouse_id', $user->warehouse_id)
                  ->orWhere('to_warehouse_id', $user->warehouse_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('mr_number', 'ilike', '%' . $request->search . '%');
        }

        $data = $query->paginate($request->input('per_page', 15));

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

    // ────────────────────────────────────────────────────────────────────────
    // POST /transfer-part
    // ────────────────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('create-transfer-part')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'unit_from_kode'    => 'required|string|max:100',
            'unit_from_tipe'    => 'nullable|string|max:100',
            'unit_to_kode'      => 'required|string|max:100',
            'unit_to_tipe'      => 'nullable|string|max:100',
            'alasan_urgent'     => 'required|string|min:10',
            'needed_date'       => 'nullable|date',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.item_id'   => 'required|exists:items,id',
            'items.*.qty'       => 'required|numeric|min:0.01',
            'items.*.satuan'    => 'required|string|max:50',
            'items.*.keterangan'=> 'nullable|string',
        ]);

        $mr = DB::transaction(function () use ($validated, $request) {
            $mr = MaterialRequest::create([
                'mr_number'         => $this->generateNumber(),
                'type'              => 'transfer_part',
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id'   => $validated['to_warehouse_id'],
                'unit_from_kode'    => $validated['unit_from_kode'],
                'unit_from_tipe'    => $validated['unit_from_tipe'] ?? null,
                'unit_to_kode'      => $validated['unit_to_kode'],
                'unit_to_tipe'      => $validated['unit_to_tipe'] ?? null,
                'alasan_urgent'     => $validated['alasan_urgent'],
                'status'            => 'draft',
                'requested_by'      => $request->user()->id,
                'notes'             => $validated['notes'] ?? null,
                'needed_date'       => $validated['needed_date'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                MaterialRequestItem::create([
                    'material_request_id' => $mr->id,
                    'item_id'             => $item['item_id'],
                    'qty_request'         => $item['qty'],
                    'satuan'              => $item['satuan'],
                    'notes'               => $item['keterangan'] ?? null,
                    'kode_unit'           => $validated['unit_from_kode'],
                    'tipe_unit'           => $validated['unit_from_tipe'] ?? null,
                ]);
            }

            return $mr->load('items.item', 'fromWarehouse', 'toWarehouse', 'requester');
        });

        return response()->json([
            'success' => true,
            'data'    => $mr,
            'message' => "Transfer Part Darurat {$mr->mr_number} berhasil dibuat",
        ], 201);
    }

    // ────────────────────────────────────────────────────────────────────────
    // GET /transfer-part/{mr}
    // ────────────────────────────────────────────────────────────────────────
    public function show(MaterialRequest $mr): JsonResponse
    {
        $this->assertTransferPart($mr);

        return response()->json([
            'success' => true,
            'data'    => $mr->load([
                'fromWarehouse', 'toWarehouse',
                'requester', 'chiefAuthorizer', 'managerApprover',
                'items.item.category',
                'linkedPo.items',
                'linkedPo.supplier',
            ]),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /transfer-part/{mr}/submit
    // ────────────────────────────────────────────────────────────────────────
    public function submit(Request $request, MaterialRequest $mr): JsonResponse
    {
        $this->assertTransferPart($mr);

        if ($mr->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya draft yang bisa disubmit']);
        }

        $mr->update(['status' => 'pending_chief', 'submitted_at' => now()]);

        return response()->json([
            'success' => true,
            'data'    => $mr->fresh(),
            'message' => 'Disubmit, menunggu persetujuan Chief Mekanik',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /transfer-part/{mr}/approve-chief
    // ────────────────────────────────────────────────────────────────────────
    public function approveChief(Request $request, MaterialRequest $mr): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('approve-transfer-part-chief')) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        }

        $this->assertTransferPart($mr);

        if ($mr->status !== 'pending_chief') {
            throw ValidationException::withMessages(['status' => 'MR tidak dalam status menunggu Chief Mekanik']);
        }

        $mr->update([
            'status'               => 'pending_manager',
            'chief_authorized_by'  => $request->user()->id,
            'chief_authorized_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $mr->fresh(),
            'message' => 'Disetujui Chief Mekanik, menunggu persetujuan Manager',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /transfer-part/{mr}/approve-manager
    // ────────────────────────────────────────────────────────────────────────
    public function approveManager(Request $request, MaterialRequest $mr): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('approve-transfer-part-manager')) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        }

        $this->assertTransferPart($mr);

        if ($mr->status !== 'pending_manager') {
            throw ValidationException::withMessages(['status' => 'MR tidak dalam status menunggu Manager']);
        }

        $mr->update([
            'status'               => 'approved',
            'manager_approved_by'  => $request->user()->id,
            'manager_approved_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $mr->fresh(),
            'message' => 'Disetujui Manager, siap dieksekusi',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /transfer-part/{mr}/reject
    // ────────────────────────────────────────────────────────────────────────
    public function reject(Request $request, MaterialRequest $mr): JsonResponse
    {
        $this->assertTransferPart($mr);
        $request->validate(['reason' => 'required|string|min:5']);

        if (!in_array($mr->status, ['pending_chief', 'pending_manager'])) {
            throw ValidationException::withMessages(['status' => 'Status tidak bisa ditolak']);
        }

        $mr->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $mr->fresh(),
            'message' => 'Transfer Part Darurat ditolak',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /transfer-part/{mr}/link-po
    // Link PO pengganti ke transfer ini (dipanggil dari form PO)
    // ────────────────────────────────────────────────────────────────────────
    public function linkPo(Request $request, MaterialRequest $mr): JsonResponse
    {
        $this->assertTransferPart($mr);

        $validated = $request->validate([
            'po_id' => 'required|exists:purchase_orders,id',
        ]);

        $po = PurchaseOrder::findOrFail($validated['po_id']);

        DB::transaction(function () use ($mr, $po) {
            // Link dari sisi MR
            $mr->update(['linked_po_id' => $po->id]);

            // Link dari sisi PO
            $po->update(['linked_mr_transfer_id' => $mr->id]);
        });

        return response()->json([
            'success' => true,
            'data'    => $mr->fresh(['linkedPo']),
            'message' => "PO {$po->po_number} berhasil di-link sebagai pengganti",
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // DELETE /transfer-part/{mr}
    // ────────────────────────────────────────────────────────────────────────
    public function destroy(MaterialRequest $mr): JsonResponse
    {
        $this->assertTransferPart($mr);

        if ($mr->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya draft yang bisa dihapus']);
        }

        $mr->delete();

        return response()->json(['success' => true, 'message' => 'Transfer Part dihapus']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // GET /transfer-part/unlinked
    // Daftar MR transfer_part approved yang belum punya PO pengganti
    // (dipakai dropdown di form PO)
    // ────────────────────────────────────────────────────────────────────────
    public function unlinked(Request $request): JsonResponse
    {
        $data = MaterialRequest::with(['fromWarehouse', 'toWarehouse', 'items.item'])
            ->where('type', 'transfer_part')
            ->where('status', 'approved')
            ->whereNull('linked_po_id')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // GET /transfer-part/unlinked-pm
    // Daftar TP approved yang belum punya PM pengganti
    // (dipakai dropdown di form Buat Permintaan Material)
    // ────────────────────────────────────────────────────────────────────────
    public function unlinkedPm(Request $request): JsonResponse
    {
        $data = MaterialRequest::with(['fromWarehouse', 'toWarehouse', 'items.item'])
            ->where('type', 'transfer_part')
            ->where('status', 'approved')
            ->whereNull('linked_pm_id')
            ->latest()
            ->get()
            ->map(fn($tp) => [
                'id'             => $tp->id,
                'mr_number'      => $tp->mr_number,
                'unit_from_kode' => $tp->unit_from_kode,
                'unit_from_tipe' => $tp->unit_from_tipe,
                'unit_to_kode'   => $tp->unit_to_kode,
                'unit_to_tipe'   => $tp->unit_to_tipe,
                'alasan_urgent'  => $tp->alasan_urgent,
                'from_warehouse' => $tp->fromWarehouse?->only(['id', 'name']),
                'to_warehouse'   => $tp->toWarehouse?->only(['id', 'name']),
                'items'          => $tp->items->map(fn($i) => [
                    'item_id'    => $i->item_id,
                    'nama_barang'=> $i->item?->name ?? '',
                    'part_number'=> $i->item?->part_number ?? '',
                    'qty'        => $i->qty_request,
                    'satuan'     => $i->satuan,
                    'kode_unit'  => $i->kode_unit ?? $tp->unit_from_kode ?? '',
                    'tipe_unit'  => $i->tipe_unit ?? $tp->unit_from_tipe ?? '',
                ]),
                'created_at'     => $tp->created_at,
            ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    private function assertTransferPart(MaterialRequest $mr): void
    {
        if ($mr->type !== 'transfer_part') {
            abort(404, 'Bukan MR Transfer Part');
        }
    }

    private function generateNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = "TP-{$date}-";

        $last = MaterialRequest::lockForUpdate()
            ->where('mr_number', 'like', "{$prefix}%")
            ->where('type', 'transfer_part')
            ->orderByRaw('CAST(SUBSTRING(mr_number FROM ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('mr_number');

        $lastNumber = $last ? (int) substr($last, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }
}