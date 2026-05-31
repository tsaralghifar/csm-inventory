<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentSigner;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $service) {}

    // ─── Stok ─────────────────────────────────────────────────────────────────

    public function stockReport(Request $request): JsonResponse
    {
        $request->validate(['warehouse_id' => 'nullable|exists:warehouses,id']);

        $result = $this->service->stockReport(
            warehouseId: $request->warehouse_id ? (int) $request->warehouse_id : null,
            categoryId:  $request->category_id  ? (int) $request->category_id  : null,
            filter:      $request->filter,
        );

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'summary' => $result['summary'],
        ]);
    }

    public function exportPdf(Request $request): Response|BinaryFileResponse|JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'signer_ids'   => 'nullable|array|max:3',
            'signer_ids.*' => 'integer|exists:users,id',
        ]);

        $warehouseId = $request->warehouse_id ? (int) $request->warehouse_id : null;

        $result = $this->service->stockReport(
            warehouseId: $warehouseId,
            categoryId:  $request->category_id ? (int) $request->category_id : null,
            filter:      $request->filter,
        );

        $warehouseName = $request->warehouse_name
            ?? ($warehouseId ? Warehouse::find($warehouseId)?->name ?? 'Semua Gudang' : 'Semua Gudang');

        // document_id deterministik dari parameter + tanggal
        $documentId = $this->buildReportDocumentId('stock', $request);

        // Coba load snapshot yang sudah tersimpan
        $signers = DocumentSigner::loadSnapshot('report_stock', $documentId);

        if ($signers === null) {
            $signerIds = $request->input('signer_ids', []);

            if (! empty($signerIds)) {
                [$signers, $errors] = $this->resolveSigners($signerIds);

                if (! empty($errors)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Satu atau lebih penandatangan tidak valid.',
                        'errors'  => $errors,
                    ], 422);
                }

                // Simpan snapshot permanen
                DocumentSigner::saveSnapshot('report_stock', $documentId, $signers);
                $signers = DocumentSigner::loadSnapshot('report_stock', $documentId);
            } else {
                $signers = [];
            }
        }

        $pdf = Pdf::loadView('pdf.stock', [
            'data'     => $result['data'],
            'summary'  => $result['summary'],
            'request'  => array_merge($request->all(), ['warehouse_name' => $warehouseName]),
            'signers'  => $signers ?? [],
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Laporan_Stok_' . now()->format('Ymd') . '.pdf');
    }

    // ─── Mutasi ───────────────────────────────────────────────────────────────

    public function movementReport(Request $request): JsonResponse
    {
        $movements = $this->service->movementReport(
            warehouseId:  $request->warehouse_id  ? (int) $request->warehouse_id : null,
            type:         $request->type,
            dateFrom:     $request->date_from,
            dateTo:       $request->date_to,
            itemId:       $request->item_id        ? (int) $request->item_id : null,
            moveableType: $request->moveable_type,
            perPage:      (int) ($request->per_page ?? 50),
        );

        return response()->json([
            'success' => true,
            'data'    => $movements->items(),
            'meta'    => [
                'total'     => $movements->total(),
                'page'      => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
            ],
        ]);
    }

    // ─── Pengeluaran ──────────────────────────────────────────────────────────

    public function pengeluaranReport(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date|after_or_equal:date_from',
        ]);

        $result = $this->service->pengeluaranReport(
            warehouseId: (int) $request->warehouse_id,
            dateFrom:    $request->date_from,
            dateTo:      $request->date_to,
        );

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'summary' => $result['summary'],
        ]);
    }

    // ─── Pembelian ────────────────────────────────────────────────────────────

    public function purchaseReport(Request $request): JsonResponse
    {
        $result = $this->service->purchaseReport(
            dateFrom:    $request->date_from,
            dateTo:      $request->date_to,
            paymentType: $request->payment_type,
            status:      $request->status,
            supplierId:  $request->supplier_id ? (int) $request->supplier_id : null,
        );

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'summary' => $result['summary'],
        ]);
    }

    public function purchasePdf(Request $request): Response|BinaryFileResponse|JsonResponse
    {
        $request->validate([
            'signer_ids'   => 'nullable|array|max:3',
            'signer_ids.*' => 'integer|exists:users,id',
        ]);

        $result = $this->service->purchaseReport(
            dateFrom:    $request->date_from,
            dateTo:      $request->date_to,
            paymentType: $request->payment_type,
            status:      $request->status,
            supplierId:  $request->supplier_id ? (int) $request->supplier_id : null,
        );

        $supplierName = $request->supplier_name
            ?? ($request->supplier_id
                ? Supplier::find($request->supplier_id)?->name ?? 'Semua Supplier'
                : 'Semua Supplier');

        $documentId = $this->buildReportDocumentId('purchase', $request);

        $signers = DocumentSigner::loadSnapshot('report_purchase', $documentId);

        if ($signers === null) {
            $signerIds = $request->input('signer_ids', []);

            if (! empty($signerIds)) {
                [$signers, $errors] = $this->resolveSigners($signerIds);

                if (! empty($errors)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Satu atau lebih penandatangan tidak valid.',
                        'errors'  => $errors,
                    ], 422);
                }

                DocumentSigner::saveSnapshot('report_purchase', $documentId, $signers);
                $signers = DocumentSigner::loadSnapshot('report_purchase', $documentId);
            } else {
                $signers = [];
            }
        }

        $pdf = Pdf::loadView('pdf.purchase', [
            'data'     => $result['data'],
            'summary'  => $result['summary'],
            'request'  => array_merge($request->all(), ['supplier_name' => $supplierName]),
            'signers'  => $signers ?? [],
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Laporan_Pembelian_' . now()->format('Ymd') . '.pdf');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Buat document_id deterministik dari parameter laporan.
     * Laporan dengan parameter sama di hari yang sama → snapshot yang sama.
     * Hari berbeda → snapshot baru (laporan hari ini ≠ laporan kemarin).
     */
    private function buildReportDocumentId(string $type, Request $request): string
    {
        $params = [
            'type'         => $type,
            'warehouse_id' => $request->warehouse_id,
            'category_id'  => $request->category_id,
            'filter'       => $request->filter,
            'date_from'    => $request->date_from,
            'date_to'      => $request->date_to,
            'supplier_id'  => $request->supplier_id,
            'payment_type' => $request->payment_type,
            'status'       => $request->status,
            'date'         => now()->format('Y-m-d'),
        ];

        ksort($params);
        return substr(md5(json_encode($params)), 0, 16);
    }

    /**
     * Validasi dan resolve signer_ids menjadi array data penandatangan.
     * Menyertakan 'user_model' untuk keperluan DocumentSigner::saveSnapshot().
     *
     * Syarat validasi (CELAH 3):
     *   1. User aktif
     *   2. Sudah punya TTD
     *   3. Role minimal manager/logistik_ho/admin_ho/superuser
     *
     * @return array  [$signers, $errors]
     */
    private function resolveSigners(array $signerIds): array
    {
        if (empty($signerIds)) return [[], []];

        $labels = ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh'];

        $users = User::with('roles')
            ->whereIn('id', $signerIds)
            ->get()
            ->keyBy('id');

        $signers = [];
        $errors  = [];

        foreach (array_slice($signerIds, 0, 3) as $i => $userId) {
            $user = $users[$userId] ?? null;

            if (! $user) {
                $errors[] = "User ID {$userId} tidak ditemukan.";
                continue;
            }

            if (! $user->is_active) {
                $errors[] = "User \"{$user->name}\" tidak aktif.";
                continue;
            }

            if (! $user->hasSignature()) {
                $errors[] = "User \"{$user->name}\" belum memiliki tanda tangan.";
                continue;
            }

            if (! $user->canSign()) {
                $roleName = $user->roles->first()?->name ?? 'tanpa role';
                $errors[] = "User \"{$user->name}\" (role: {$roleName}) tidak berwenang menandatangani.";
                continue;
            }

            $signers[] = [
                'label'      => $labels[$i] ?? 'Penandatangan ' . ($i + 1),
                'name'       => $user->name,
                'position'   => $user->position ?? $user->roles->first()?->name ?? '—',
                'role'       => $user->roles->first()?->name,
                'user_id'    => $user->id,
                'user_model' => $user,   // untuk copy file TTD ke snapshot
                'signature'  => $user->signatureDataUri(),
            ];
        }

        return [$signers, $errors];
    }
}