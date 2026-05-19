<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearTransactionData extends Command
{
    protected $signature   = 'csm:clear-data {--force : Jalankan tanpa konfirmasi}';
    protected $description = 'Hapus semua data transaksi, pertahankan: users, roles, permissions, categories, warehouses, units';

    // Tabel yang TIDAK akan dihapus
    protected array $keep = [
        'users',
        'categories',
        'warehouses',
        'units',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        // tabel sistem Laravel
        'migrations',
        'personal_access_tokens',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    // Urutan hapus — dari tabel anak ke tabel induk agar tidak error FK
    protected array $truncateOrder = [
        'audit_logs',
        'notifications',
        'journal_items',
        'journal_entries',
        'supplier_payments',
        'supplier_invoices',
        'purchase_order_permintaan_material',
        'purchase_order_items',
        'purchase_orders',
        'bon_pengeluaran_items',
        'bon_pengeluaran',
        'permintaan_material_items',
        'permintaan_material',
        'material_request_items',
        'material_requests',
        'retur_barang_items',
        'retur_barang',
        'surat_jalan_items',
        'surat_jalan',
        'delivery_order_items',
        'delivery_orders',
        'stok_opname_items',
        'stok_opname',
        'toolbox_opname_items',
        'toolbox_opnames',
        'stock_movements',
        'item_stocks',
        'item_price_history',
        'items',
        'apd_distributions',
        'fuel_logs',
        'payroll_items',
        'payroll_periods',
        'employee_loans',
        'employee_salary_components',
        'employees',
        'petty_cash_transactions',
        'petty_cash_accounts',
        'main_cash_transactions',
        'main_cash_accounts',
        'suppliers',
    ];

    public function handle(): int
    {
        $this->warn('================================================');
        $this->warn('  ⚠️  PERINGATAN: Operasi ini tidak bisa dibalik!');
        $this->warn('================================================');
        $this->info('Data yang akan DIHAPUS: semua transaksi, items, employees, suppliers, dll.');
        $this->info('Data yang DIPERTAHANKAN: users, categories, warehouses, units, roles, permissions.');
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('Apakah Anda yakin ingin menghapus semua data transaksi?')) {
                $this->info('Dibatalkan.');
                return self::SUCCESS;
            }
            // Konfirmasi kedua
            $confirm = $this->ask('Ketik "HAPUS SEMUA" untuk konfirmasi');
            if ($confirm !== 'HAPUS SEMUA') {
                $this->error('Konfirmasi tidak sesuai. Dibatalkan.');
                return self::FAILURE;
            }
        }

        $this->info('Memulai penghapusan data...');
        $this->newLine();

        DB::statement('SET session_replication_role = replica;'); // nonaktifkan FK sementara (PostgreSQL)

        $bar = $this->output->createProgressBar(count($this->truncateOrder));
        $bar->start();

        $errors = [];
        foreach ($this->truncateOrder as $table) {
            try {
                DB::table($table)->truncate();
            } catch (\Throwable $e) {
                $errors[] = "  - {$table}: " . $e->getMessage();
            }
            $bar->advance();
        }

        DB::statement('SET session_replication_role = DEFAULT;'); // aktifkan kembali FK

        $bar->finish();
        $this->newLine(2);

        if ($errors) {
            $this->warn('Beberapa tabel gagal dihapus:');
            foreach ($errors as $err) {
                $this->error($err);
            }
        }

        $this->info('✅ Selesai! Semua data transaksi telah dihapus.');
        $this->table(
            ['Dipertahankan'],
            collect($this->keep)->map(fn($t) => [$t])->toArray()
        );

        return self::SUCCESS;
    }
}
