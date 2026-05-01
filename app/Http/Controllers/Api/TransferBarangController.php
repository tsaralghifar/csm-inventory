<?php

namespace App\Http\Controllers\Api;

use App\Events\TransferBarangUpdated;
use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Services\TransferBarangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferBarangController extends Controller
{
    public function __construct(
        private readonly TransferBarangService $transferService
    ) {}

    // GET /transfer-barang
    public function index(Request $request): JsonResponse
    {
        $data = $this->transferService->list($request, $request->user());

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /transfer-barang
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id'  => 'required|exists:warehouses,id',
            'to_warehouse_id'    => 'required|exists:warehouses,id|different:from_warehouse_id',
            'needed_date'        => 'nullable|date',
            'notes'              => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|exists:items,id',
            'items.*.qty'        => 'required|numeric|min:0.01',
            'items.*.keterangan' => 'nullable|string',
        ]);

        $mr = DB::transaction(function () use ($validated, $request) {
            $mr = MaterialRequest::create([
                'mr_number'         => MaterialRequest::generateNumber(),
                'type'              => 'transfer',
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id'   => $validated['to_warehouse_id'],
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
                    'notes'               => $item['keterangan'] ?? null,
                ]);
            }

            return $mr->load('items.item', 'fromWarehouse', 'toWarehouse', 'requester');
        });

        return tap(
            response()->json(['success' => true, 'data' => $mr, 'message' => 'MR Transfer berhasil dibuat'], 201),
            fn() => broadcast(new TransferBarangUpdated($mr, 'created'))->toOthers()
        );
    }

    // GET /transfer-barang/{mr}
    public function show(MaterialRequest $mr): JsonResponse
    {
        if ($mr->type !== 'transfer') {
            return response()->json(['success' => false, 'message' => 'Bukan MR Transfer'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $mr->load([
                'fromWarehouse', 'toWarehouse',
                'requester', 'approver', 'atasanApprover',
                'items.item.category',
                'deliveryOrders.items.item',
                'deliveryOrders.fromWarehouse',
                'deliveryOrders.toWarehouse',
                'deliveryOrders.sender',
                'deliveryOrders.receiver',
            ]),
        ]);
    }

    // POST /transfer-barang/{mr}/submit
    public function submit(Request $request, MaterialRequest $mr): JsonResponse
    {
        $this->assertTransfer($mr);

        if ($mr->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya MR draft yang bisa disubmit']);
        }

        $mr->update(['status' => 'pending_admin', 'submitted_at' => now()]);

        return tap(
            response()->json(['success' => true, 'data' => $mr->fresh(), 'message' => 'MR disubmit, menunggu persetujuan Admin']),
            fn() => broadcast(new TransferBarangUpdated($mr->fresh(), 'submitted'))->toOthers()
        );
    }

    // POST /transfer-barang/{mr}/approve-admin
    public function approveAdmin(Request $request, MaterialRequest $mr): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('approve-mr')) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        }

        $this->assertTransfer($mr);

        if ($mr->status !== 'pending_admin') {
            throw ValidationException::withMessages(['status' => 'MR tidak dalam status menunggu Admin']);
        }

        $validated = $request->validate([
            'items'                => 'required|array',
            'items.*.id'           => 'required|exists:material_request_items,id',
            'items.*.qty_approved' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($mr, $validated, $request) {
            foreach ($validated['items'] as $ai) {
                $mrItem = MaterialRequestItem::find($ai['id']);
                if (!$mrItem || $mrItem->material_request_id !== $mr->id) continue;

                $qtyApproved = (float) $ai['qty_approved'];
                $mrItem->update(['qty_approved' => $qtyApproved]);

                if ($qtyApproved > 0) {
                    $stock = \App\Models\ItemStock::where('item_id', $mrItem->item_id)
                        ->where('warehouse_id', $mr->from_warehouse_id)
                        ->first();

                    $available = $stock ? ($stock->qty - $stock->qty_reserved) : 0;

                    if (!$stock || $available < $qtyApproved) {
                        throw ValidationException::withMessages([
                            'stock' => "Stok tidak cukup untuk item ID {$mrItem->item_id} di gudang asal (tersedia: {$available}, diminta: {$qtyApproved})",
                        ]);
                    }

                    $stock->increment('qty_reserved', $qtyApproved);
                }
            }

            $mr->update([
                'status'      => 'pending_atasan',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
        });

        return tap(
            response()->json(['success' => true, 'data' => $mr->fresh(), 'message' => 'Disetujui Admin, menunggu persetujuan Atasan']),
            fn() => broadcast(new TransferBarangUpdated($mr->fresh(), 'approved_admin'))->toOthers()
        );
    }

    // POST /transfer-barang/{mr}/approve-atasan
    public function approveAtasan(Request $request, MaterialRequest $mr): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('approve-mr-manager')) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        }

        $this->assertTransfer($mr);

        if ($mr->status !== 'pending_atasan') {
            throw ValidationException::withMessages(['status' => 'MR tidak dalam status menunggu Atasan']);
        }

        $mr->update([
            'status'             => 'approved',
            'atasan_approved_by' => $request->user()->id,
            'atasan_approved_at' => now(),
        ]);

        return tap(
            response()->json(['success' => true, 'data' => $mr->fresh(), 'message' => 'Disetujui Atasan, siap untuk pengiriman']),
            fn() => broadcast(new TransferBarangUpdated($mr->fresh(), 'approved_atasan'))->toOthers()
        );
    }

    // POST /transfer-barang/{mr}/kirim
    public function kirim(Request $request, MaterialRequest $mr): JsonResponse
    {
        if (!$request->user()->hasPermissionTo('dispatch-mr')) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        }

        $this->assertTransfer($mr);

        $validated = $request->validate([
            'driver_name'      => 'nullable|string|max:255',
            'vehicle_plate'    => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|exists:material_request_items,id',
            'items.*.qty_sent' => 'required|numeric|min:0.01',
        ]);

        // Tambahkan driver/vehicle ke validated agar tersedia di service
        $validated['driver_name']   = $validated['driver_name'] ?? null;
        $validated['vehicle_plate'] = $validated['vehicle_plate'] ?? null;

        $do = $this->transferService->kirim($validated, $mr, $request->user()->id);

        return tap(
            response()->json(['success' => true, 'data' => $do, 'message' => "Barang dikirim, Surat Jalan {$do->do_number} dibuat"], 201),
            fn() => broadcast(new TransferBarangUpdated($mr->fresh(), 'dispatched'))->toOthers()
        );
    }

    // POST /transfer-barang/delivery/{do}/terima
    public function terima(Request $request, DeliveryOrder $do): JsonResponse
    {
        $validated = $request->validate([
            'received_by_name'     => 'required|string|max:255',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.id'           => 'required|exists:delivery_order_items,id',
            'items.*.qty_received' => 'required|numeric|min:0',
        ]);

        $this->transferService->terima($validated, $do, $request->user()->id);

        return tap(
            response()->json(['success' => true, 'message' => 'Barang dikonfirmasi diterima, stok gudang tujuan bertambah']),
            function () use ($do) {
                if ($do->material_request_id) {
                    $mr = MaterialRequest::find($do->material_request_id);
                    if ($mr) broadcast(new TransferBarangUpdated($mr, 'received'))->toOthers();
                }
            }
        );
    }

    // POST /transfer-barang/{mr}/reject
    public function reject(Request $request, MaterialRequest $mr): JsonResponse
    {
        $this->assertTransfer($mr);
        $request->validate(['reason' => 'required|string|min:5']);

        $mr = $this->transferService->reject($mr, $request->reason, $request->user()->id);

        return tap(
            response()->json(['success' => true, 'data' => $mr, 'message' => 'MR Transfer ditolak']),
            fn() => broadcast(new TransferBarangUpdated($mr->fresh(), 'rejected'))->toOthers()
        );
    }

    // DELETE /transfer-barang/{mr}
    public function destroy(MaterialRequest $mr): JsonResponse
    {
        $this->assertTransfer($mr);

        if ($mr->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya MR draft yang bisa dihapus']);
        }

        $mr->delete();

        return response()->json(['success' => true, 'message' => 'MR Transfer dihapus']);
    }

    private function assertTransfer(MaterialRequest $mr): void
    {
        if ($mr->type !== 'transfer') {
            abort(404, 'Bukan MR Transfer');
        }
    }
}