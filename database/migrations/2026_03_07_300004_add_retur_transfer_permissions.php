<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permission baru untuk Retur Barang
        $new = ['view-retur', 'create-retur', 'confirm-retur'];
        foreach ($new as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Assign view-retur ke role yang seharusnya bisa akses
        $returRoles = ['superuser', 'admin_ho', 'admin_site', 'purchasing'];
        foreach ($returRoles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(array_filter($new, fn($p) => 
                    Permission::where('name', $p)->where('guard_name', 'web')->exists()
                ));
            }
        }

        // Pastikan view-transfer ada untuk Transfer Barang sidebar
        Permission::firstOrCreate(['name' => 'view-transfer', 'guard_name' => 'web']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::whereIn('name', ['view-retur', 'create-retur', 'confirm-retur'])
            ->where('guard_name', 'web')->delete();
    }
};
