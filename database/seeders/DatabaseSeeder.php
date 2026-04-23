<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Permission & role dikelola sepenuhnya oleh RolePermissionSeeder.
        // Untuk mengubah hak akses, edit RolePermissionSeeder lalu jalankan:
        //   php artisan db:seed --class=RolePermissionSeeder
        $this->call(RolePermissionSeeder::class);

        // Gudang HO
        $warehouseHO = Warehouse::firstOrCreate(
            ['code' => 'HO-CSM'],
            [
                'name'     => 'Gudang Head Office CSM',
                'type'     => 'ho',
                'location' => 'Samarinda',
                'address'  => 'Jl. Utama No. 1, Samarinda, Kalimantan Timur',
                'pic_name' => 'Admin HO',
                'is_active' => true,
            ]
        );

        $sites = [
            ['code' => 'SITE-LOA', 'name' => 'Gudang Site Loajanan',    'location' => 'Loajanan, Kutai Kartanegara'],
            ['code' => 'SITE-PLK', 'name' => 'Gudang Site Palangkaraya','location' => 'Palangkaraya, Kalimantan Tengah'],
        ];
        foreach ($sites as $site) {
            Warehouse::firstOrCreate(['code' => $site['code']], array_merge($site, ['type' => 'site', 'is_active' => true]));
        }

        // Superuser
        $superuser = User::firstOrCreate(
            ['email' => 'superuser@csm.co.id'],
            [
                'name'               => 'Super User CSM',
                'password'           => bcrypt('superuser123'),
                'position'           => 'System Administrator',
                'warehouse_id'       => $warehouseHO->id,
                'is_active'          => true,
                'email_verified_at'  => now(),
            ]
        );
        $superuser->syncRoles(['superuser']);

        // Admin HO
        $adminHO = User::firstOrCreate(
            ['email' => 'admin.ho@csm.co.id'],
            [
                'name'              => 'Admin HO CSM',
                'password'          => bcrypt('admin123'),
                'position'          => 'Admin Gudang HO',
                'warehouse_id'      => $warehouseHO->id,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
        $adminHO->syncRoles(['admin_ho']);

        // ── User Accounting (baru) ──────────────────────────────────────────
        $accounting = User::firstOrCreate(
            ['email' => 'accounting@csm.co.id'],
            [
                'name'              => 'Accounting CSM',
                'password'          => bcrypt('accounting123'),
                'position'          => 'Staff Accounting',
                'warehouse_id'      => $warehouseHO->id,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
        $accounting->syncRoles(['accounting']);

        // Categories
        $categories = [
            ['code' => 'SPARE',  'name' => 'Sparepart',      'description' => 'Suku cadang alat berat'],
            ['code' => 'OLI',    'name' => 'Oli & Pelumas',  'description' => 'Oli mesin, hydraulic, gear'],
            ['code' => 'FILTER', 'name' => 'Filter',         'description' => 'Oil filter, fuel filter, air filter'],
            ['code' => 'APD',    'name' => 'APD',            'description' => 'Alat Pelindung Diri'],
            ['code' => 'TOOL',   'name' => 'Toolbox',        'description' => 'Peralatan mekanik'],
            ['code' => 'CONSUM', 'name' => 'Consumable',     'description' => 'Material habis pakai'],
            ['code' => 'ELEC',   'name' => 'Elektrikal',     'description' => 'Komponen listrik dan elektrik'],
            ['code' => 'HYDRA',  'name' => 'Hydraulic',      'description' => 'Komponen hidrolik'],
            ['code' => 'OFFICE', 'name' => 'Perlengkapan Office', 'description' => 'ATK dan perlengkapan kantor'],
        ];
        foreach ($categories as $cat) {
            Category::firstOrCreate(['code' => $cat['code']], $cat);
        }

        $this->command->info('✅ Seeder berhasil!');
        $this->command->info('👤 Superuser  : superuser@csm.co.id  / superuser123');
        $this->command->info('👤 Admin HO   : admin.ho@csm.co.id   / admin123');
        $this->command->info('👤 Accounting : accounting@csm.co.id / accounting123');
    }
}