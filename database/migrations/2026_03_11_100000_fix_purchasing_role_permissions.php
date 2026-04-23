<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Definisi lengkap permission per role
        // Edit file ini setiap kali ada perubahan hak akses, lalu jalankan php artisan migrate
        $rolePermissions = [
            'superuser' => null, // null = semua permission (dihandle di bawah)

            'admin_ho' => [
                'view-stocks', 'create-stock-in', 'create-stock-out', 'adjust-stock',
                'view-items', 'manage-items',
                'view-warehouses', 'manage-warehouses',
                'view-mr', 'approve-mr', 'dispatch-mr', 'approve-mr-ho',
                'view-po', 'view-bon', 'create-bon', 'issue-bon',
                'view-sj', 'receive-sj',
                'view-retur',
                'view-pm', 'approve-pm-site', 'approve-pm-ho',
                'view-fuel', 'manage-fuel',
                'view-apd', 'manage-apd',
                'view-toolbox', 'manage-toolbox',
                'view-reports', 'export-reports',
                'view-units', 'manage-units',
                'view-employees', 'manage-employees',
            ],

            'admin_site' => [
                'view-stocks', 'create-stock-out', 'create-stock-in',
                'view-items',
                'view-warehouses',
                'view-mr', 'create-mr',
                'view-bon',
                'view-sj',
                'view-pm', 'create-pm', 'approve-pm-site',
                'view-fuel', 'manage-fuel',
                'view-apd',
                'view-toolbox',
                'view-reports',
                'view-units',
                'view-employees',
            ],

            'manager' => [
                'view-stocks',
                'view-items',
                'view-warehouses',
                'view-mr', 'approve-mr-manager',
                'view-po',
                'view-bon',
                'view-sj',
                'view-pm',
                'view-fuel',
                'view-apd',
                'view-toolbox',
                'view-reports', 'export-reports',
                'view-units',
            ],

            'chief_mekanik' => [
                'view-stocks',
                'view-items',
                'view-mr', 'create-mr', 'authorize-mr-chief',
                'view-pm', 'create-pm',
                'view-reports',
                'view-units',
            ],

            'purchasing' => [
                'view-stocks', 'create-stock-in',
                'view-items',
                'view-mr',
                'view-po', 'create-po', 'manage-po',
                'view-bon',
                'view-sj', 'create-sj', 'receive-sj',
                'view-retur',
                'view-reports',
            ],

            'viewer' => [
                'view-stocks',
                'view-items',
                'view-warehouses',
                'view-mr', 'create-mr',
                'view-pm', 'create-pm',
                'view-fuel',
                'view-apd',
                'view-toolbox',
                'view-reports',
                'view-units',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (!$role) continue;

            if ($perms === null) {
                // Superuser: beri semua permission
                $role->syncPermissions(Permission::where('guard_name', 'web')->get());
            } else {
                $role->syncPermissions($perms);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void {}
};
