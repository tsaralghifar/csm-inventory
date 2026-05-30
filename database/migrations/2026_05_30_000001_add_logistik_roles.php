<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Migration: Tambah role logistik_ho dan logistik_site
 *
 * Setelah migration ini berjalan, jalankan juga seeder untuk sinkronisasi permission:
 *   php artisan db:seed --class=RolePermissionSeeder
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Logistik HO ──────────────────────────────────────────────────────
        // Role dibuat kosong — permission ditentukan oleh superuser via UI.
        Role::firstOrCreate([
            'name'       => 'logistik_ho',
            'guard_name' => 'web',
        ]);

        // ── Logistik Site ────────────────────────────────────────────────────
        // Role dibuat kosong — permission ditentukan oleh superuser via UI.
        Role::firstOrCreate([
            'name'       => 'logistik_site',
            'guard_name' => 'web',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Role::where('name', 'logistik_ho')->first()?->delete();
        Role::where('name', 'logistik_site')->first()?->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
