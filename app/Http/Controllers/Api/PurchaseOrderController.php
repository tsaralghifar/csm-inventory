<?php

namespace App\Http\Controllers\Api;

use App\Events\PurchaseOrderUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    // GET /purchase-orders
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request);

        return $this->paginatedResponse($paginator);
    }

    // GET /purchase-orders/summary
    public function summary(): JsonResponse
    {
        return $this->successResponse($this->service->summary());
    }

    // GET /purchase-orders/{purchaseOrder}
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->successResponse(
            $purchaseOrder->load(
                'items.item',
                'items.permintaanMaterialItem',
                'warehouse',
                'supplier',
                'creator',
                'materialRequest',
                'permintaanMaterials.items',
                'suratJalan',
                'supplierInvoices',
            )
        );
    }

    // POST /purchase-orders
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $po = $this->service->create($request->validated(), $request->user()->id);

        broadcast(new PurchaseOrderUpdated($po->fresh(), 'created'))->toOthers();

        return $this->successResponse($po, 'Purchase Order berhasil dibuat.', 201);
    }

    // POST /purchase-orders/{purchaseOrder}/send
    public function sendToVendor(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $po = $this->service->sendToVendor($purchaseOrder);

        broadcast(new PurchaseOrderUpdated($po->fresh(), 'sent_to_vendor'))->toOthers();

        return $this->successResponse($po, 'PO berhasil dikirim ke vendor.');
    }

    // POST /purchase-orders/{purchaseOrder}/complete
    public function complete(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! $purchaseOrder->isCompletable()) {
            return $this->errorResponse(
                'PO harus berstatus "Dikirim ke Vendor" atau "Diterima Sebagian" untuk diselesaikan.',
                422,
            );
        }

        $po = $this->service->markComplete($purchaseOrder);

        broadcast(new PurchaseOrderUpdated($po->fresh(), 'completed'))->toOthers();

        $message = $po->isKredit()
            ? 'PO selesai. Invoice hutang supplier dibuat otomatis.'
            : 'PO selesai.';

        return $this->successResponse($po->load('supplierInvoices'), $message);
    }

    // PATCH /purchase-orders/{purchaseOrder}/items/{poItem}/update-part-number
    public function updatePartNumber(
        Request           $request,
        PurchaseOrder     $purchaseOrder,
        PurchaseOrderItem $poItem,
    ): JsonResponse {
        if (! $request->user()->can('manage-po')) {
            return $this->errorResponse('Anda tidak memiliki izin untuk mengubah part number.', 403);
        }

        $validated = $request->validate([
            'new_part_number' => ['required', 'string', 'max:100'],
            'update_master'   => ['sometimes', 'boolean'],
            'notes'           => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        // Pastikan item memang milik PO ini
        if ((int) $poItem->purchase_order_id !== (int) $purchaseOrder->id) {
            return $this->errorResponse('Item tidak ditemukan di PO ini.', 404);
        }

        try {
            $updated = $this->service->updatePartNumber(
                $purchaseOrder,
                $poItem,
                $validated,
                $request->user()->id,
            );

            return $this->successResponse($updated, 'Part number berhasil diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                collect($e->errors())->flatten()->first(),
                422,
            );
        }
    }

    // ─── Response helpers ─────────────────────────────────────────────────────

    private function successResponse(mixed $data, string $message = '', int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];

        if ($message) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    private function errorResponse(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function paginatedResponse($paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'total'     => $paginator->total(),
                'page'      => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}