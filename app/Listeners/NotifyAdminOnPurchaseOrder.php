<?php

namespace App\Listeners;

use App\Events\PurchaseOrderUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyAdminOnPurchaseOrder
 *
 * Kirim in-app notification ke admin_ho / superuser
 * setiap kali Purchase Order dibuat atau dikirim ke vendor.
 */
class NotifyAdminOnPurchaseOrder implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(PurchaseOrderUpdated $event): void
    {
        $po     = $event->po;
        $number = $po->po_number;
        $vendor = $po->vendor_name;
        $status = $po->status;

        $message = match ($event->action) {
            'created'        => 'PO ' . $number . ' telah dibuat untuk vendor ' . $vendor . '.',
            'sent_to_vendor' => 'PO ' . $number . ' telah dikirim ke vendor ' . $vendor . '.',
            default          => 'PO ' . $number . ' diperbarui (status: ' . $status . ').',
        };

        $payload = [
            'type'         => 'purchase_order',
            'action'       => $event->action,
            'id'           => $po->id,
            'po_number'    => $po->po_number,
            'status'       => $po->status,
            'warehouse_id' => $po->warehouse_id,
            'title'        => 'Purchase Order',
            'message'      => $message,
            'url'          => '/purchase-order/' . $po->id,
        ];

        $recipients = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['superuser', 'admin_ho'])
        )->get();

        foreach ($recipients as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(PurchaseOrderUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyAdminOnPurchaseOrder failed', [
            'po_id' => $event->po->id,
            'error' => $exception->getMessage(),
        ]);
    }
}