<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccountingPayrollPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Bersihkan cache permission ────────────────────────────────────────
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permission Accounting ─────────────────────────────────────────────
        $accountingPermissions = [
            'view-accounting'    => 'Lihat Accounting',
            'manage-accounting'  => 'Kelola Accounting',
            'approve-accounting' => 'Approve Transaksi Accounting',
        ];

        // ── Permission Payroll ────────────────────────────────────────────────
        $payrollPermissions = [
            'view-payroll'    => 'Lihat Payroll',
            'manage-payroll'  => 'Kelola Payroll',
            'approve-payroll' => 'Approve & Bayar Payroll',
        ];

        $allNew = array_merge(
            array_keys($accountingPermissions),
            array_keys($payrollPermissions)
        );

        foreach ($allNew as $perm) {
            Permission::findOrCreate($perm);
        }

        // ── Role Accounting (baru) ────────────────────────────────────────────
        $accountingRole = Role::findOrCreate('accounting');
        $accountingRole->syncPermissions([
            'view-accounting',
            'manage-accounting',
            'approve-accounting',
            'view-payroll',
            'manage-payroll',
            'approve-payroll',
            // Akses laporan
            'view-reports',
            'export-reports',
        ]);

        // ── Update role superuser: tambahkan permission baru ──────────────────
        $superuser = Role::findByName('superuser');
        if ($superuser) {
            $superuser->givePermissionTo($allNew);
        }

        // ── Update role admin_ho: bisa lihat accounting & payroll ─────────────
        $adminHo = Role::findByName('admin_ho');
        if ($adminHo) {
            $adminHo->givePermissionTo([
                'view-accounting',
                'approve-accounting',
                'view-payroll',
                'approve-payroll',
            ]);
        }

        $this->command->info('✅ Permission Accounting & Payroll berhasil ditambahkan.');
        $this->command->info('✅ Role "accounting" berhasil dibuat.');
        $this->command->table(
            ['Permission', 'Deskripsi'],
            collect(array_merge($accountingPermissions, $payrollPermissions))
                ->map(fn($label, $perm) => [$perm, $label])
                ->values()
                ->toArray()
        );
    }
}
