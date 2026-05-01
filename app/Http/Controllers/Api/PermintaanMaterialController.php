<?php

namespace App\Http\Controllers\Api;

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

        // Validasi manual untuk field wajib pada barang baru
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

        $result = $this->service->store($validated, $request->user()->id);

        $message = 'Permintaan material berhasil dibuat';
        if ($result['new_items_count'] > 0) {
            $message .= " ({$result['new_items_count']} barang baru otomatis terdaftar ke Master Barang)";
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
                'bonPengeluaran.warehouse'
            ),
        ]);
    }

    // POST /permintaan-material/{id}/submit
    public function submit(Request $request, PermintaanMaterial $pm)
    {
        $result = $this->service->submit($pm);

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

        return response()->json([
            'success' => true,
            'data'    => $pm,
            'message' => 'Permintaan material disetujui Admin HO. Silakan klik "Ajukan PO ke Purchasing" untuk meneruskan ke Purchasing.',
        ]);
    }

    // POST /permintaan-material/{id}/submit-purchasing
    public function submitPurchasing(Request $request, PermintaanMaterial $pm)
    {
        if (!$request->user()->isAdminHO() && !$request->user()->isSuperuser()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Admin HO yang dapat mengajukan PO',
            ], 403);
        }

        $pm = $this->service->submitPurchasing($pm, $request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => $pm,
            'message' => "PM {$pm->nomor} berhasil diajukan ke Purchasing. Status: Menunggu Pembuatan PO.",
        ]);
    }

    // POST /permintaan-material/{id}/reject
    public function reject(Request $request, PermintaanMaterial $pm)
    {
        $request->validate(['reason' => 'required|string|min:5']);

        $pm = $this->service->reject($pm, $request->reason);

        return response()->json([
            'success' => true,
            'data'    => $pm,
            'message' => 'Permintaan material ditolak',
        ]);
    }

    // DELETE /permintaan-material/{id}
    public function destroy(PermintaanMaterial $pm)
    {
        $this->service->destroy($pm);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan dihapus',
        ]);
    }

    // GET /permintaan-material/{pm}/export-excel
    public function exportExcel(PermintaanMaterial $pm)
    {
        $pm->load('items.item', 'warehouse', 'requester', 'chiefAuthorizer', 'managerApprover', 'hoApprover');

        $data           = $pm->toArray();
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

        return response()->download($xlsxFile, "PM-{$pm->nomor}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}