<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
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
                'view-reports',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => 'web',
            ]);

            foreach ($permissions as $perm) {
                Permission::firstOrCreate([
                    'name'       => $perm,
                    'guard_name' => 'web',
                ]);
            }

            $role->syncPermissions($permissions);
        }
    }

    public function down(): void
    {
        Role::whereIn('name', ['chief_mekanik', 'purchasing'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
