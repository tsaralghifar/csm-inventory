<?php

namespace App\Listeners;

use App\Events\AccountingUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyOnAccounting
 *
 * Kirim notifikasi in-app ke tim accounting setiap kali
 * AccountingUpdated di-fire dari route accounting.
 *
 * Type yang ditangani:
 *   supplier     → created | updated
 *   invoice      → created | updated
 *   payment      → created | approved | rejected
 *   kas-kecil    → created | approved
 *   kas-besar    → created | approved
 *   jurnal       → created | approved (posted)
 */
class NotifyOnAccounting implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(AccountingUpdated $event): void
    {
        $type   = $event->type;
        $action = $event->action;
        $id     = $event->id;

        // Hanya proses action yang relevan untuk notifikasi
        $notifiableActions = ['created', 'approved', 'rejected'];
        if (!in_array($action, $notifiableActions)) {
            return;
        }

        $label   = $this->typeLabel($type);
        $message = $this->buildMessage($label, $action, $id);
        $url     = $this->buildUrl($type, $id);

        $payload = [
            'type'    => 'accounting',
            'subtype' => $type,
            'action'  => $action,
            'id'      => $id,
            'title'   => 'Accounting - ' . $label,
            'message' => $message,
            'url'     => $url,
        ];

        match ($action) {
            // Disetujui/ditolak → notif semua tim accounting & superuser
            'approved', 'rejected' => $this->notifyAccountingTeam($payload),

            // Dibuat → notif hanya pemegang approve-accounting (butuh tindakan)
            'created' => $this->notifyApprovers($type, $payload),

            default => null,
        };
    }

    private function buildMessage(string $label, string $action, ?int $id): string
    {
        $idStr = $id ? ' #' . $id : '';

        return match ($action) {
            'created'  => $label . $idStr . ' baru telah dibuat dan menunggu persetujuan.',
            'approved' => $label . $idStr . ' telah disetujui.',
            'rejected' => $label . $idStr . ' ditolak.',
            default    => $label . $idStr . ' diperbarui.',
        };
    }

    private function buildUrl(string $type, ?int $id): string
    {
        return match ($type) {
            'supplier'  => '/accounting/supplier' . ($id ? '/' . $id : ''),
            'invoice'   => '/accounting/invoice' . ($id ? '/' . $id : ''),
            'payment'   => '/accounting/payment' . ($id ? '/' . $id : ''),
            'kas-kecil' => '/accounting/kas-kecil',
            'kas-besar' => '/accounting/kas-besar',
            'jurnal'    => '/accounting/jurnal' . ($id ? '/' . $id : ''),
            default     => '/accounting',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'supplier'  => 'Supplier',
            'invoice'   => 'Invoice Supplier',
            'payment'   => 'Pembayaran Supplier',
            'kas-kecil' => 'Kas Kecil',
            'kas-besar' => 'Kas Besar',
            'jurnal'    => 'Jurnal Entry',
            default     => ucfirst($type),
        };
    }

    /**
     * Notif ke user yang punya permission approve-accounting.
     * Digunakan saat dokumen baru dibuat dan butuh approval.
     */
    private function notifyApprovers(string $type, array $payload): void
    {
        // Kas kecil/besar tidak perlu notif saat created karena langsung diproses
        if (in_array($type, ['kas-kecil', 'kas-besar'])) {
            return;
        }

        $approvers = User::whereHas('roles.permissions', fn ($q) => $q->where('name', 'approve-accounting'))
            ->orWhereHas('permissions', fn ($q) => $q->where('name', 'approve-accounting'))
            ->get();

        if ($approvers->isEmpty()) {
            $this->notifyAccountingTeam($payload);
            return;
        }

        foreach ($approvers as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    /**
     * Notif ke semua user dengan permission view-accounting atau superuser.
     */
    private function notifyAccountingTeam(array $payload): void
    {
        $users = User::where(function ($q) {
            $q->whereHas('roles.permissions', fn ($rq) => $rq->whereIn('name', ['view-accounting', 'approve-accounting']))
              ->orWhereHas('permissions', fn ($rq) => $rq->whereIn('name', ['view-accounting', 'approve-accounting']))
              ->orWhereHas('roles', fn ($rq) => $rq->where('name', 'superuser'));
        })->get();

        foreach ($users as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(AccountingUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyOnAccounting failed', [
            'type'   => $event->type,
            'action' => $event->action,
            'id'     => $event->id,
            'error'  => $exception->getMessage(),
        ]);
    }
}
