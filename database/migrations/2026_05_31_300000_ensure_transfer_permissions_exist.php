<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Pastikan semua permission transfer ada di database DAN ter-assign ke role yang tepat.
 * Migration ini aman dijalankan berulang kali (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Pastikan semua permission transfer ada
        $transferPerms = [
            'view-transfer',
            'create-transfer',
            'approve-transfer-admin',
            'approve-transfer-atasan',
            'dispatch-transfer',
            'receive-transfer',
        ];

        foreach ($transferPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 2. Assign ke setiap role sesuai tugasnya
        $roleMap = [
            'superuser'     => $transferPerms, // semua
            'admin_ho'      => ['view-transfer', 'approve-transfer-admin', 'dispatch-transfer'],
            'logistik_ho'   => ['view-transfer', 'approve-transfer-admin', 'dispatch-transfer'],
            'admin_site'    => ['view-transfer', 'create-transfer'],
            'logistik_site' => ['view-transfer', 'create-transfer'],
            'manager'       => ['view-transfer', 'approve-transfer-atasan'],
            'chief_mekanik' => ['view-transfer', 'create-transfer'],
        ];

        foreach ($roleMap as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) continue;

            foreach ($perms as $perm) {
                $permission = Permission::where('name', $perm)->where('guard_name', 'web')->first();
                if ($permission && !$role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = ['admin_ho', 'logistik_ho', 'admin_site', 'logistik_site', 'manager', 'chief_mekanik'];
        $perms = ['view-transfer', 'create-transfer', 'approve-transfer-admin',
                  'approve-transfer-atasan', 'dispatch-transfer', 'receive-transfer'];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                foreach ($perms as $perm) {
                    if ($role->hasPermissionTo($perm)) $role->revokePermissionTo($perm);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
