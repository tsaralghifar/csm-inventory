<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view-stocks', 'create-stock-in', 'create-stock-out', 'adjust-stock',
            'view-items', 'manage-items',
            'view-warehouses', 'manage-warehouses',
            'view-mr', 'create-mr', 'approve-mr', 'dispatch-mr',
            'authorize-mr-chief', 'approve-mr-manager', 'approve-mr-ho',
            'view-po', 'create-po', 'manage-po',
            'view-bon', 'create-bon', 'issue-bon',
            'view-sj', 'create-sj', 'receive-sj',
            'view-transfer', 'create-transfer', 'approve-transfer-admin',
            'approve-transfer-atasan', 'dispatch-transfer', 'receive-transfer',
            'view-pm', 'create-pm', 'approve-pm-site', 'approve-pm-ho',
            'view-fuel', 'manage-fuel',
            'view-apd', 'manage-apd',
            'view-toolbox', 'manage-toolbox',
            'view-reports', 'export-reports',
            'manage-users', 'manage-roles',
            'view-units', 'manage-units',
            'view-employees', 'manage-employees',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Sync role permissions
        $rolePermissions = [
            'chief_mekanik' => [
                'view-stocks', 'view-items',
                'view-mr', 'create-mr', 'authorize-mr-chief',
                'view-pm', 'create-pm',
                'view-reports', 'view-units',
            ],
            'purchasing' => [
                'view-stocks', 'create-stock-in',
                'view-items',
                'view-mr',
                'view-po', 'create-po', 'manage-po',
                'view-bon',
                'view-sj', 'create-sj', 'receive-sj',
                'view-reports',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($perms);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void {}
};
