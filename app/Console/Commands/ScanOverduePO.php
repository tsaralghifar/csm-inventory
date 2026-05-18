<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Scan PO kredit yang mendekati atau sudah melewati jatuh tempo.
 *
 * Jalankan manual:
 *   php artisan po:scan-overdue
 *   php artisan po:scan-overdue --days=14
 *   php artisan po:scan-overdue --force
 *
 * Jadwalkan di Kernel.php / routes/console.php:
 *   Schedule::command('po:scan-overdue')->dailyAt('08:00');
 */
class ScanOverduePO extends Command
{
    protected $signature = 'po:scan-overdue
                            {--days=7 : Peringatan N hari sebelum jatuh tempo}
                            {--force  : Kirim notifikasi meski sudah dikirim hari ini}';

    protected $description = 'Scan PO kredit mendekati / melewati jatuh tempo dan kirim notifikasi';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Memulai scan PO kredit (batas peringatan: {$days} hari)...");

        $overdue = $this->fetchOverdue();
        $nearDue = $this->fetchNearDue($days);
        $total   = $overdue->count() + $nearDue->count();

        if ($total === 0) {
            $this->info('✅ Tidak ada PO kredit yang perlu di-alert.');
            return self::SUCCESS;
        }

        $this->printOverdueTable($overdue);
        $this->printNearDueTable($nearDue);
        $this->notifyRecipients($overdue, 'overdue');
        $this->notifyRecipients($nearDue, 'near_due');

        $this->info("✅ {$total} notifikasi terkirim.");

        return self::SUCCESS;
    }

    // ─── Fetch ────────────────────────────────────────────────────────────────

    private function fetchOverdue(): Collection
    {
        return PurchaseOrder::overdue()
            ->whereNotIn('status', [PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_COMPLETED])
            ->with(['creator', 'supplier'])
            ->get();
    }

    private function fetchNearDue(int $days): Collection
    {
        return PurchaseOrder::nearDue($days)
            ->whereDate('payment_due_date', '>=', now()->toDateString())
            ->whereNotIn('status', [PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_COMPLETED])
            ->with(['creator', 'supplier'])
            ->get();
    }

    // ─── Output ───────────────────────────────────────────────────────────────

    private function printOverdueTable(Collection $pos): void
    {
        if ($pos->isEmpty()) return;

        $this->error("🔴 {$pos->count()} PO sudah melewati jatuh tempo:");
        $this->table(
            ['No. PO', 'Vendor', 'Grand Total', 'Jatuh Tempo', 'Terlambat'],
            $pos->map(fn($po) => [
                $po->po_number,
                $po->vendor_name,
                'Rp ' . number_format($po->grand_total, 0, ',', '.'),
                $po->payment_due_date->format('d/m/Y'),
                abs($po->daysUntilDue()) . ' hari',
            ])->toArray()
        );
    }

    private function printNearDueTable(Collection $pos): void
    {
        if ($pos->isEmpty()) return;

        $this->warn("🟡 {$pos->count()} PO mendekati jatuh tempo:");
        $this->table(
            ['No. PO', 'Vendor', 'Grand Total', 'Jatuh Tempo', 'Sisa Hari'],
            $pos->map(fn($po) => [
                $po->po_number,
                $po->vendor_name,
                'Rp ' . number_format($po->grand_total, 0, ',', '.'),
                $po->payment_due_date->format('d/m/Y'),
                $po->daysUntilDue() . ' hari',
            ])->toArray()
        );
    }

    // ─── Notification ─────────────────────────────────────────────────────────

    private function notifyRecipients(Collection $pos, string $type): void
    {
        if ($pos->isEmpty()) return;

        $recipients = User::whereHas('roles', fn($q) =>
            $q->whereIn('name', ['superuser', 'admin_ho'])
        )->get();

        foreach ($pos as $po) {
            $payload = $this->buildPayload($po, $type);

            foreach ($recipients as $user) {
                $this->sendSafely($user, $payload, $po->id);
            }
        }
    }

    private function buildPayload(PurchaseOrder $po, string $type): array
    {
        $isOverdue = $type === 'overdue';
        $days      = abs($po->daysUntilDue());
        $daysLabel = $isOverdue ? "{$days} hari terlambat" : "{$days} hari lagi";
        $total     = 'Rp ' . number_format($po->grand_total, 0, ',', '.');
        $due       = $po->payment_due_date->format('d/m/Y');

        return [
            'type'         => 'purchase_order_payment',
            'action'       => $type,
            'id'           => $po->id,
            'po_number'    => $po->po_number,
            'payment_type' => $po->payment_type,
            'due_date'     => $po->payment_due_date->toDateString(),
            'title'        => $isOverdue
                                ? "⚠️ PO Kredit Overdue: {$po->po_number}"
                                : "🔔 Jatuh Tempo Segera: {$po->po_number}",
            'message'      => $isOverdue
                                ? "PO {$po->po_number} ({$po->vendor_name}) melewati jatuh tempo {$due} ({$daysLabel}). Total: {$total}."
                                : "PO {$po->po_number} ({$po->vendor_name}) jatuh tempo {$due} ({$daysLabel}). Total: {$total}.",
            'url'          => "/purchase-order/{$po->id}",
        ];
    }

    private function sendSafely(User $user, array $payload, int $poId): void
    {
        try {
            $user->notify(new DocumentStatusNotification($payload));
        } catch (\Throwable $e) {
            Log::error('ScanOverduePO: gagal kirim notifikasi', [
                'po_id'   => $poId,
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
