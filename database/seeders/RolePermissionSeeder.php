<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * RolePermissionSeeder — SATU-SATUNYA sumber kebenaran untuk permission.
 *
 * ATURAN WAJIB:
 * 1. Setiap kali ada perubahan hak akses (tambah fitur, ubah role, dsb),
 *    HANYA edit file ini — jangan ubah file migration lama.
 * 2. Setelah edit, jalankan: php artisan db:seed --class=RolePermissionSeeder
 * 3. Karena menggunakan syncPermissions(), aman dijalankan berulang kali
 *    tanpa duplikasi data.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─────────────────────────────────────────────
        // DAFTAR SEMUA PERMISSION
        // Tambahkan permission baru di sini jika ada fitur baru
        // ─────────────────────────────────────────────
        $allPermissions = [
            // Stok
            'view-stocks', 'create-stock-in', 'create-stock-out', 'adjust-stock',
            // Barang
            'view-items', 'manage-items',
            // Gudang
            'view-warehouses', 'manage-warehouses',
            // Material Request
            'view-mr', 'create-mr', 'approve-mr', 'dispatch-mr',
            'authorize-mr-chief', 'approve-mr-manager', 'approve-mr-ho',
            // Purchase Order
            'view-po', 'create-po', 'manage-po',
            // Bon Pengeluaran
            'view-bon', 'create-bon', 'issue-bon',
            // Surat Jalan
            'view-sj', 'create-sj', 'receive-sj',
            // Transfer Barang
            'view-transfer', 'create-transfer',
            'approve-transfer-admin', 'approve-transfer-atasan',
            'dispatch-transfer', 'receive-transfer',
            // Permintaan Material
            'view-pm', 'create-pm', 'approve-pm-site', 'approve-pm-ho',
            // Fuel
            'view-fuel', 'manage-fuel',
            // APD
            'view-apd', 'manage-apd',
            // Toolbox
            'view-toolbox', 'manage-toolbox',
            // Laporan
            'view-reports', 'export-reports',
            // Manajemen User
            'manage-users', 'manage-roles',
            // Unit Alat
            'view-units', 'manage-units',
            // Karyawan
            'view-employees', 'manage-employees',
            // Retur Barang
            'view-retur', 'create-retur', 'confirm-retur',
            // Accounting
            'view-accounting', 'manage-accounting', 'approve-accounting',
            // Payroll
            'view-payroll', 'manage-payroll', 'approve-payroll',
        ];

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ─────────────────────────────────────────────
        // DEFINISI HAK AKSES PER ROLE
        //
        // ROLE          | TANGGUNG JAWAB
        // superuser     | Akses penuh semua fitur
        // admin_ho      | Kelola gudang HO, approve PM, buat bon pengeluaran, lihat accounting & payroll
        // admin_site    | Kelola gudang site, buat PM
        // manager       | Approval manager untuk MR Part
        // chief_mekanik | Otorisasi MR Part dari mekanik
        // purchasing    | Buat & kelola PO, buat surat jalan
        // accounting    | Kelola accounting (kas, invoice, supplier) dan payroll
        // viewer        | Hanya lihat data
        // ─────────────────────────────────────────────
        $rolePermissions = [

            'superuser' => $allPermissions,

            'admin_ho' => [
                'view-stocks', 'create-stock-in', 'create-stock-out', 'adjust-stock',
                'view-items', 'manage-items',
                'view-warehouses', 'manage-warehouses',
                'view-mr', 'approve-mr', 'dispatch-mr', 'approve-mr-ho',
                'view-po',
                'view-bon', 'create-bon', 'issue-bon',
                'view-sj', 'receive-sj',
                'view-transfer', 'approve-transfer-admin', 'receive-transfer',
                'view-pm', 'approve-pm-site', 'approve-pm-ho',
                'view-fuel', 'manage-fuel',
                'view-apd', 'manage-apd',
                'view-toolbox', 'manage-toolbox',
                'view-reports', 'export-reports',
                'view-units', 'manage-units',
                'view-employees', 'manage-employees',
                'view-retur',
                // Accounting & Payroll — lihat dan approve saja
                'view-accounting', 'approve-accounting',
                'view-payroll', 'approve-payroll',
            ],

            'admin_site' => [
                'view-stocks', 'create-stock-in', 'create-stock-out',
                'view-items',
                'view-warehouses',
                'view-mr', 'create-mr',
                'view-bon',
                'view-sj',
                'view-transfer', 'create-transfer', 'approve-transfer-atasan', 'dispatch-transfer',
                'view-pm', 'create-pm', 'approve-pm-site',
                'view-fuel', 'manage-fuel',
                'view-apd',
                'view-toolbox',
                'view-reports',
                'view-units',
                'view-employees',
                'view-retur',
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

            // Role baru: Accounting
            'accounting' => [
                // Lihat data inventory (read-only)
                'view-stocks',
                'view-items',
                'view-warehouses',
                'view-reports', 'export-reports',
                'view-employees',
                // Accounting penuh
                'view-accounting', 'manage-accounting', 'approve-accounting',
                // Payroll penuh
                'view-payroll', 'manage-payroll', 'approve-payroll',
            ],

            'viewer' => [
                'view-stocks',
                'view-items',
                'view-warehouses',
                'view-mr',
                'view-pm',
                'view-fuel',
                'view-apd',
                'view-toolbox',
                'view-reports',
                'view-units',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Permission semua role berhasil disinkronkan.');
    }
}