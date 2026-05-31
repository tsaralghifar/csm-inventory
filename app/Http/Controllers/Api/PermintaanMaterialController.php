<?php

namespace App\Http\Controllers\Api;

use App\Events\PermintaanMaterialUpdated;
use App\Http\Controllers\Controller;
use App\Models\PermintaanMaterial;
use App\Services\PermintaanMaterialService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PermintaanMaterialController extends Controller
{
    public function __construct(protected PermintaanMaterialService $service) {}

    // GET /permintaan-material
    public function index(Request $request)
    {
        $data = $this->service->list($request, $request->user());

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

    // POST /permintaan-material
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id'              => 'required|exists:warehouses,id',
            'type'                      => 'nullable|in:part,office',
            'notes'                     => 'nullable|string',
            'needed_date'               => 'nullable|date',
            'linked_transfer_part_id'   => 'nullable|exists:material_requests,id',
            'items'                     => 'required|array|min:1',
            'items.*.item_id'           => 'nullable|exists:items,id',
            'items.*.part_number'       => 'nullable|string|max:100',
            'items.*.nama_barang'       => 'required|string|max:255',
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
                        "items.{$idx}.new_part_number" => 'Part Number wajib diisi untuk barang baru.',
                    ]);
                }
                if (empty($itemData['new_category_id'])) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.new_category_id" => 'Kategori wajib dipilih untuk barang baru.',
                    ]);
                }
            }
        }

        // Jika PM ini pengganti Transfer Part, validasi TP valid & belum punya PM
        if (!empty($validated['linked_transfer_part_id'])) {
            $tp = \App\Models\MaterialRequest::where('id', $validated['linked_transfer_part_id'])
                ->where('type', 'transfer_part')
                ->where('status', 'approved')
                ->first();
            if (!$tp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer Part tidak ditemukan atau belum disetujui.',
                ], 422);
            }
            if ($tp->linked_pm_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer Part ini sudah memiliki PM pengganti.',
                ], 422);
            }
        }

        $result = $this->service->store($validated, $request->user()->id);

        // Link balik ke Transfer Part jika ada
        if (!empty($validated['linked_transfer_part_id'])) {
            $result['pm']->update(['linked_transfer_part_id' => $validated['linked_transfer_part_id']]);
            \App\Models\MaterialRequest::where('id', $validated['linked_transfer_part_id'])
                ->update(['linked_pm_id' => $result['pm']->id]);
        }

        broadcast(new PermintaanMaterialUpdated($result['pm']->fresh(), 'created'))->toOthers();

        $message = 'Permintaan material berhasil dibuat';
        if ($result['new_items_count'] > 0) {
            $message .= ' (' . $result['new_items_count'] . ' barang baru otomatis terdaftar ke Master Barang)';
        }

        return response()->json([
            'success' => true,
            'data'    => $result['pm'],
            'message' => $message,
        ], 201);
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
                'bonPengeluaran.warehouse',
                'linkedTransferPart'
            ),
        ]);
    }

    // POST /permintaan-material/{id}/submit
    public function submit(Request $request, PermintaanMaterial $pm)
    {
        $result = $this->service->submit($pm);

        broadcast(new PermintaanMaterialUpdated($result['pm']->fresh(), 'submitted'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $result['pm'],
            'message' => $result['message'],
        ]);
    }

    // POST /permintaan-material/{id}/authorize-chief
    public function authorizeChief(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->hasPermissionTo('authorize-mr-chief')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melakukan otorisasi ini',
            ], 403);
        }

        $pm = $this->service->authorizeChief($pm, $request->user()->id);

        broadcast(new PermintaanMaterialUpdated($pm->fresh(), 'authorized_chief'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $pm,
            'message' => 'Diotorisasi Chief Mekanik, diteruskan ke Manager',
        ]);
    }

    // POST /permintaan-material/{id}/approve-manager
    public function approveManager(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->hasPermissionTo('approve-mr-manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui sebagai Manager',
            ], 403);
        }

        $pm = $this->service->approveManager($pm, $request->user()->id);

        broadcast(new PermintaanMaterialUpdated($pm->fresh(), 'approved_manager'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $pm,
            'message' => 'Disetujui Manager, diteruskan ke Admin HO',
        ]);
    }

    // POST /permintaan-material/{id}/approve-ho
    public function approveHO(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->hasPermissionTo('approve-pm-ho')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui sebagai Admin HO',
            ], 403);
        }

        $pm = $this->service->approveHO($pm, $request->user()->id);

        broadcast(new PermintaanMaterialUpdated($pm->fresh(), 'approved_ho'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $pm,
            'message' => 'Permintaan material disetujui Admin HO. Silakan klik "Ajukan PO ke Purchasing" untuk meneruskan ke Purchasing.',
        ]);
    }

    // POST /permintaan-material/{id}/submit-purchasing
    public function submitPurchasing(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->isAdminHO() && !$request->user()->isLogistikHO() && !$request->user()->isSuperuser()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Admin HO yang dapat mengajukan PO',
            ], 403);
        }

        $pm = $this->service->submitPurchasing($pm, $request->user()->id);

        broadcast(new PermintaanMaterialUpdated($pm->fresh(), 'submit_purchasing'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $pm,
            'message' => 'PM ' . $pm->nomor . ' berhasil diajukan ke Purchasing. Status: Menunggu Pembuatan PO.',
        ]);
    }

    // POST /permintaan-material/{id}/reject
    public function reject(Request $request, PermintaanMaterial $pm)
    {
        $request->validate(['reason' => 'required|string|min:5']);

        $pm = $this->service->reject($pm, $request->reason);

        broadcast(new PermintaanMaterialUpdated($pm->fresh(), 'rejected'))->toOthers();

        return response()->json([
            'success' => true,
            'data'    => $pm,
            'message' => 'Permintaan material ditolak',
        ]);
    }

    // POST /permintaan-material/{pm}/items
    public function addItem(Request $request, PermintaanMaterial $pm)
    {
        if ($pm->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Item hanya bisa ditambahkan saat status masih Draft.'], 422);
        }

        $validated = $request->validate([
            'nama_barang'    => 'required|string|max:255',
            'part_number'    => 'nullable|string|max:100',
            'kode_unit'      => 'nullable|string|max:100',
            'tipe_unit'      => 'nullable|string|max:100',
            'qty'            => 'required|numeric|min:0.01',
            'satuan'         => 'required|string|max:50',
            'keterangan'     => 'nullable|string',
            'item_id'        => 'nullable|exists:items,id',
            'is_new_item'    => 'nullable|boolean',
            'new_part_number'=> 'nullable|string|max:100',
            'new_category_id'=> 'nullable|exists:categories,id',
            'new_brand'      => 'nullable|string|max:100',
            'new_min_stock'  => 'nullable|numeric|min:0',
        ]);

        $itemId = $validated['item_id'] ?? null;

        // Cek duplikat dalam PM yang sama
        if ($itemId && $pm->items()->where('item_id', $itemId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Barang ini sudah ada di daftar PM. Tidak boleh menambahkan barang yang sama dua kali.'], 422);
        }
        $partNumber = $validated['part_number'] ?? ($validated['new_part_number'] ?? null);
        if ($partNumber && $pm->items()->where('part_number', $partNumber)->exists()) {
            return response()->json(['success' => false, 'message' => "Part Number \"{$partNumber}\" sudah ada di daftar PM ini."], 422);
        }

        // Jika barang baru, daftarkan ke master data terlebih dahulu
        if (!empty($validated['is_new_item'])) {
            if (empty($validated['new_part_number'])) {
                return response()->json(['success' => false, 'message' => 'Part Number wajib diisi untuk barang baru.'], 422);
            }
            if (empty($validated['new_category_id'])) {
                return response()->json(['success' => false, 'message' => 'Kategori wajib dipilih untuk barang baru.'], 422);
            }
            $newItem = \App\Models\Item::create([
                'name'        => $validated['nama_barang'],
                'part_number' => $validated['new_part_number'],
                'category_id' => $validated['new_category_id'],
                'brand'       => $validated['new_brand'] ?? null,
                'unit'        => $validated['satuan'],
                'min_stock'   => $validated['new_min_stock'] ?? 0,
            ]);
            $itemId = $newItem->id;
        }

        $item = $pm->items()->create([
            'item_id'     => $itemId,
            'nama_barang' => $validated['nama_barang'],
            'part_number' => $validated['part_number'] ?? null,
            'kode_unit'   => $validated['kode_unit'] ?? null,
            'tipe_unit'   => $validated['tipe_unit'] ?? null,
            'qty'         => $validated['qty'],
            'satuan'      => $validated['satuan'],
            'keterangan'  => $validated['keterangan'] ?? null,
        ]);

        broadcast(new PermintaanMaterialUpdated($pm->fresh(), 'updated'))->toOthers();

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Item berhasil ditambahkan.']);
    }

    // PUT /permintaan-material/{pm}/items/{item}
    public function updateItem(Request $request, PermintaanMaterial $pm, \App\Models\PermintaanMaterialItem $item)
    {
        if ($pm->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Item hanya bisa diubah saat status masih Draft.'], 422);
        }
        if ($item->permintaan_material_id !== $pm->id) {
            return response()->json(['success' => false, 'message' => 'Item tidak ditemukan dalam PM ini.'], 404);
        }

        $validated = $request->validate([
            'nama_barang'  => 'required|string|max:255',
            'part_number'  => 'nullable|string|max:100',
            'kode_unit'    => 'nullable|string|max:100',
            'tipe_unit'    => 'nullable|string|max:100',
            'qty'          => 'required|numeric|min:0.01',
            'satuan'       => 'required|string|max:50',
            'keterangan'   => 'nullable|string',
        ]);

        // Cek duplikat part_number dalam PM yang sama, kecuali item yang sedang diedit
        $newPartNumber = $validated['part_number'] ?? null;
        if ($newPartNumber) {
            $isDuplicate = $pm->items()
                ->where('part_number', $newPartNumber)
                ->where('id', '!=', $item->id)
                ->exists();

            if ($isDuplicate) {
                return response()->json([
                    'success' => false,
                    'message' => "Part Number \"{$newPartNumber}\" sudah ada di daftar PM ini. Tidak bisa menyimpan perubahan dengan Part Number yang sama.",
                ], 422);
            }
        }

        $item->update($validated);

        // Sync perubahan nama & part_number ke master barang jika item berasal dari master
        if ($item->item_id) {
            $masterItem = \App\Models\Item::find($item->item_id);
            if ($masterItem) {
                $syncData = [];
                if (!empty($validated['part_number']) && $masterItem->part_number !== $validated['part_number']) {
                    $syncData['part_number'] = $validated['part_number'];
                }
                if ($masterItem->name !== $validated['nama_barang']) {
                    $syncData['name'] = $validated['nama_barang'];
                }
                if (!empty($syncData)) {
                    $masterItem->update($syncData);
                }
            }
        }

        broadcast(new PermintaanMaterialUpdated($pm->fresh(), 'updated'))->toOthers();

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Item berhasil diperbarui.']);
    }

    // DELETE /permintaan-material/{pm}/items/{item}
    public function deleteItem(Request $request, PermintaanMaterial $pm, \App\Models\PermintaanMaterialItem $item)
    {
        if ($pm->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Item hanya bisa dihapus saat status masih Draft.'], 422);
        }
        if ($item->permintaan_material_id !== $pm->id) {
            return response()->json(['success' => false, 'message' => 'Item tidak ditemukan dalam PM ini.'], 404);
        }
        if ($pm->items()->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'PM harus memiliki minimal 1 item.'], 422);
        }

        $item->delete();

        broadcast(new PermintaanMaterialUpdated($pm->fresh(), 'updated'))->toOthers();

        return response()->json(['success' => true, 'message' => 'Item berhasil dihapus.']);
    }

    // DELETE /permintaan-material/{id}
    public function destroy(PermintaanMaterial $pm)
    {
        $warehouseId = $pm->warehouse_id;
        $nomor       = $pm->nomor;

        $this->service->destroy($pm);

        // Broadcast dengan data minimal karena record sudah dihapus
        broadcast(new PermintaanMaterialUpdated(
            new PermintaanMaterial(['id' => null, 'nomor' => $nomor, 'status' => 'deleted', 'warehouse_id' => $warehouseId]),
            'deleted'
        ))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Permintaan dihapus',
        ]);
    }

    // GET /permintaan-material/{pm}/export-excel
    public function exportExcel(PermintaanMaterial $pm)
    {
        $pm->load('items.item', 'warehouse', 'requester', 'chiefAuthorizer', 'managerApprover', 'hoApprover');

        $data             = $pm->toArray();
        $data['approver'] = $pm->hoApprover ? $pm->hoApprover->toArray() : null;

        $jsonFile = tempnam(sys_get_temp_dir(), 'pm_json_') . '.json';
        $xlsxFile = tempnam(sys_get_temp_dir(), 'pm_excel_') . '.xlsx';
        file_put_contents($jsonFile, json_encode($data));

        $script = base_path('scripts/export_pm_excel.py');
        $cmd    = 'python3 ' . escapeshellarg($script) . ' ' . escapeshellarg($jsonFile) . ' ' . escapeshellarg($xlsxFile) . ' 2>&1';

        exec($cmd, $output, $code);
        @unlink($jsonFile);

        if ($code !== 0 || !file_exists($xlsxFile)) {
            return response()->json([
                'error'  => 'Gagal generate Excel',
                'detail' => implode("\n", $output),
            ], 500);
        }

        return response()->download($xlsxFile, 'PM-' . $pm->nomor . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}