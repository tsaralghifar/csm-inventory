<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LogActivity — Middleware Audit Log untuk aksi NON-CRUD
 *
 * Setelah observer dipasang, middleware ini HANYA mencatat aksi yang
 * tidak ditangkap observer model:
 *   - login / logout / ganti password
 *   - approve / reject / submit workflow
 *   - kirim / terima (state transition)
 *   - export
 *
 * Aksi CRUD (create/update/delete) sudah dicatat oleh Observer masing-masing
 * model sehingga tidak perlu dicatat ulang di sini.
 *
 * Cara daftar: middleware sudah terdaftar di bootstrap/app.php atau Kernel.php
 * dengan alias 'log.activity'. Grup route protected sudah memakai middleware ini.
 */
class LogActivity
{
    /**
     * Peta URL → [action, module, deskripsi].
     *
     * CATATAN: Hanya aksi non-CRUD (workflow/state-transition/auth).
     * Aksi create/update/delete sudah ditangani Observer.
     */
    private const WORKFLOW_ROUTES = [
        // ─── Auth ────────────────────────────────────────────────────────
        ['POST', '/auth/login',           'login',  'auth', 'Login ke sistem'],
        ['POST', '/auth/logout',          'logout', 'auth', 'Logout dari sistem'],
        ['POST', '/auth/change-password', 'reset',  'auth', 'Ganti password sendiri'],

        // ─── Users ───────────────────────────────────────────────────────
        ['POST', '/users/{id}/reset-password', 'reset', 'users', 'Reset password user'],

        // ─── Roles & Permissions ─────────────────────────────────────────
        ['POST', '/roles/update-permissions', 'update', 'roles', 'Perbarui permission role'],

        // ─── Items (stok masuk/keluar manual — bukan CRUD model) ─────────
        ['POST', '/items/{id}/stock-in',  'create', 'stok', 'Stok masuk manual'],
        ['POST', '/items/{id}/stock-out', 'create', 'stok', 'Stok keluar manual'],

        // ─── Material Request — workflow ─────────────────────────────────
        ['POST', '/material-requests/{id}/submit',         'update',   'mr', 'Submit MR untuk approval'],
        ['POST', '/material-requests/{id}/authorize-chief','approve',  'mr', 'Otorisasi MR (Chief)'],
        ['POST', '/material-requests/{id}/approve-manager','approve',  'mr', 'Approve MR (Manager)'],
        ['POST', '/material-requests/{id}/approve-ho',     'approve',  'mr', 'Approve MR (HO)'],
        ['POST', '/material-requests/{id}/reject',         'reject',   'mr', 'Tolak MR'],
        ['POST', '/material-requests/{id}/dispatch',       'dispatch', 'mr', 'Kirim barang MR'],

        // ─── Permintaan Material — workflow ──────────────────────────────
        ['POST', '/permintaan-material/{id}/submit',           'update',  'pm', 'Submit PM untuk approval'],
        ['POST', '/permintaan-material/{id}/authorize-chief',  'approve', 'pm', 'Otorisasi PM (Chief)'],
        ['POST', '/permintaan-material/{id}/approve-manager',  'approve', 'pm', 'Approve PM (Manager)'],
        ['POST', '/permintaan-material/{id}/approve-ho',       'approve', 'pm', 'Approve PM (HO)'],
        ['POST', '/permintaan-material/{id}/submit-purchasing','update',  'pm', 'Submit PM ke Purchasing'],
        ['POST', '/permintaan-material/{id}/reject',           'reject',  'pm', 'Tolak PM'],

        // ─── Purchase Order — workflow ────────────────────────────────────
        ['POST', '/purchase-orders/{id}/send', 'update', 'po', 'Kirim PO ke vendor'],

        // ─── Surat Jalan — workflow ───────────────────────────────────────
        ['POST', '/surat-jalan/{id}/receive', 'receive', 'sj', 'Konfirmasi terima barang'],

        // ─── Transfer Barang — workflow ───────────────────────────────────
        ['POST', '/transfer-barang/{id}/submit',         'update',   'transfer', 'Submit Transfer'],
        ['POST', '/transfer-barang/{id}/approve-admin',  'approve',  'transfer', 'Approve Transfer (Admin HO)'],
        ['POST', '/transfer-barang/{id}/approve-atasan', 'approve',  'transfer', 'Approve Transfer (Atasan)'],
        ['POST', '/transfer-barang/{id}/kirim',          'dispatch', 'transfer', 'Kirim Transfer Barang'],
        ['POST', '/transfer-barang/{id}/reject',         'reject',   'transfer', 'Tolak Transfer Barang'],
        ['POST', '/transfer-barang/delivery/{id}/terima','receive',  'transfer', 'Terima Transfer Barang'],

        // ─── Bon Pengeluaran — workflow ───────────────────────────────────
        ['POST', '/bon-pengeluaran/{id}/issue', 'dispatch', 'bon', 'Keluarkan Bon Pengeluaran'],

        // ─── Retur Barang — workflow ──────────────────────────────────────
        ['POST', '/retur-barang/{id}/confirm', 'approve', 'retur', 'Konfirmasi Retur Barang'],

        // ─── Stok Opname — workflow ───────────────────────────────────────
        ['POST', '/stok-opname/{id}/ajukan',  'update',  'stok_opname', 'Ajukan Stok Opname'],
        ['POST', '/stok-opname/{id}/setujui', 'approve', 'stok_opname', 'Setujui Stok Opname'],
        ['POST', '/stok-opname/{id}/tolak',   'reject',  'stok_opname', 'Tolak Stok Opname'],

        // ─── Accounting — workflow ────────────────────────────────────────
        ['POST', '/supplier-payments/{id}/approve', 'approve', 'akuntansi', 'Approve Pembayaran Supplier'],
        ['POST', '/supplier-payments/{id}/reject',  'reject',  'akuntansi', 'Tolak Pembayaran Supplier'],
        ['POST', '/petty-cash/transactions/{id}/approve', 'approve', 'akuntansi', 'Approve Transaksi Kas Kecil'],
        ['POST', '/main-cash/transactions/{id}/approve',  'approve', 'akuntansi', 'Approve Transaksi Kas Besar'],
        ['POST', '/journal-entries/{id}/post',            'approve', 'akuntansi', 'Post Jurnal ke General Ledger'],

        // ─── Payroll — workflow ───────────────────────────────────────────
        ['POST', '/payroll/periods/{id}/generate', 'create',  'payroll', 'Generate Data Gaji'],
        ['POST', '/payroll/periods/{id}/approve',  'approve', 'payroll', 'Approve Penggajian'],
        ['POST', '/payroll/periods/{id}/pay',      'approve', 'payroll', 'Tandai Penggajian Dibayar'],
        ['POST', '/payroll/loans/{id}/approve',    'approve', 'payroll', 'Approve Pinjaman Karyawan'],

        // ─── Export ───────────────────────────────────────────────────────
        ['GET', '/reports/export',     'export', 'laporan', 'Export laporan'],
        ['GET', '/audit-logs/export',  'export', 'audit',   'Export audit log'],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Hanya catat mutasi yang berhasil
        if (
            !in_array($request->method(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])
            || $response->getStatusCode() < 200
            || $response->getStatusCode() >= 300
        ) {
            return $response;
        }

        // Jangan catat jika AuditLog sedang di-suppress
        if (AuditLog::isSuppressed()) {
            return $response;
        }

        try {
            $resolved = $this->resolveWorkflowRoute($request);

            // Hanya catat jika cocok dengan WORKFLOW_ROUTES
            // Aksi CRUD (create/update/delete plain) dibiarkan ke Observer
            if ($resolved !== null) {
                [$action, $module, $description] = $resolved;
                AuditLog::record($action, $module, $description);
            }
        } catch (\Throwable) {
            // Jangan biarkan error logging menghentikan response
        }

        return $response;
    }

    // ─── Route resolver ───────────────────────────────────────────────────

    /**
     * Kembalikan [action, module, description] jika cocok dengan WORKFLOW_ROUTES.
     * Kembalikan null jika tidak cocok (biarkan Observer yang handle).
     */
    private function resolveWorkflowRoute(Request $request): ?array
    {
        $method = $request->method();
        $path   = '/' . ltrim(str_replace('/api', '', parse_url($request->getRequestUri(), PHP_URL_PATH)), '/');

        foreach (self::WORKFLOW_ROUTES as [$m, $pattern, $action, $module, $desc]) {
            if ($m !== $method) continue;
            if ($this->matchPattern($pattern, $path)) {
                return [$action, $module, $desc];
            }
        }

        return null; // tidak cocok → Observer yang tangani
    }

    private function matchPattern(string $pattern, string $path): bool
    {
        $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
        $regex = '#^' . $regex . '$#';
        return (bool) preg_match($regex, $path);
    }
}