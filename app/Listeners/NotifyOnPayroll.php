<?php

namespace App\Listeners;

use App\Events\PayrollUpdated;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NotifyOnPayroll
 *
 * Kirim notifikasi in-app ke tim HR / payroll setiap kali
 * PayrollUpdated di-fire dari route payroll.
 *
 * Type yang ditangani:
 *   payroll   → created | updated | approved | paid
 *   pinjaman  → created | approved
 *   komponen  → updated
 */
class NotifyOnPayroll implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(PayrollUpdated $event): void
    {
        $type   = $event->type;
        $action = $event->action;
        $id     = $event->id;

        // Hanya proses action yang perlu notifikasi
        $notifiableActions = ['created', 'approved', 'paid'];
        if (!in_array($action, $notifiableActions)) {
            return;
        }

        $label   = $this->typeLabel($type);
        $message = $this->buildMessage($label, $action, $id);
        $url     = $this->buildUrl($type, $id);

        $payload = [
            'type'    => 'payroll',
            'subtype' => $type,
            'action'  => $action,
            'id'      => $id,
            'title'   => 'Payroll - ' . $label,
            'message' => $message,
            'url'     => $url,
        ];

        match (true) {
            // Penggajian disetujui atau dibayar → notif seluruh tim HR
            $type === 'payroll' && in_array($action, ['approved', 'paid'])
                => $this->notifyPayrollTeam($payload),

            // Penggajian baru dibuat → notif approver payroll
            $type === 'payroll' && $action === 'created'
                => $this->notifyApprovers($payload),

            // Pinjaman baru → notif approver payroll
            $type === 'pinjaman' && $action === 'created'
                => $this->notifyApprovers($payload),

            // Pinjaman disetujui → notif seluruh tim HR
            $type === 'pinjaman' && $action === 'approved'
                => $this->notifyPayrollTeam($payload),

            default => $this->notifySuperusers($payload),
        };
    }

    private function buildMessage(string $label, string $action, ?int $id): string
    {
        $idStr = $id ? ' #' . $id : '';

        return match ($action) {
            'created'  => $label . $idStr . ' baru telah dibuat dan menunggu persetujuan.',
            'approved' => $label . $idStr . ' telah disetujui.',
            'paid'     => $label . $idStr . ' telah dibayarkan kepada karyawan.',
            default    => $label . $idStr . ' diperbarui.',
        };
    }

    private function buildUrl(string $type, ?int $id): string
    {
        return match ($type) {
            'payroll'   => '/payroll/periods' . ($id ? '/' . $id : ''),
            'pinjaman'  => '/payroll/loans' . ($id ? '/' . $id : ''),
            'komponen'  => '/payroll/salary-components',
            default     => '/payroll',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'payroll'  => 'Periode Penggajian',
            'pinjaman' => 'Pinjaman Karyawan',
            'komponen' => 'Komponen Gaji',
            default    => ucfirst($type),
        };
    }

    /**
     * Notif ke user yang punya permission approve-payroll.
     */
    private function notifyApprovers(array $payload): void
    {
        $approvers = User::whereHas('roles.permissions', fn ($q) => $q->where('name', 'approve-payroll'))
            ->orWhereHas('permissions', fn ($q) => $q->where('name', 'approve-payroll'))
            ->get();

        if ($approvers->isEmpty()) {
            $this->notifySuperusers($payload);
            return;
        }

        foreach ($approvers as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    /**
     * Notif ke semua user dengan permission view-payroll atau approve-payroll.
     */
    private function notifyPayrollTeam(array $payload): void
    {
        $users = User::where(function ($q) {
            $q->whereHas('roles.permissions', fn ($rq) => $rq->whereIn('name', ['view-payroll', 'approve-payroll']))
              ->orWhereHas('permissions', fn ($rq) => $rq->whereIn('name', ['view-payroll', 'approve-payroll']))
              ->orWhereHas('roles', fn ($rq) => $rq->where('name', 'superuser'));
        })->get();

        foreach ($users as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    private function notifySuperusers(array $payload): void
    {
        $superusers = User::whereHas('roles', fn ($q) => $q->where('name', 'superuser'))->get();
        foreach ($superusers as $user) {
            $user->notify(new DocumentStatusNotification($payload));
        }
    }

    public function failed(PayrollUpdated $event, \Throwable $exception): void
    {
        \Log::error('NotifyOnPayroll failed', [
            'type'   => $event->type,
            'action' => $event->action,
            'id'     => $event->id,
            'error'  => $exception->getMessage(),
        ]);
    }
}
