<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\PurchaseOrder;

class PurchaseOrderObserver
{
    private array $old = [];

    public function created(PurchaseOrder $po): void
    {
        AuditLog::record(
            action:      'create',
            module:      'po',
            description: "Purchase Order dibuat: {$po->po_number} — Vendor: {$po->vendor_name}",
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
        $new     = $this->snapshot($po);
        $changed = $this->diffOnly($this->old, $new);

        // Deskripsi spesifik jika hanya status yang berubah
        $desc = isset($changed['new']['status'])
            ? "Status PO {$po->po_number} berubah: {$changed['old']['status']} → {$changed['new']['status']}"
            : "Purchase Order diperbarui: {$po->po_number}";

        if (empty($changed['new'])) return;

        AuditLog::record(
            action:      'update',
            module:      'po',
            description: $desc,
            subject:     $po,
            old:         $changed['old'],
            new:         $changed['new'],
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

    // ─── Snapshot & diff ─────────────────────────────────────────────────────

    private function snapshot(PurchaseOrder $po): array
    {
        return array_filter([
            'po_number'       => $po->po_number,
            'vendor_name'     => $po->vendor_name,
            'vendor_contact'  => $po->vendor_contact,
            'status'          => $po->status,
            'delivery_status' => $po->delivery_status,
            'total_amount'    => $po->total_amount,
            'ppn_percent'     => $po->ppn_percent,
            'grand_total'     => $po->grand_total,
            'diskon_persen'   => $po->diskon_persen,
            'expected_date'   => $po->expected_date?->toDateString(),
            'notes'           => $po->notes,
            'warehouse_id'    => $po->warehouse_id,
        ], fn($v) => $v !== null);
    }

    private function diffOnly(array $old, array $new): array
    {
        $changedKeys = array_keys(array_filter($new, fn($v, $k) => ($old[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));
        return [
            'old' => array_intersect_key($old, array_flip($changedKeys)),
            'new' => array_intersect_key($new, array_flip($changedKeys)),
        ];
    }
}
