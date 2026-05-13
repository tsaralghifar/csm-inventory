<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;

class UserObserver
{
    private array $old = [];

    public function created(User $user): void
    {
        AuditLog::record(
            action:      'create',
            module:      'users',
            description: "User baru dibuat: {$user->name} ({$user->email})",
            subject:     $user,
            old:         null,
            new:         $this->snapshot($user),
        );
    }

    public function updating(User $user): void
    {
        $this->old = $this->snapshot($user->fresh() ?? $user);
    }

    public function updated(User $user): void
    {
        $new     = $this->snapshot($user);
        $changed = $this->diffOnly($this->old, $new);

        if (empty($changed['new'])) return;

        // Deteksi perubahan status aktif
        $desc = isset($changed['new']['is_active'])
            ? ($changed['new']['is_active'] ? "User diaktifkan: {$user->name}" : "User dinonaktifkan: {$user->name}")
            : "Data user diperbarui: {$user->name} ({$user->email})";

        AuditLog::record(
            action:      'update',
            module:      'users',
            description: $desc,
            subject:     $user,
            old:         $changed['old'],
            new:         $changed['new'],
        );
    }

    public function deleting(User $user): void
    {
        $this->old = $this->snapshot($user);
    }

    public function deleted(User $user): void
    {
        AuditLog::record(
            action:      'delete',
            module:      'users',
            description: "User dihapus: {$user->name} ({$user->email})",
            subject:     $user,
            old:         $this->old,
            new:         null,
        );
    }

    private function snapshot(User $user): array
    {
        return array_filter([
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'position'     => $user->position,
            'warehouse_id' => $user->warehouse_id,
            'is_active'    => $user->is_active,
            // password & remember_token TIDAK dicatat
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
