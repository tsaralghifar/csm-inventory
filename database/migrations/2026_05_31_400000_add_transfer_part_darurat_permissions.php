<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permission khusus Transfer Part Darurat
        $perms = [
            'view-transfer-part',      // lihat daftar & detail
            'create-transfer-part',    // buat TP baru (site)
            'approve-transfer-part-chief',   // otorisasi chief mekanik
            'approve-transfer-part-manager', // approve manager
        ];

        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $roleMap = [
            'superuser'     => $perms,
            'admin_ho'      => ['view-transfer-part'],
            'logistik_ho'   => ['view-transfer-part'],
            'admin_site'    => ['view-transfer-part', 'create-transfer-part'],
            'logistik_site' => ['view-transfer-part', 'create-transfer-part'],
            'manager'       => ['view-transfer-part', 'approve-transfer-part-manager'],
            'chief_mekanik' => ['view-transfer-part', 'create-transfer-part', 'approve-transfer-part-chief'],
        ];

        foreach ($roleMap as $roleName => $rolePerms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) continue;
            foreach ($rolePerms as $perm) {
                if (!$role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = ['superuser','admin_ho','logistik_ho','admin_site','logistik_site','manager','chief_mekanik'];
        $perms = ['view-transfer-part','create-transfer-part','approve-transfer-part-chief','approve-transfer-part-manager'];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) $role->revokePermissionTo($perms);
        }

        foreach ($perms as $perm) {
            Permission::where('name', $perm)->where('guard_name', 'web')->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
