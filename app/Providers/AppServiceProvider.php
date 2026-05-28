<?php

namespace App\Providers;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StokOpname;
use App\Models\User;
use App\Observers\ItemObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\StokOpnameObserver;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ─── Cegah N+1: throw exception saat lazy loading terjadi di local ────
        // Ini akan mendeteksi relasi yang belum di-eager load secara otomatis.
        // Hapus atau komentari baris ini sebelum deploy ke production.
        if (app()->environment('local')) {
            Model::preventLazyLoading();
        }

        // Super User bypass semua permission
        Gate::before(function ($user, $ability) {
            if ($user->isSuperuser()) {
                return true;
            }
        });

        // ─── Daftarkan semua Observer Audit Log ───────────────────────────────
        // Setiap observer mencatat create/update/delete otomatis
        // dengan before (old_values) dan after (new_values) yang jelas.
        Item::observe(ItemObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        User::observe(UserObserver::class);
        StokOpname::observe(StokOpnameObserver::class);

        // Tambahkan observer lain di sini jika diperlukan:
        // SuratJalan::observe(SuratJalanObserver::class);
        // TransferBarang::observe(TransferBarangObserver::class);
        // BonPengeluaran::observe(BonPengeluaranObserver::class);
        // ReturBarang::observe(ReturBarangObserver::class);
        // FuelLog::observe(FuelLogObserver::class);
        // Warehouse::observe(WarehouseObserver::class);
    }
}