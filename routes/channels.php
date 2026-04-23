<?php

use Illuminate\Support\Facades\Broadcast;

// Semua channel di bawah hanya bisa diakses user yang sudah login

Broadcast::channel('transfer-barang',      fn($u) => auth()->check());
Broadcast::channel('material-request',     fn($u) => auth()->check());
Broadcast::channel('permintaan-material',  fn($u) => auth()->check());
Broadcast::channel('purchase-order',       fn($u) => auth()->check());
Broadcast::channel('bon-pengeluaran',      fn($u) => auth()->check());
Broadcast::channel('surat-jalan',          fn($u) => auth()->check());
Broadcast::channel('master-data',          fn($u) => auth()->check());
Broadcast::channel('master-gudang',        fn($u) => auth()->check());
Broadcast::channel('master-kategori',      fn($u) => auth()->check());
Broadcast::channel('master-unit',          fn($u) => auth()->check());
Broadcast::channel('master-karyawan',      fn($u) => auth()->check());
Broadcast::channel('fuel-log',             fn($u) => auth()->check());
Broadcast::channel('apd',                  fn($u) => auth()->check());

// Accounting & Payroll — hanya user dengan permission view-accounting / view-payroll
Broadcast::channel('accounting', function ($user) {
    return $user->hasPermissionTo('view-accounting') || $user->isSuperuser();
});
Broadcast::channel('payroll', function ($user) {
    return $user->hasPermissionTo('view-payroll') || $user->isSuperuser();
});

// Stok — semua gudang (untuk HO/Superuser)
Broadcast::channel('stok',                 fn($u) => auth()->check());

// Stok — per gudang spesifik
Broadcast::channel('stok.{warehouseId}',   function ($user, $warehouseId) {
    return $user->isSuperuser()
        || $user->isAdminHO()
        || (int) $user->warehouse_id === (int) $warehouseId;
});