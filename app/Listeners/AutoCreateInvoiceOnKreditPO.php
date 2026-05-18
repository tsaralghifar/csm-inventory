<?php

namespace App\Listeners;

use App\Events\PurchaseOrderUpdated;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Saat PO kredit selesai (action = 'completed'), otomatis buat SupplierInvoice.
 *
 * Daftarkan di EventServiceProvider:
 *   PurchaseOrderUpdated::class => [
 *       NotifyAdminOnPurchaseOrder::class,
 *       AutoCreateInvoiceOnKreditPO::class,  // ← tambahkan
 *   ],
 */
class AutoCreateInvoiceOnKreditPO implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    public function shouldHandle(PurchaseOrderUpdated $event): bool
    {
        return $event->action === PurchaseOrder::STATUS_COMPLETED
            && $event->po->isKredit();
    }

    public function handle(PurchaseOrderUpdated $event): void
    {
        if (! $this->shouldHandle($event)) {
            return;
        }

        $po      = $event->po;
        $invoice = $this->service->createSupplierInvoiceIfNeeded($po);

        if ($invoice) {
            Log::info('AutoCreateInvoiceOnKreditPO: invoice dibuat', [
                'po_id'          => $po->id,
                'po_number'      => $po->po_number,
                'invoice_number' => $invoice->invoice_number,
            ]);
        }
    }

    public function failed(PurchaseOrderUpdated $event, \Throwable $e): void
    {
        Log::error('AutoCreateInvoiceOnKreditPO: job gagal', [
            'po_id' => $event->po->id,
            'error' => $e->getMessage(),
        ]);
    }
}
