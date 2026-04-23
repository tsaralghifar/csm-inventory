<?php

namespace App\Http\Controllers\Api;

use App\Events\TransferBarangUpdated;
use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\ItemStock;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferBarangController extends Controller
{
    // GET /transfer-barang
    public function index(Request $request)
    {
        $user  = $request->user();

        $query = MaterialRequest::with(['fromWarehouse', 'toWarehouse', 'requester', 'approver', 'atasanApprover'])
            ->withCount('items')
            ->where('type', 'transfer')
            ->orderBy('created_at', 'desc');

        if (!$user->isSuperuser() && !$user->isAdminHO()) {
            $query->where(fn($q) => $q
                ->where('from_warehouse_id', $user->warehouse_id)
                ->orWhere('to_warehouse_id', $user->warehouse_id));
        }

        if ($request->status)            $query->where('status', $request->status);
        if ($request->from_warehouse_id) $query->where('from_warehouse_id', $request->from_warehouse_id);
        if ($request->to_warehouse_id)   $query->where('to_warehouse_id', $request->to_warehouse_id);
        if ($request->date_from)         $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)           $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->search)            $query->where('mr_number', 'ilike', "%{$request->search}%");

        $data = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /transfer-barang
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_warehouse_id'   => 'required|exists:warehouses,id',
            'to_warehouse_id'     => 'required|exists:warehouses,id|different:from_warehouse_id',
            'needed_date'         => 'nullable|date',
            'notes'               => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.item_id'     => 'required|exists:items,id',
            'items.*.qty'         => 'required|numeric|min:0.01',
            'items.*.keterangan'  => 'nullable|string',
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

        return tap(response()->json(['success' => true, 'data' => $mr, 'message' => 'MR Transfer berhasil dibuat'], 201), function () use ($mr) {
            broadcast(new TransferBarangUpdated($mr, 'created'))->toOthers();
        });
    }

    // GET /transfer-barang/{mr}
    public function show(MaterialRequest $mr)
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
    // draft → pending_admin
    public function submit(Request $request, MaterialRequest $mr)
    {
        $this->assertTransfer($mr);
        if ($mr->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya MR draft yang bisa disubmit']);
        }

        $mr->update(['status' => 'pending_admin', 'submitted_at' => now()]);

        return tap(response()->json(['success' => true, 'data' => $mr->fresh(), 'message' => 'MR disubmit, menunggu persetujuan Admin']), function () use ($mr) {
            broadcast(new TransferBarangUpdated($mr->fresh(), 'submitted'))->toOthers();
        });
    }

    // POST /transfer-barang/{mr}/approve-admin
    // pending_admin → pending_atasan  (Admin HO approve qty)
    public function approveAdmin(Request $request, MaterialRequest $mr)
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

                // Cek & reserve stok di gudang sumber
                if ($qtyApproved > 0) {
                    $stock = ItemStock::where('item_id', $mrItem->item_id)
                        ->where('warehouse_id', $mr->from_warehouse_id)
                        ->first();

                    if (!$stock || ($stock->qty - $stock->qty_reserved) < $qtyApproved) {
                        $available = $stock ? ($stock->qty - $stock->qty_reserved) : 0;
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

        return tap(response()->json(['success' => true, 'data' => $mr->fresh(), 'message' => 'Disetujui Admin, menunggu persetujuan Atasan']), function () use ($mr) {
            broadcast(new TransferBarangUpdated($mr->fresh(), 'approved_admin'))->toOthers();
        });
    }

    // POST /transfer-barang/{mr}/approve-atasan
    // pending_atasan → approved
    public function approveAtasan(Request $request, MaterialRequest $mr)
    {
        if (!$request->user()->hasPermissionTo('approve-mr-manager')) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        }

        $this->assertTransfer($mr);
        if ($mr->status !== 'pending_atasan') {
            throw ValidationException::withMessages(['status' => 'MR tidak dalam status menunggu Atasan']);
        }

        $mr->update([
            'status'              => 'approved',
            'atasan_approved_by'  => $request->user()->id,
            'atasan_approved_at'  => now(),
        ]);

        return tap(response()->json(['success' => true, 'data' => $mr->fresh(), 'message' => 'Disetujui Atasan, siap untuk pengiriman']), function () use ($mr) {
            broadcast(new TransferBarangUpdated($mr->fresh(), 'approved_atasan'))->toOthers();
        });
    }

    // POST /transfer-barang/{mr}/kirim
    // approved → dispatched  (buat Surat Jalan / Delivery Order)
    public function kirim(Request $request, MaterialRequest $mr)
    {
        if (!$request->user()->hasPermissionTo('dispatch-mr')) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki akses'], 403);
        }

        $this->assertTransfer($mr);
        if ($mr->status !== 'approved') {
            throw ValidationException::withMessages(['status' => 'MR harus berstatus approved untuk dikirim']);
        }

        $validated = $request->validate([
            'driver_name'   => 'nullable|string|max:255',
            'vehicle_plate' => 'nullable|string|max:50',
            'notes'         => 'nullable|string',
            'items'         => 'required|array|min:1',
            'items.*.id'    => 'required|exists:material_request_items,id',
            'items.*.qty_sent' => 'required|numeric|min:0.01',
        ]);

        $do = DB::transaction(function () use ($mr, $validated, $request) {
            $do = DeliveryOrder::create([
                'do_number'           => DeliveryOrder::generateNumber(),
                'material_request_id' => $mr->id,
                'from_warehouse_id'   => $mr->from_warehouse_id,
                'to_warehouse_id'     => $mr->to_warehouse_id,
                'status'              => 'sent',
                'driver_name'         => $validated['driver_name'] ?? null,
                'vehicle_plate'       => $validated['vehicle_plate'] ?? null,
                'sent_by'             => $request->user()->id,
                'sent_at'             => now(),
                'notes'               => $validated['notes'] ?? null,
            ]);

            $itemIndex = 1;

            foreach ($validated['items'] as $itemData) {
                $mrItem = MaterialRequestItem::find($itemData['id']);
                if (!$mrItem || $mrItem->material_request_id !== $mr->id) continue;

                $qtySent = (float) $itemData['qty_sent'];
                if ($qtySent <= 0) continue;

                DeliveryOrderItem::create([
                    'delivery_order_id' => $do->id,
                    'item_id'           => $mrItem->item_id,
                    'qty_sent'          => $qtySent,
                ]);

                $mrItem->update(['qty_sent' => $mrItem->qty_sent + $qtySent]);

                // Kurangi stok gudang asal saat barang dikirim
                $stock = ItemStock::where('item_id', $mrItem->item_id)
                    ->where('warehouse_id', $mr->from_warehouse_id)
                    ->first();

                if ($stock) {
                    $qtyBefore = $stock->qty;
                    $stock->decrement('qty', $qtySent);
                    $stock->decrement('qty_reserved', min($qtySent, $stock->qty_reserved));

                    StockMovement::create([
                        'item_id'           => $mrItem->item_id,
                        'from_warehouse_id' => $mr->from_warehouse_id,
                        'to_warehouse_id'   => $mr->to_warehouse_id,
                        'type'              => 'transfer_out',
                        'qty'               => $qtySent,
                        'qty_before'        => $qtyBefore,
                        'qty_after'         => $qtyBefore - $qtySent,
                        'reference_no'      => $do->do_number . '-OUT-' . str_pad($itemIndex, 3, '0', STR_PAD_LEFT),
                        'notes'             => "Transfer keluar via DO: {$do->do_number}",
                        'moveable_type'     => DeliveryOrder::class,
                        'moveable_id'       => $do->id,
                        'movement_date'     => now()->toDateString(),
                        'created_by'        => $request->user()->id,
                    ]);
                }

                $itemIndex++;
            }

            $mr->update([
                'status'        => 'dispatched',
                'dispatched_by' => $request->user()->id,
                'dispatched_at' => now(),
            ]);

            return $do->load('items.item', 'fromWarehouse', 'toWarehouse');
        });

        return tap(response()->json(['success' => true, 'data' => $do, 'message' => "Barang dikirim, Surat Jalan {$do->do_number} dibuat"], 201), function () use ($mr) {
            broadcast(new TransferBarangUpdated($mr->fresh(), 'dispatched'))->toOthers();
        });
    }

    // POST /transfer-barang/delivery/{do}/terima
    // Penerima konfirmasi barang tiba → stok masuk di gudang tujuan
    public function terima(Request $request, DeliveryOrder $do)
    {
        if ($do->status !== 'sent') {
            throw ValidationException::withMessages(['status' => 'Surat Jalan sudah pernah dikonfirmasi']);
        }

        $validated = $request->validate([
            'received_by_name' => 'required|string|max:255',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|exists:delivery_order_items,id',
            'items.*.qty_received' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($do, $validated, $request) {
            $itemIndex = 1;

            foreach ($validated['items'] as $itemData) {
                $doItem = DeliveryOrderItem::find($itemData['id']);
                if (!$doItem || $doItem->delivery_order_id !== $do->id) continue;

                $qtyReceived = (float) $itemData['qty_received'];
                $doItem->update(['qty_received' => $qtyReceived]);

                if ($qtyReceived <= 0) continue;

                // Stok masuk di gudang tujuan
                $stock = ItemStock::firstOrCreate(
                    ['item_id' => $doItem->item_id, 'warehouse_id' => $do->to_warehouse_id],
                    ['qty' => 0, 'qty_reserved' => 0, 'avg_price' => 0]
                );

                $qtyBefore = $stock->qty;
                $stock->increment('qty', $qtyReceived);

                StockMovement::create([
                    'item_id'          => $doItem->item_id,
                    'from_warehouse_id' => $do->from_warehouse_id,
                    'to_warehouse_id'  => $do->to_warehouse_id,
                    'type'             => 'transfer_in',
                    'qty'              => $qtyReceived,
                    'qty_before'       => $qtyBefore,
                    'qty_after'        => $qtyBefore + $qtyReceived,
                    'reference_no'     => $do->do_number . '-IN-' . str_pad($itemIndex, 3, '0', STR_PAD_LEFT),
                    'notes'            => "Transfer masuk via DO: {$do->do_number}",
                    'moveable_type'    => DeliveryOrder::class,
                    'moveable_id'      => $do->id,
                    'movement_date'    => now()->toDateString(),
                    'created_by'       => $request->user()->id,
                ]);

                // Update qty_received di MR item
                $mrItem = $do->materialRequest?->items()
                    ->where('item_id', $doItem->item_id)->first();
                if ($mrItem) {
                    $mrItem->increment('qty_received', $qtyReceived);
                }

                $itemIndex++;
            }

            $do->update([
                'status'           => 'received',
                'received_by'      => $request->user()->id,
                'received_by_name' => $validated['received_by_name'],
                'received_at'      => now(),
                'receive_notes'    => $validated['notes'] ?? null,
            ]);

            // Update status MR ke received
            if ($do->material_request_id) {
                MaterialRequest::find($do->material_request_id)?->update([
                    'status'      => 'received',
                    'received_by' => $request->user()->id,
                    'received_at' => now(),
                ]);
            }
        });

        return tap(response()->json(['success' => true, 'message' => 'Barang dikonfirmasi diterima, stok gudang tujuan bertambah']), function () use ($do) {
            if ($do->material_request_id) {
                $mr = \App\Models\MaterialRequest::find($do->material_request_id);
                if ($mr) broadcast(new TransferBarangUpdated($mr, 'received'))->toOthers();
            }
        });
    }

    // POST /transfer-barang/{mr}/reject
    public function reject(Request $request, MaterialRequest $mr)
    {
        $this->assertTransfer($mr);
        $request->validate(['reason' => 'required|string|min:5']);

        $rejectableStatuses = ['pending_admin', 'pending_atasan'];
        if (!in_array($mr->status, $rejectableStatuses)) {
            throw ValidationException::withMessages(['status' => 'MR tidak bisa ditolak dari status ini']);
        }

        DB::transaction(function () use ($mr, $request) {
            // Release reserved stock jika sudah di-approve admin
            if ($mr->status === 'pending_atasan') {
                foreach ($mr->items as $mrItem) {
                    if ($mrItem->qty_approved > 0) {
                        $stock = ItemStock::where('item_id', $mrItem->item_id)
                            ->where('warehouse_id', $mr->from_warehouse_id)->first();
                        if ($stock) {
                            $stock->decrement('qty_reserved', min((float)$mrItem->qty_approved, (float)$stock->qty_reserved));
                        }
                    }
                }
            }

            $mr->update([
                'status'           => 'rejected',
                'rejection_reason' => $request->reason,
                'approved_by'      => $request->user()->id,
                'approved_at'      => now(),
            ]);
        });

        return tap(response()->json(['success' => true, 'data' => $mr->fresh(), 'message' => 'MR Transfer ditolak']), function () use ($mr) {
            broadcast(new TransferBarangUpdated($mr->fresh(), 'rejected'))->toOthers();
        });
    }

    // DELETE /transfer-barang/{mr}
    public function destroy(MaterialRequest $mr)
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