<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\PurchaseOrder;

class PurchaseOrderObserver
{
    private array $old = [];

    // ─── Hooks ────────────────────────────────────────────────────────────────

    public function created(PurchaseOrder $po): void
    {
        AuditLog::record(
            action:      'create',
            module:      'po',
            description: "Purchase Order dibuat: {$po->po_number} — {$po->vendor_name} ({$po->paymentLabel()})",
            subject:     $po,
            old:         null,
            new:         $this->snapshot($po),
        );
    }

    public function updating(PurchaseOrder $po): void
    {
        $this->old = $this->snapshot($po->fresh() ?? $po);
    }

    public function updated(PurchaseOrder $po): void
    {
        $diff = $this->diff($this->old, $this->snapshot($po));

        if (empty($diff['new'])) return;

        AuditLog::record(
            action:      'update',
            module:      'po',
            description: $this->updateDescription($po, $diff),
            subject:     $po,
            old:         $diff['old'],
            new:         $diff['new'],
        );
    }

    public function deleting(PurchaseOrder $po): void
    {
        $this->old = $this->snapshot($po);
    }

    public function deleted(PurchaseOrder $po): void
    {
        AuditLog::record(
            action:      'delete',
            module:      'po',
            description: "Purchase Order dihapus: {$po->po_number}",
            subject:     $po,
            old:         $this->old,
            new:         null,
        );
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function snapshot(PurchaseOrder $po): array
    {
        // Do NOT use array_filter here — dropping null fields causes false positives
        // in diff() and makes updateDescription() throw "Undefined array key" errors
        // when old values are absent but new values exist for the same key.
        return [
            'po_number'         => $po->po_number,
            'vendor_name'       => $po->vendor_name,
            'vendor_contact'    => $po->vendor_contact,
            'status'            => $po->status,
            'delivery_status'   => $po->delivery_status,
            'total_amount'      => $po->total_amount,
            'ppn_percent'       => $po->ppn_percent,
            'grand_total'       => $po->grand_total,
            'diskon_persen'     => $po->diskon_persen,
            'expected_date'     => $po->expected_date?->toDateString(),
            'notes'             => $po->notes,
            'warehouse_id'      => $po->warehouse_id,
            'payment_type'      => $po->payment_type,
            'payment_term_days' => $po->payment_term_days,
            'payment_due_date'  => $po->payment_due_date?->toDateString(),
        ];
    }

    private function diff(array $old, array $new): array
    {
        $changed = array_keys(
            array_filter($new, fn($v, $k) => ($old[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH)
        );

        return [
            'old' => array_intersect_key($old, array_flip($changed)),
            'new' => array_intersect_key($new, array_flip($changed)),
        ];
    }

    private function updateDescription(PurchaseOrder $po, array $diff): string
    {
        if (isset($diff['new']['status'])) {
            $oldStatus = $diff['old']['status'] ?? '-';
            return "Status PO {$po->po_number}: {$oldStatus} → {$diff['new']['status']}";
        }

        if (isset($diff['new']['delivery_status'])) {
            $oldDs = $diff['old']['delivery_status'] ?? 'belum diterima';
            $newDs = $diff['new']['delivery_status'] ?? 'belum diterima';
            return "Delivery status PO {$po->po_number}: {$oldDs} → {$newDs}";
        }

        if (isset($diff['new']['payment_type']) || isset($diff['new']['payment_term_days'])) {
            return "Pembayaran PO {$po->po_number} diubah: {$po->paymentLabel()}";
        }

        return "Purchase Order diperbarui: {$po->po_number}";
    }
}