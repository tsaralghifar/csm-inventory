<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\StokOpname;

class StokOpnameObserver
{
    private array $old = [];

    public function created(StokOpname $so): void
    {
        AuditLog::record(
            action:      'create',
            module:      'stok_opname',
            description: "Stok Opname dibuat — Gudang ID: {$so->warehouse_id}",
            subject:     $so,
            old:         null,
            new:         $this->snapshot($so),
        );
    }

    public function updating(StokOpname $so): void
    {
        $this->old = $this->snapshot($so->fresh() ?? $so);
    }

    public function updated(StokOpname $so): void
    {
        $new     = $this->snapshot($so);
        $changed = $this->diffOnly($this->old, $new);

        if (empty($changed['new'])) return;

        $desc = isset($changed['new']['status'])
            ? "Status Stok Opname berubah: {$changed['old']['status']} → {$changed['new']['status']}"
            : "Stok Opname diperbarui (ID #{$so->id})";

        AuditLog::record(
            action:      'update',
            module:      'stok_opname',
            description: $desc,
            subject:     $so,
            old:         $changed['old'],
            new:         $changed['new'],
        );
    }

    public function deleting(StokOpname $so): void
    {
        $this->old = $this->snapshot($so);
    }

    public function deleted(StokOpname $so): void
    {
        AuditLog::record(
            action:      'delete',
            module:      'stok_opname',
            description: "Stok Opname dihapus (ID #{$so->id})",
            subject:     $so,
            old:         $this->old,
            new:         null,
        );
    }

    private function snapshot(StokOpname $so): array
    {
        return array_filter([
            'warehouse_id' => $so->warehouse_id,
            'status'       => $so->status,
            'notes'        => $so->notes,
            'opname_date'  => $so->opname_date?->toDateString() ?? $so->getAttribute('opname_date'),
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
