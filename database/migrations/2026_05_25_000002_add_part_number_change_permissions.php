<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Tidak ada permission baru yang perlu ditambahkan ──────────────────
        // Fitur koreksi part number menggunakan permission 'manage-po' yang
        // sudah ada. Permission ini sudah dimiliki oleh: superuser, purchasing.
        //
        // Yang perlu diperbaiki adalah:
        // 1. Role 'purchasing' perlu mendapat 'view-pm' agar dapat melihat
        //    konteks PM saat melakukan koreksi part number di PO.
        // 2. Label 'view-audit-log' belum ada di permLabel Roles.vue (frontend).

        // ── Perbaikan: tambah view-pm ke role purchasing ──────────────────────
        $purchasing = Role::where('name', 'purchasing')->where('guard_name', 'web')->first();

        if ($purchasing) {
            $viewPm = Permission::firstOrCreate(
                ['name' => 'view-pm', 'guard_name' => 'web']
            );

            if (! $purchasing->hasPermissionTo('view-pm')) {
                $purchasing->givePermissionTo($viewPm);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $purchasing = Role::where('name', 'purchasing')->where('guard_name', 'web')->first();
        $purchasing?->revokePermissionTo('view-pm');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
