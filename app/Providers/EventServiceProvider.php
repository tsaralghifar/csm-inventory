<?php

namespace App\Providers;

use App\Events\AccountingUpdated;
use App\Events\BonPengeluaranUpdated;
use App\Events\FuelLogUpdated;
use App\Events\ItemUpdated;
use App\Events\MasterDataUpdated;
use App\Events\MaterialRequestUpdated;
use App\Events\PayrollUpdated;
use App\Events\PermintaanMaterialUpdated;
use App\Events\PurchaseOrderUpdated;
use App\Events\ReturBarangUpdated;
use App\Events\StokOpnameUpdated;
use App\Events\StokUpdated;
use App\Events\SuratJalanUpdated;
use App\Events\TransferBarangUpdated;
use App\Listeners\HandleItemStockChange;
use App\Listeners\NotifyAdminOnBonPengeluaran;
use App\Listeners\NotifyAdminOnFuelLog;
use App\Listeners\NotifyAdminOnMaterialRequest;
use App\Listeners\NotifyAdminOnPurchaseOrder;
use App\Listeners\NotifyAdminOnReturBarang;
use App\Listeners\NotifyAdminOnStokOpname;
use App\Listeners\NotifyAdminOnSuratJalan;
use App\Listeners\NotifyAdminOnTransferBarang;
use App\Listeners\NotifyOnAccounting;
use App\Listeners\NotifyOnPayroll;
use App\Listeners\NotifyOnPermintaanMaterial;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Mapping Event → Listener(s).
     *
     * Semua listener implement ShouldQueue dan berjalan via queue worker.
     * Jalankan: php artisan queue:work --queue=notifications,default
     */
    protected $listen = [

        // ── Item & Stok ───────────────────────────────────────────────────────
        // Cek stok kritis/minus setelah stock_in/stock_out
        ItemUpdated::class => [
            HandleItemStockChange::class,
        ],

        StokUpdated::class => [
            // Placeholder — tambahkan listener jika diperlukan
        ],

        // ── Purchase Order ────────────────────────────────────────────────────
        // Notif admin_ho & superuser saat PO dibuat atau dikirim ke vendor
        PurchaseOrderUpdated::class => [
            NotifyAdminOnPurchaseOrder::class,
        ],

        // ── Surat Jalan / TTB ─────────────────────────────────────────────────
        // Notif admin_ho saat dibuat; notif admin gudang saat dikonfirmasi
        SuratJalanUpdated::class => [
            NotifyAdminOnSuratJalan::class,
        ],

        // ── Bon Pengeluaran ───────────────────────────────────────────────────
        // Notif admin_ho saat bon dibuat atau dikeluarkan
        BonPengeluaranUpdated::class => [
            NotifyAdminOnBonPengeluaran::class,
        ],

        // ── Retur Barang ──────────────────────────────────────────────────────
        // Notif admin_ho saat retur dibuat atau dikonfirmasi
        ReturBarangUpdated::class => [
            NotifyAdminOnReturBarang::class,
        ],

        // ── Stok Opname ───────────────────────────────────────────────────────
        // Notif admin_ho saat diajukan; notif admin gudang saat disetujui/ditolak
        StokOpnameUpdated::class => [
            NotifyAdminOnStokOpname::class,
        ],

        // ── Transfer Barang ───────────────────────────────────────────────────
        // Notif bertingkat: submit → admin → atasan → kirim → terima
        TransferBarangUpdated::class => [
            NotifyAdminOnTransferBarang::class,
        ],

        // ── Material Request (part/office) ────────────────────────────────────
        // Notif bertingkat: submit → chief → manager → ho → dispatch → receive
        MaterialRequestUpdated::class => [
            NotifyAdminOnMaterialRequest::class,
        ],

        // ── Permintaan Material ───────────────────────────────────────────────
        // Notif bertingkat: submit → chief → manager → ho → purchasing
        PermintaanMaterialUpdated::class => [
            NotifyOnPermintaanMaterial::class,
        ],

        // ── Fuel Log (BBM) ────────────────────────────────────────────────────
        // Notif jika stok BBM di bawah 200 liter setelah pencatatan
        FuelLogUpdated::class => [
            NotifyAdminOnFuelLog::class,
        ],

        // ── Master Data ───────────────────────────────────────────────────────
        // Broadcast realtime sudah aktif via ShouldBroadcast di event-nya
        MasterDataUpdated::class => [
            // contoh: LogMasterDataChange::class,
        ],

        // ── Accounting ────────────────────────────────────────────────────────
        // Notif tim accounting saat invoice/payment/jurnal dibuat atau disetujui
        AccountingUpdated::class => [
            NotifyOnAccounting::class,
        ],

        // ── Payroll ───────────────────────────────────────────────────────────
        // Notif tim HR saat penggajian / pinjaman dibuat atau disetujui
        PayrollUpdated::class => [
            NotifyOnPayroll::class,
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}