<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LogActivity — Middleware Audit Log Otomatis
 *
 * Mencatat setiap permintaan mutasi (POST/PUT/PATCH/DELETE) yang berhasil
 * (status 2xx) ke tabel audit_logs secara otomatis.
 *
 * Cara daftar di routes/api.php (sudah ditambahkan di grup protected):
 *   Route::middleware(['auth:sanctum', 'api.limit:standard', 'log.activity'])->group(...)
 *
 * Atau tambahkan ke $middlewareAliases di bootstrap/app.php:
 *   'log.activity' => \App\Http\Middleware\LogActivity::class,
 */
class LogActivity
{
    /**
     * Peta URL → [action, module, template deskripsi].
     * Urutan penting: lebih spesifik dahulu.
     */
    private const ROUTE_MAP = [
        // Auth
        ['POST', '/auth/login',           'login',   'auth',     'Login ke sistem'],
        ['POST', '/auth/logout',          'logout',  'auth',     'Logout dari sistem'],
        ['POST', '/auth/change-password', 'reset',   'auth',     'Ganti password sendiri'],

        // Users
        ['POST',   '/users',                    'create', 'users', 'Buat user baru'],
        ['PUT',    '/users/{id}',               'update', 'users', 'Perbarui data user'],
        ['DELETE', '/users/{id}',               'delete', 'users', 'Hapus user'],
        ['POST',   '/users/{id}/reset-password','reset',  'users', 'Reset password user'],

        // Roles & Permissions
        ['POST', '/roles/update-permissions', 'update', 'roles', 'Perbarui permission role'],

        // Items / Barang
        ['POST',   '/items',         'create', 'items', 'Tambah barang baru'],
        ['PUT',    '/items/{id}',    'update', 'items', 'Perbarui data barang'],
        ['DELETE', '/items/{id}',    'delete', 'items', 'Hapus barang'],
        ['POST',   '/items/{id}/stock-in',  'create', 'stok', 'Stok masuk manual'],
        ['POST',   '/items/{id}/stock-out', 'create', 'stok', 'Stok keluar manual'],

        // Gudang
        ['POST', '/warehouses',       'create', 'gudang', 'Tambah gudang baru'],
        ['PUT',  '/warehouses/{id}',  'update', 'gudang', 'Perbarui data gudang'],

        // Material Request
        ['POST', '/material-requests',                    'create',   'mr', 'Buat Material Request'],
        ['POST', '/material-requests/{id}/submit',        'update',   'mr', 'Submit MR untuk approval'],
        ['POST', '/material-requests/{id}/authorize-chief','approve', 'mr', 'Otorisasi MR (Chief)'],
        ['POST', '/material-requests/{id}/approve-manager','approve', 'mr', 'Approve MR (Manager)'],
        ['POST', '/material-requests/{id}/approve-ho',    'approve',  'mr', 'Approve MR (HO)'],
        ['POST', '/material-requests/{id}/reject',        'reject',   'mr', 'Tolak MR'],
        ['POST', '/material-requests/{id}/dispatch',      'dispatch', 'mr', 'Kirim barang MR (buat DO)'],

        // Permintaan Material
        ['POST',   '/permintaan-material',                      'create',   'pm', 'Buat Permintaan Material'],
        ['DELETE', '/permintaan-material/{id}',                 'delete',   'pm', 'Hapus Permintaan Material'],
        ['POST',   '/permintaan-material/{id}/submit',          'update',   'pm', 'Submit PM untuk approval'],
        ['POST',   '/permintaan-material/{id}/authorize-chief', 'approve',  'pm', 'Otorisasi PM (Chief)'],
        ['POST',   '/permintaan-material/{id}/approve-manager', 'approve',  'pm', 'Approve PM (Manager)'],
        ['POST',   '/permintaan-material/{id}/approve-ho',      'approve',  'pm', 'Approve PM (HO)'],
        ['POST',   '/permintaan-material/{id}/submit-purchasing','update',   'pm', 'Submit PM ke Purchasing'],
        ['POST',   '/permintaan-material/{id}/reject',          'reject',   'pm', 'Tolak PM'],

        // Purchase Order
        ['POST', '/purchase-orders',              'create', 'po', 'Buat Purchase Order'],
        ['POST', '/purchase-orders/{id}/send',    'update', 'po', 'Kirim PO ke vendor'],

        // Surat Jalan / Tanda Terima
        ['POST', '/surat-jalan',              'create',  'sj', 'Buat Surat Jalan'],
        ['POST', '/surat-jalan/{id}/receive', 'receive', 'sj', 'Konfirmasi terima barang (Surat Jalan)'],

        // Transfer Barang
        ['POST',   '/transfer-barang',                      'create',   'transfer', 'Buat Transfer Barang'],
        ['DELETE', '/transfer-barang/{id}',                 'delete',   'transfer', 'Hapus Transfer Barang'],
        ['POST',   '/transfer-barang/{id}/submit',          'update',   'transfer', 'Submit Transfer'],
        ['POST',   '/transfer-barang/{id}/approve-admin',   'approve',  'transfer', 'Approve Transfer (Admin HO)'],
        ['POST',   '/transfer-barang/{id}/approve-atasan',  'approve',  'transfer', 'Approve Transfer (Atasan)'],
        ['POST',   '/transfer-barang/{id}/kirim',           'dispatch', 'transfer', 'Kirim Transfer Barang'],
        ['POST',   '/transfer-barang/{id}/reject',          'reject',   'transfer', 'Tolak Transfer Barang'],
        ['POST',   '/transfer-barang/delivery/{id}/terima', 'receive',  'transfer', 'Terima Transfer Barang'],

        // Bon Pengeluaran
        ['POST', '/bon-pengeluaran',              'create',   'bon', 'Buat Bon Pengeluaran'],
        ['POST', '/bon-pengeluaran/{id}/issue',   'dispatch', 'bon', 'Keluarkan Bon Pengeluaran'],

        // Retur Barang
        ['POST', '/retur-barang',               'create',  'retur', 'Buat Retur Barang'],
        ['POST', '/retur-barang/{id}/confirm',  'approve', 'retur', 'Konfirmasi Retur Barang'],

        // Stok Opname
        ['POST',   '/stok-opname',              'create',  'stok_opname', 'Buat Stok Opname'],
        ['PUT',    '/stok-opname/{id}',         'update',  'stok_opname', 'Perbarui Stok Opname'],
        ['DELETE', '/stok-opname/{id}',         'delete',  'stok_opname', 'Hapus Stok Opname'],
        ['POST',   '/stok-opname/{id}/ajukan',  'update',  'stok_opname', 'Ajukan Stok Opname'],
        ['POST',   '/stok-opname/{id}/setujui', 'approve', 'stok_opname', 'Setujui Stok Opname'],
        ['POST',   '/stok-opname/{id}/tolak',   'reject',  'stok_opname', 'Tolak Stok Opname'],

        // Accounting — Supplier
        ['POST', '/suppliers',       'create', 'akuntansi', 'Tambah Supplier'],
        ['PUT',  '/suppliers/{id}',  'update', 'akuntansi', 'Perbarui Supplier'],

        // Accounting — Invoice
        ['POST', '/supplier-invoices', 'create', 'akuntansi', 'Buat Invoice Supplier'],

        // Accounting — Pembayaran
        ['POST', '/supplier-payments',               'create',  'akuntansi', 'Buat Pembayaran Supplier'],
        ['POST', '/supplier-payments/{id}/approve',  'approve', 'akuntansi', 'Approve Pembayaran Supplier'],
        ['POST', '/supplier-payments/{id}/reject',   'reject',  'akuntansi', 'Tolak Pembayaran Supplier'],

        // Accounting — Kas Kecil
        ['POST', '/petty-cash/accounts',                'create',  'akuntansi', 'Buat Akun Kas Kecil'],
        ['POST', '/petty-cash/transactions',            'create',  'akuntansi', 'Buat Transaksi Kas Kecil'],
        ['POST', '/petty-cash/transactions/{id}/approve','approve','akuntansi', 'Approve Transaksi Kas Kecil'],

        // Accounting — Kas Besar
        ['POST', '/main-cash/accounts',                 'create',  'akuntansi', 'Buat Akun Kas Besar'],
        ['POST', '/main-cash/transactions',             'create',  'akuntansi', 'Buat Transaksi Kas Besar'],
        ['POST', '/main-cash/transactions/{id}/approve','approve', 'akuntansi', 'Approve Transaksi Kas Besar'],

        // Accounting — Jurnal
        ['POST', '/journal-entries',           'create',  'akuntansi', 'Buat Jurnal Manual'],
        ['POST', '/journal-entries/{id}/post', 'approve', 'akuntansi', 'Post Jurnal ke General Ledger'],

        // Payroll
        ['POST', '/payroll/periods',                        'create',  'payroll', 'Buat Periode Penggajian'],
        ['POST', '/payroll/periods/{id}/generate',          'create',  'payroll', 'Generate Data Gaji'],
        ['PUT',  '/payroll/periods/{id}/items/{iid}',       'update',  'payroll', 'Edit Data Gaji Karyawan'],
        ['POST', '/payroll/periods/{id}/approve',           'approve', 'payroll', 'Approve Penggajian'],
        ['POST', '/payroll/periods/{id}/pay',               'approve', 'payroll', 'Tandai Penggajian Dibayar'],
        ['POST', '/payroll/loans',                          'create',  'payroll', 'Catat Pinjaman Karyawan'],
        ['POST', '/payroll/loans/{id}/approve',             'approve', 'payroll', 'Approve Pinjaman Karyawan'],
        ['PUT',  '/payroll/salary-components/{id}',         'update',  'payroll', 'Perbarui Komponen Gaji'],

        // Karyawan & Unit
        ['POST', '/employees',    'create', 'karyawan', 'Tambah Karyawan'],
        ['PUT',  '/employees/{id}','update','karyawan', 'Perbarui Data Karyawan'],
        ['POST', '/units',         'create','unit',     'Tambah Unit Alat Berat'],
        ['PUT',  '/units/{id}',    'update','unit',     'Perbarui Unit Alat Berat'],

        // APD & BBM
        ['POST', '/apd',              'create', 'apd',  'Distribusi APD'],
        ['POST', '/fuel-logs',        'create', 'bbm',  'Catat Log BBM/Solar'],
        ['PUT',  '/fuel-logs/{id}',   'update', 'bbm',  'Perbarui Log BBM/Solar'],
        ['DELETE','/fuel-logs/{id}',  'delete', 'bbm',  'Hapus Log BBM/Solar'],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Hanya catat mutasi yang berhasil
        if (
            !in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])
            || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
        ) {
            return $response;
        }

        try {
            [$action, $module, $description] = $this->resolveRoute($request);
            AuditLog::record($action, $module, $description);
        } catch (\Throwable) {
            // Jangan biarkan error logging menghentikan response
        }

        return $response;
    }

    // ─── Route resolver ───────────────────────────────────────────────────

    private function resolveRoute(Request $request): array
    {
        $method  = $request->method();
        $path    = '/' . ltrim(str_replace('/api', '', parse_url($request->getRequestUri(), PHP_URL_PATH)), '/');

        foreach (self::ROUTE_MAP as [$m, $pattern, $action, $module, $desc]) {
            if ($m !== $method) continue;
            if ($this->matchPattern($pattern, $path)) {
                return [$action, $module, $desc];
            }
        }

        // Fallback generik
        return [
            strtolower($method) === 'post'   ? 'create' :
            (strtolower($method) === 'delete' ? 'delete' : 'update'),
            'system',
            ucfirst(strtolower($method)) . ' ' . $path,
        ];
    }

    private function matchPattern(string $pattern, string $path): bool
    {
        // Ubah {param} jadi regex \d+ atau [^/]+
        $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
        $regex = '#^' . $regex . '$#';
        return (bool) preg_match($regex, $path);
    }
}
