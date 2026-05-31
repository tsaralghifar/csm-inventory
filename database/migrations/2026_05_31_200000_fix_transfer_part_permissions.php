<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Tambah permission view-transfer ke role yang terlibat dalam Transfer Part Darurat:
 * - admin_ho      : lihat semua + approve (dispatch)
 * - admin_site    : buat transfer part dari site
 * - manager       : approve transfer part
 * - chief_mekanik : buat + otorisasi awal
 * - logistik_ho   : lihat semua transfer (sejajar admin_ho)
 * - logistik_site : lihat transfer dari gudang sendiri
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Pastikan semua permission transfer sudah ada
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

        // Mapping role => permission transfer yang diberikan
        $roleMap = [
            'admin_ho'      => ['view-transfer', 'approve-transfer-admin', 'dispatch-transfer'],
            'admin_site'    => ['view-transfer', 'create-transfer'],
            'manager'       => ['view-transfer', 'approve-transfer-atasan'],
            'chief_mekanik' => ['view-transfer', 'create-transfer'],
            'logistik_ho'   => ['view-transfer', 'approve-transfer-admin', 'dispatch-transfer'],
            'logistik_site' => ['view-transfer', 'create-transfer'],
        ];

        foreach ($roleMap as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) continue;
            $role->givePermissionTo(array_filter($perms, fn($p) =>
                Permission::where('name', $p)->where('guard_name', 'web')->exists()
            ));
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = ['admin_ho', 'admin_site', 'manager', 'chief_mekanik', 'logistik_ho', 'logistik_site'];
        $perms = ['view-transfer', 'approve-transfer-admin', 'approve-transfer-atasan', 'dispatch-transfer', 'create-transfer'];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) $role->revokePermissionTo($perms);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
