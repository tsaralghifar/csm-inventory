<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Item;

/**
 * ItemObserver
 *
 * Observer dedicated untuk model Item.
 * Memberikan kontrol penuh atas deskripsi, field yang dicatat,
 * serta format before/after yang informatif.
 *
 * Daftarkan di AppServiceProvider:
 *   Item::observe(ItemObserver::class);
 */
class ItemObserver
{
    // Simpan snapshot sebelum update/delete
    private array $old = [];

    public function creating(Item $item): void
    {
        // tidak perlu old
    }

    public function created(Item $item): void
    {
        AuditLog::record(
            action:      'create',
            module:      'items',
            description: "Barang baru ditambahkan: {$item->name}" . ($item->part_number ? " [{$item->part_number}]" : ''),
            subject:     $item,
            old:         null,
            new:         $this->snapshot($item),
        );
    }

    public function updating(Item $item): void
    {
        // Snapshot SEBELUM disimpan — ini kuncinya
        $this->old = $this->snapshot($item->getOriginal() ? $item->fresh() : $item);
    }

    public function updated(Item $item): void
    {
        $new     = $this->snapshot($item);
        $changed = $this->diffOnly($this->old, $new);

        if (empty($changed['new'])) return; // tidak ada perubahan relevan

        AuditLog::record(
            action:      'update',
            module:      'items',
            description: "Barang diperbarui: {$item->name}" . ($item->part_number ? " [{$item->part_number}]" : ''),
            subject:     $item,
            old:         $changed['old'],
            new:         $changed['new'],
        );
    }

    public function deleting(Item $item): void
    {
        $this->old = $this->snapshot($item);
    }

    public function deleted(Item $item): void
    {
        AuditLog::record(
            action:      'delete',
            module:      'items',
            description: "Barang dihapus: {$item->name}" . ($item->part_number ? " [{$item->part_number}]" : ''),
            subject:     $item,
            old:         $this->old,
            new:         null,
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function snapshot(Item|array $item): array
    {
        $attrs = $item instanceof Item ? $item->getAttributes() : (array) $item;
        return array_filter([
            'name'          => $attrs['name'] ?? null,
            'part_number'   => $attrs['part_number'] ?? null,
            'brand'         => $attrs['brand'] ?? null,
            'unit'          => $attrs['unit'] ?? null,
            'min_stock'     => $attrs['min_stock'] ?? null,
            'price'         => $attrs['price'] ?? null,
            'location_code' => $attrs['location_code'] ?? null,
            'is_active'     => $attrs['is_active'] ?? null,
            'category_id'   => $attrs['category_id'] ?? null,
            'description'   => $attrs['description'] ?? null,
        ], fn($v) => $v !== null);
    }

    /** Kembalikan [old => [...], new => [...]] hanya untuk field yang benar-benar berubah */
    private function diffOnly(array $old, array $new): array
    {
        $changedKeys = array_keys(array_filter($new, fn($v, $k) => ($old[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));
        return [
            'old' => array_intersect_key($old, array_flip($changedKeys)),
            'new' => array_intersect_key($new, array_flip($changedKeys)),
        ];
    }
}
