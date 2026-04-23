<?php

namespace App\Http\Controllers\Api;

use App\Events\MaterialRequestUpdated;
use App\Http\Controllers\Controller;
use App\Models\MaterialRequest;
use App\Services\MaterialRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaterialRequestController extends Controller
{
    public function __construct(private MaterialRequestService $mrService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = MaterialRequest::with(['fromWarehouse', 'toWarehouse', 'requester', 'approver',
                    'chiefAuthorizer', 'managerApprover'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if (!$user->isSuperuser() && !$user->isAdminHO()) {
            $query->where(fn($q) => $q
                ->where('from_warehouse_id', $user->warehouse_id)
                ->orWhere('to_warehouse_id', $user->warehouse_id));
        }

        if ($request->status)            $query->where('status', $request->status);
        if ($request->type)              $query->where('type', $request->type);
        if ($request->from_warehouse_id) $query->where('from_warehouse_id', $request->from_warehouse_id);
        if ($request->to_warehouse_id)   $query->where('to_warehouse_id', $request->to_warehouse_id);
        if ($request->date_from)         $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)           $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->search)            $query->where('mr_number', 'ilike', "%{$request->search}%");

        $mrs = $query->paginate($request->per_page ?? 15);
        return response()->json([
            'success' => true,
            'data'    => $mrs->items(),
            'meta'    => ['total' => $mrs->total(), 'page' => $mrs->currentPage(), 'last_page' => $mrs->lastPage()],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'              => 'required|in:part,office',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id'   => 'required|exists:warehouses,id|different:from_warehouse_id',
            'needed_date'       => 'nullable|date|after_or_equal:today',
            'notes'             => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.item_id'   => 'required|exists:items,id',
            'items.*.qty'       => 'required|numeric|min:0.01',
            'items.*.notes'     => 'nullable|string',
        ]);

        $mr = $this->mrService->create($validated, $request->user()->id);
        broadcast(new MaterialRequestUpdated($mr, 'created'))->toOthers();
        return response()->json(['success' => true, 'data' => $mr, 'message' => 'Material Request berhasil dibuat'], 201);
    }

    public function show(MaterialRequest $materialRequest)
    {
        $materialRequest->load([
            'fromWarehouse', 'toWarehouse', 'requester', 'approver',
            'chiefAuthorizer', 'managerApprover',
            'items.item.category', 'deliveryOrders.items.item',
            'purchaseOrders.items', 'bonPengeluaran.items.item', 'suratJalan.items',
        ]);
        return response()->json(['success' => true, 'data' => $materialRequest]);
    }

    public function submit(Request $request, MaterialRequest $mr)
    {
        if ($mr->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya MR draft yang bisa disubmit']);
        }
        $nextStatus = $mr->type === 'part' ? 'pending_chief' : 'pending_ho';
        $mr->update(['status' => $nextStatus, 'submitted_at' => now()]);
        broadcast(new MaterialRequestUpdated($mr->fresh(), 'submitted'))->toOthers();
        return response()->json(['success' => true, 'data' => $mr,
            'message' => $mr->type === 'part'
                ? 'MR disubmit, menunggu otorisasi Chief Mekanik'
                : 'MR disubmit, menunggu approval Admin HO']);
    }

    public function authorizeChief(Request $request, MaterialRequest $mr)
    {
        if ($mr->type !== 'part') {
            throw ValidationException::withMessages(['type' => 'Hanya MR Part yang perlu otorisasi Chief Mekanik']);
        }
        if ($mr->status !== 'pending_chief') {
            throw ValidationException::withMessages(['status' => 'MR tidak dalam status menunggu Chief Mekanik']);
        }
        $mr->update([
            'status'              => 'pending_manager',
            'chief_authorized_by' => $request->user()->id,
            'chief_authorized_at' => now(),
        ]);
        broadcast(new MaterialRequestUpdated($mr->fresh(), 'authorized_chief'))->toOthers();
        return response()->json(['success' => true, 'data' => $mr,
            'message' => 'MR diotorisasi Chief Mekanik, diteruskan ke Manager']);
    }

    public function approveManager(Request $request, MaterialRequest $mr)
    {
        if ($mr->type !== 'part') {
            throw ValidationException::withMessages(['type' => 'Hanya MR Part yang perlu approval Manager']);
        }
        if ($mr->status !== 'pending_manager') {
            throw ValidationException::withMessages(['status' => 'MR tidak dalam status menunggu Manager']);
        }
        $mr->update([
            'status'              => 'manager_approved',
            'manager_approved_by' => $request->user()->id,
            'manager_approved_at' => now(),
        ]);
        broadcast(new MaterialRequestUpdated($mr->fresh(), 'approved_manager'))->toOthers();
        return response()->json(['success' => true, 'data' => $mr,
            'message' => 'MR disetujui Manager, silakan cek ketersediaan stok']);
    }

    public function approveHO(Request $request, MaterialRequest $mr)
    {
        $validated = $request->validate([
            'items'                => 'required|array',
            'items.*.id'           => 'required|exists:material_request_items,id',
            'items.*.qty_approved' => 'required|numeric|min:0',
        ]);
        $allowedStatuses = $mr->type === 'office' ? ['pending_ho'] : ['manager_approved'];
        if (!in_array($mr->status, $allowedStatuses)) {
            throw ValidationException::withMessages(['status' => 'Status MR tidak valid untuk di-approve']);
        }
        $mr = $this->mrService->approve($mr, $validated['items'], $request->user()->id);
        broadcast(new MaterialRequestUpdated($mr->fresh(), 'approved_ho'))->toOthers();
        return response()->json(['success' => true, 'data' => $mr, 'message' => 'MR berhasil diapprove']);
    }

    public function reject(Request $request, MaterialRequest $mr)
    {
        $request->validate(['reason' => 'required|string|min:5']);
        $rejectableStatuses = ['pending_chief', 'pending_manager', 'pending_ho', 'submitted', 'manager_approved'];
        if (!in_array($mr->status, $rejectableStatuses)) {
            throw ValidationException::withMessages(['status' => 'MR tidak bisa ditolak dari status: ' . $mr->status]);
        }
        $mr = $this->mrService->reject($mr, $request->reason, $request->user()->id);
        broadcast(new MaterialRequestUpdated($mr->fresh(), 'rejected'))->toOthers();
        return response()->json(['success' => true, 'data' => $mr, 'message' => 'MR ditolak']);
    }

    public function dispatchMR(Request $request, MaterialRequest $mr)
    {
        $this->authorize('dispatch-mr');
        $validated = $request->validate([
            'driver_name'   => 'nullable|string',
            'vehicle_plate' => 'nullable|string',
            'notes'         => 'nullable|string',
            'items'         => 'nullable|array',
        ]);
        $do = $this->mrService->dispatch($mr, $validated, $request->user()->id);
        broadcast(new MaterialRequestUpdated($mr->fresh(), 'dispatched'))->toOthers();
        return response()->json(['success' => true, 'data' => $do,
            'message' => 'Barang berhasil dikirim, DO dibuat: ' . $do->do_number]);
    }
}