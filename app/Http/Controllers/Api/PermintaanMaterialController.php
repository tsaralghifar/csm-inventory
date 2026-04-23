<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PermintaanMaterial;
use App\Models\PermintaanMaterialItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PermintaanMaterialController extends Controller
{
    // GET /permintaan-material
    public function index(Request $request)
    {
        $user = $request->user();

        $query = PermintaanMaterial::with(['warehouse', 'requester', 'chiefAuthorizer', 'managerApprover', 'hoApprover'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        // Filter by warehouse berdasarkan role
        if (!$user->isSuperuser() && !$user->isAdminHO()) {
            if ($user->hasRole('manager') || $user->hasRole('chief_mekanik') || $user->hasRole('purchasing')) {
                // tidak filter by warehouse — bisa lihat semua PM
            } else {
                $query->where('warehouse_id', $user->warehouse_id);
            }
        }

        if ($request->status) {
            // Support comma-separated: ?status=approved,partial_ordered
            $statuses = array_filter(explode(',', $request->status));
            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } else {
                $query->where('status', $request->status);
            }
        }
        if ($request->warehouse_id) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->type)         $query->where('type', $request->type);
        if ($request->date_from)    $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)      $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->search)       $query->where('nomor', 'ilike', "%{$request->search}%");

        $data = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => ['total' => $data->total(), 'page' => $data->currentPage(), 'last_page' => $data->lastPage()],
        ]);
    }

    // POST /permintaan-material
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id'              => 'required|exists:warehouses,id',
            'type'                      => 'nullable|in:part,office',
            'notes'                     => 'nullable|string',
            'needed_date'               => 'nullable|date',
            'items'                     => 'required|array|min:1',
            'items.*.item_id'           => 'nullable|exists:items,id',
            'items.*.part_number'        => 'nullable|string|max:100',
            'items.*.nama_barang'        => 'required|string|max:255',
            'items.*.kode_unit'         => 'nullable|string|max:100',
            'items.*.tipe_unit'         => 'nullable|string|max:100',
            'items.*.qty'               => 'required|numeric|min:0.01',
            'items.*.satuan'            => 'required|string|max:50',
            'items.*.keterangan'        => 'nullable|string',
            'items.*.is_new_item'       => 'nullable|boolean',
            'items.*.new_part_number'   => 'nullable|string|max:100',
            'items.*.new_category_id'   => 'nullable|exists:categories,id',
            'items.*.new_brand'         => 'nullable|string|max:100',
            'items.*.new_min_stock'     => 'nullable|numeric|min:0',
        ]);

        foreach ($validated['items'] as $idx => $itemData) {
            if (!empty($itemData['is_new_item'])) {
                if (empty($itemData['new_part_number'])) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.new_part_number" => "Part Number wajib diisi untuk barang baru.",
                    ]);
                }
                if (empty($itemData['new_category_id'])) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.new_category_id" => "Kategori wajib dipilih untuk barang baru.",
                    ]);
                }
                $existingItem = Item::where('part_number', $itemData['new_part_number'])->first();
                if ($existingItem) {
                    $validated['items'][$idx]['is_new_item']   = false;
                    $validated['items'][$idx]['item_id']       = $existingItem->id;
                    $validated['items'][$idx]['part_number']   = $existingItem->part_number;
                    $validated['items'][$idx]['nama_barang']   = $validated['items'][$idx]['nama_barang'] ?: $existingItem->name;
                    $validated['items'][$idx]['satuan']        = $validated['items'][$idx]['satuan'] ?: $existingItem->unit;
                }
            }
        }

        $pm = DB::transaction(function () use ($validated, $request) {
            $pm = PermintaanMaterial::create([
                'nomor'        => PermintaanMaterial::generateNomor(),
                'warehouse_id' => $validated['warehouse_id'],
                'type'         => $validated['type'] ?? 'part',
                'requested_by' => $request->user()->id,
                'status'       => 'draft',
                'notes'        => $validated['notes'] ?? null,
                'needed_date'  => $validated['needed_date'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $resolvedItemId = $itemData['item_id'] ?? null;

                if (!empty($itemData['is_new_item'])) {
                    $newItem = Item::create([
                        'part_number' => $itemData['new_part_number'],
                        'name'        => $itemData['nama_barang'],
                        'category_id' => $itemData['new_category_id'],
                        'brand'       => $itemData['new_brand'] ?? null,
                        'unit'        => $itemData['satuan'],
                        'min_stock'   => $itemData['new_min_stock'] ?? 0,
                        'price'       => 0,
                        'is_active'   => true,
                    ]);

                    ItemStock::firstOrCreate(
                        ['item_id' => $newItem->id, 'warehouse_id' => $validated['warehouse_id']],
                        ['qty' => 0, 'qty_reserved' => 0]
                    );

                    $resolvedItemId = $newItem->id;
                }

                PermintaanMaterialItem::create([
                    'permintaan_material_id' => $pm->id,
                    'item_id'                => $resolvedItemId,
                    'part_number'            => !empty($itemData['is_new_item'])
                                                    ? ($itemData['new_part_number'] ?? null)
                                                    : ($itemData['part_number'] ?? null),
                    'nama_barang'            => $itemData['nama_barang'],
                    'kode_unit'              => $itemData['kode_unit'] ?? null,
                    'tipe_unit'              => $itemData['tipe_unit'] ?? null,
                    'qty'                    => $itemData['qty'],
                    'satuan'                 => $itemData['satuan'],
                    'keterangan'             => $itemData['keterangan'] ?? null,
                ]);
            }

            return $pm->load('items', 'warehouse', 'requester');
        });

        $newItemsCount = collect($validated['items'])->filter(fn($i) => !empty($i['is_new_item']))->count();
        $message = 'Permintaan material berhasil dibuat';
        if ($newItemsCount > 0) {
            $message .= " ({$newItemsCount} barang baru otomatis terdaftar ke Master Barang)";
        }

        return response()->json(['success' => true, 'data' => $pm, 'message' => $message], 201);
    }

    // GET /permintaan-material/{id}
    public function show(PermintaanMaterial $permintaanMaterial)
    {
        return response()->json([
            'success' => true,
            'data'    => $permintaanMaterial->load(
                'items.item',
                'warehouse',
                'requester',
                'chiefAuthorizer',
                'managerApprover',
                'hoApprover',
                'poSubmitter',
                'purchaseOrders.items',
                'purchaseOrders.creator',
                'bonPengeluaran.warehouse'
            ),
        ]);
    }

    public function exportExcel(PermintaanMaterial $pm)
    {
        $pm->load('items.item', 'warehouse', 'requester', 'chiefAuthorizer', 'managerApprover', 'hoApprover');

        $data = $pm->toArray();
        $data['approver'] = $pm->hoApprover ? $pm->hoApprover->toArray() : null;

        $jsonFile = tempnam(sys_get_temp_dir(), 'pm_json_') . '.json';
        $xlsxFile = tempnam(sys_get_temp_dir(), 'pm_excel_') . '.xlsx';
        file_put_contents($jsonFile, json_encode($data));

        $script  = base_path('scripts/export_pm_excel.py');
        $cmd     = "python3 " . escapeshellarg($script) . " " . escapeshellarg($jsonFile) . " " . escapeshellarg($xlsxFile) . " 2>&1";

        exec($cmd, $output, $code);
        @unlink($jsonFile);

        if ($code !== 0 || !file_exists($xlsxFile)) {
            return response()->json(['error' => 'Gagal generate Excel', 'detail' => implode("\n", $output)], 500);
        }

        return response()->download($xlsxFile, "PM-{$pm->nomor}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // POST /permintaan-material/{id}/submit
    public function submit(Request $request, PermintaanMaterial $pm)
    {
        if ($pm->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya permintaan berstatus draft yang bisa disubmit']);
        }

        if ($pm->type === 'part') {
            $pm->update(['status' => 'pending_chief']);
            $message = 'Permintaan berhasil disubmit ke Chief Mekanik';
        } else {
            $pm->update(['status' => 'pending_ho']);
            $message = 'Permintaan berhasil disubmit ke Admin HO';
        }

        return response()->json(['success' => true, 'data' => $pm->fresh('warehouse', 'requester'), 'message' => $message]);
    }

    // POST /permintaan-material/{id}/authorize-chief
    public function authorizeChief(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->hasPermissionTo('authorize-mr-chief')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk melakukan otorisasi ini'], 403);
        }

        if ($pm->status !== 'pending_chief') {
            throw ValidationException::withMessages(['status' => 'Status permintaan tidak valid untuk diotorisasi Chief Mekanik']);
        }

        $pm->update([
            'status'               => 'pending_manager',
            'chief_authorized_by'  => $request->user()->id,
            'chief_authorized_at'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => $pm->fresh(), 'message' => 'Diotorisasi Chief Mekanik, diteruskan ke Manager']);
    }

    // POST /permintaan-material/{id}/approve-manager
    public function approveManager(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->hasPermissionTo('approve-mr-manager')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menyetujui sebagai Manager'], 403);
        }

        if ($pm->status !== 'pending_manager') {
            throw ValidationException::withMessages(['status' => 'Status permintaan tidak valid untuk disetujui Manager']);
        }

        $pm->update([
            'status'               => 'pending_ho',
            'manager_approved_by'  => $request->user()->id,
            'manager_approved_at'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => $pm->fresh(), 'message' => 'Disetujui Manager, diteruskan ke Admin HO']);
    }

    // POST /permintaan-material/{id}/approve-ho
    // Admin HO approve → status 'approved', menunggu Admin HO klik "Ajukan PO"
    public function approveHO(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->hasPermissionTo('approve-pm-ho')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menyetujui sebagai Admin HO'], 403);
        }

        if ($pm->status !== 'pending_ho') {
            throw ValidationException::withMessages(['status' => 'Status permintaan tidak valid untuk di-approve Admin HO']);
        }

        $pm->update([
            'status'         => 'approved',
            'ho_approved_by' => $request->user()->id,
            'ho_approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $pm->fresh(),
            'message' => 'Permintaan material disetujui Admin HO. Silakan klik "Ajukan PO ke Purchasing" untuk meneruskan ke Purchasing.',
        ]);
    }

    // POST /permintaan-material/{id}/submit-purchasing
    // Admin HO mengajukan PM ke Purchasing → status 'pending_purchasing'
    // Ini adalah sinyal eksplisit bahwa PM ini outstanding dan perlu dibuatkan PO
    public function submitPurchasing(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->isAdminHO() && !$request->user()->isSuperuser()) {
            return response()->json(['success' => false, 'message' => 'Hanya Admin HO yang dapat mengajukan PO'], 403);
        }

        if ($pm->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Hanya PM berstatus "Disetujui HO" yang dapat diajukan ke Purchasing',
            ]);
        }

        $pm->update([
            'status'          => 'pending_purchasing',
            'po_submitted_by' => $request->user()->id,
            'po_submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $pm->fresh('warehouse', 'requester', 'hoApprover', 'poSubmitter'),
            'message' => "PM {$pm->nomor} berhasil diajukan ke Purchasing. Status: Menunggu Pembuatan PO.",
        ]);
    }

    // POST /permintaan-material/{id}/reject
    public function reject(Request $request, PermintaanMaterial $pm)
    {
        $request->validate(['reason' => 'required|string|min:5']);

        // pending_purchasing bisa ditolak oleh Admin HO (misalnya cancel pengajuan)
        $rejectableStatuses = ['pending_chief', 'pending_manager', 'pending_ho', 'pending_purchasing'];
        if (!in_array($pm->status, $rejectableStatuses)) {
            throw ValidationException::withMessages(['status' => 'Permintaan tidak bisa ditolak pada status ini']);
        }

        $pm->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return response()->json(['success' => true, 'data' => $pm->fresh(), 'message' => 'Permintaan material ditolak']);
    }

    // DELETE /permintaan-material/{id}
    public function destroy(PermintaanMaterial $pm)
    {
        if ($pm->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya permintaan berstatus draft yang bisa dihapus']);
        }
        $pm->delete();
        return response()->json(['success' => true, 'message' => 'Permintaan dihapus']);
    }
}