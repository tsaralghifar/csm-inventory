<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ResetDataCommand
 *
 * Menghapus semua data termasuk master barang, namun MEMPERTAHANKAN:
 *   - users
 *   - roles & permissions (Spatie)
 *   - model_has_roles, model_has_permissions, role_has_permissions
 *   - warehouses  (master gudang)
 *
 * Cara pakai:
 *   php artisan csm:reset-data
 *   php artisan csm:reset-data --force   (tanpa konfirmasi interaktif)
 */
class ResetDataCommand extends Command
{
    protected $signature   = 'csm:reset-data {--force : Jalankan tanpa konfirmasi}';
    protected $description = 'Hapus semua data termasuk master barang, pertahankan user, role, dan hak akses';

    // Urutan: tabel anak (child) lebih dulu dari tabel induk (parent)
    private array $tablesToTruncate = [
        // Pivot & relasi
        'purchase_order_permintaan_material',

        // Operasional - detail dulu
        'surat_jalan_items',
        'surat_jalan',
        'bon_pengeluaran_items',
        'bon_pengeluaran',
        'purchase_order_items',
        'purchase_orders',
        'permintaan_material_items',
        'permintaan_material',

        // Retur & transfer
        'retur_barang_items',
        'retur_barang',

        // Stok opname
        'stok_opname_items',
        'stok_opname',

        // Supplier & hutang
        'supplier_payments',
        'supplier_invoices',
        'suppliers',

        // Harga barang
        'item_price_history',

        // Pergerakan stok
        'stock_movements',

        // Akuntansi
        'journal_items',
        'journal_entries',
        'petty_cash_transactions',
        'petty_cash_accounts',
        'main_cash_transactions',
        'main_cash_accounts',

        // Payroll & karyawan
        'payroll_items',
        'payroll_periods',
        'employee_loans',
        'employee_salary_components',
        'employees',

        // Operasional lain
        'apd_distributions',
        'fuel_logs',
        'delivery_order_items',
        'delivery_orders',
        'material_request_items',
        'material_requests',
        'toolbox_opname_items',
        'toolbox_opnames',
        'units',

        // Log & notifikasi
        'audit_logs',
        'notifications',
        'cache',
        'cache_locks',

        // Master barang (terakhir karena direferensi banyak tabel di atas)
        'item_stocks',
        'items',
        'categories',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║          CSM Inventory — RESET DATA                  ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->warn('⚠️  Operasi ini akan MENGHAPUS PERMANEN semua data berikut:');
        $this->line('   • Permintaan Material (PM) & item-nya');
        $this->line('   • Purchase Order (PO) & item-nya');
        $this->line('   • Bon Pengeluaran & Surat Jalan');
        $this->line('   • Retur Barang & Transfer Barang');
        $this->line('   • Stok Opname');
        $this->line('   • Supplier, Invoice & Pembayaran');
        $this->line('   • Pergerakan Stok (stock movements)');
        $this->line('   • Akuntansi (jurnal, kas kecil, kas utama)');
        $this->line('   • Payroll & Karyawan');
        $this->line('   • Audit log, Notifikasi, Cache');
        $this->line('   • Master Barang, Stok & Kategori');
        $this->newLine();

        $this->info('✅  Yang DIPERTAHANKAN:');
        $this->line('   • Users (akun pengguna)');
        $this->line('   • Roles, Permissions & hak akses');
        $this->line('   • Master Gudang (warehouses)');
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('Lanjutkan reset data? Tindakan ini TIDAK BISA DIBATALKAN', false)) {
                $this->info('Reset dibatalkan.');
                return self::SUCCESS;
            }

            $confirm = $this->ask('Ketik "RESET" untuk konfirmasi');
            if ($confirm !== 'RESET') {
                $this->error('Konfirmasi tidak sesuai. Reset dibatalkan.');
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('🔄 Memulai proses reset...');
        $this->newLine();

        try {
            DB::beginTransaction();

            $bar = $this->output->createProgressBar(count($this->tablesToTruncate));
            $bar->start();

            foreach ($this->tablesToTruncate as $table) {
                DB::statement("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
                $bar->advance();
            }

            $bar->finish();
            DB::commit();

            $this->newLine(2);
            $this->info('✅ Reset data selesai!');
            $this->newLine();
            $this->line('  • ' . count($this->tablesToTruncate) . ' tabel berhasil dikosongkan');
            $this->line('  • Data user, role & hak akses tetap aman');
            $this->newLine();

            return self::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->newLine(2);
            $this->error('❌ Terjadi error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}