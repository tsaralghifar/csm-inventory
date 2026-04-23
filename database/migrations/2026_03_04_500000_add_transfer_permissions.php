<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view-transfer',
            'create-transfer',
            'approve-transfer-admin',
            'approve-transfer-atasan',
            'dispatch-transfer',
            'receive-transfer',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Reset cache permission Spatie agar langsung aktif
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'view-transfer',
            'create-transfer',
            'approve-transfer-admin',
            'approve-transfer-atasan',
            'dispatch-transfer',
            'receive-transfer',
        ])->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
