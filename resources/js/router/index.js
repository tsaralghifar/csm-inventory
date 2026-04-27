import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/store/auth'

const routes = [
  { path: '/login', component: () => import('@/pages/auth/Login.vue'), meta: { guest: true } },

  {
    path: '/',
    component: () => import('@/components/Layout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', component: () => import('@/pages/dashboard/Dashboard.vue'), meta: { title: 'Dashboard', icon: 'bi-speedometer2' } },

      // Master Data
      { path: 'master/barang', component: () => import('@/pages/master/Barang.vue'), meta: { title: 'Master Barang', icon: 'bi-box-seam', permission: 'view-items' } },
      { path: 'master/kategori', component: () => import('@/pages/master/Kategori.vue'), meta: { title: 'Kategori', icon: 'bi-tags', permission: 'view-items' } },
      { path: 'master/gudang', component: () => import('@/pages/master/Gudang.vue'), meta: { title: 'Gudang', icon: 'bi-building', permission: 'view-warehouses' } },
      { path: 'master/unit', component: () => import('@/pages/master/Unit.vue'), meta: { title: 'Unit Alat Berat', icon: 'bi-clipboard-check', permission: 'view-units' } },
      { path: 'master/karyawan', component: () => import('@/pages/master/Karyawan.vue'), meta: { title: 'Karyawan', icon: 'bi-people', permission: 'view-employees' } },

      // Stok
      { path: 'stok/ho', component: () => import('@/pages/stok/StokHO.vue'), meta: { title: 'Stok Gudang HO', icon: 'bi-boxes', permission: 'view-stocks' } },
      { path: 'stok/site', component: () => import('@/pages/stok/StokSite.vue'), meta: { title: 'Stok Site', icon: 'bi-geo-alt', permission: 'view-stocks' } },
      { path: 'stok/import-saldo-awal', component: () => import('@/pages/stok/ImportSaldoAwal.vue'), meta: { title: 'Import Saldo Awal', icon: 'bi-file-earmark-arrow-up', permission: 'manage-items' } },

      { path: 'permintaan-material', component: () => import('@/pages/permintaan/PermintaanMaterial.vue'), meta: { title: 'Permintaan Material', icon: 'bi-clipboard-heart', permission: 'view-pm' } },
      { path: 'permintaan-material/:id', component: () => import('@/pages/permintaan/DetailPermintaan.vue'), meta: { title: 'Detail Permintaan', permission: 'view-pm' } },
      { path: 'purchase-order', component: () => import('@/pages/purchasing/PurchaseOrder.vue'), meta: { title: 'Purchase Order', icon: 'bi-file-earmark-text', permission: 'view-po' } },
      { path: 'bon-pengeluaran', component: () => import('@/pages/purchasing/BonPengeluaran.vue'), meta: { title: 'Bon Pengeluaran', icon: 'bi-box-arrow-right', permission: 'view-bon' } },
      { path: 'tanda-terima-barang', component: () => import('@/pages/purchasing/SuratJalan.vue'), meta: { title: 'Tanda Terima Barang', icon: 'bi-clipboard-check', permission: 'view-sj' } },
      { path: 'retur-barang', component: () => import('@/pages/purchasing/ReturBarang.vue'), meta: { title: 'Retur Barang', icon: 'bi-arrow-return-left', permission: 'view-po' } },

      // Transfer Barang antar Gudang
      { path: 'transfer-barang', component: () => import('@/pages/transfer/TransferBarang.vue'), meta: { title: 'Transfer Barang', icon: 'bi-arrow-left-right', permission: 'view-mr' } },
      { path: 'transfer-barang/:id', component: () => import('@/pages/transfer/DetailTransfer.vue'), meta: { title: 'Detail Transfer', permission: 'view-mr' } },

      // Mutasi / Pergerakan
      { path: 'stok/opname', component: () => import('@/pages/stok/StokOpname.vue'), meta: { title: 'Stok Opname', icon: 'bi-clipboard-check', permission: 'view-stocks' } },
      { path: 'mutasi', component: () => import('@/pages/mutasi/Mutasi.vue'), meta: { title: 'Histori Mutasi', icon: 'bi-arrow-left-right', permission: 'view-stocks' } },

      // BBM / Solar
      { path: 'bbm', component: () => import('@/pages/bbm/BBM.vue'), meta: { title: 'Pengeluaran Solar', icon: 'bi-fuel-pump', permission: 'view-fuel' } },

      // APD
      { path: 'apd', component: () => import('@/pages/apd/APD.vue'), meta: { title: 'APD Karyawan', icon: 'bi-shield-check', permission: 'view-apd' } },

      // Laporan
      { path: 'laporan/stok', component: () => import('@/pages/laporan/LaporanStok.vue'), meta: { title: 'Laporan Stok', icon: 'bi-file-earmark-text', permission: 'view-reports' } },
      { path: 'laporan/pengeluaran', component: () => import('@/pages/laporan/LaporanPengeluaran.vue'), meta: { title: 'Laporan Pengeluaran', icon: 'bi-file-earmark-bar-graph', permission: 'view-reports' } },
      { path: 'laporan/mutasi', component: () => import('@/pages/laporan/LaporanMutasi.vue'), meta: { title: 'Laporan Mutasi', icon: 'bi-file-earmark-arrow-right', permission: 'view-reports' } },
      { path: 'laporan/accounting', component: () => import('@/pages/laporan/LaporanAccounting.vue'), meta: { title: 'Laporan Accounting', icon: 'bi-file-earmark-bar-graph', permission: 'view-accounting' } },
      { path: 'laporan/cash-flow', component: () => import('@/pages/laporan/LaporanCashFlow.vue'), meta: { title: 'Laporan Cash Flow', icon: 'bi-arrow-left-right', permission: 'view-accounting' } },
      { path: 'laporan/hutang-supplier', component: () => import('@/pages/laporan/LaporanHutangSupplier.vue'), meta: { title: 'Laporan Hutang Supplier', icon: 'bi-receipt-cutoff', permission: 'view-accounting' } },
      { path: 'laporan/beban', component: () => import('@/pages/laporan/LaporanBeban.vue'), meta: { title: 'Laporan Beban', icon: 'bi-cash-stack', permission: 'view-accounting' } },
      { path: 'laporan/payroll', component: () => import('@/pages/laporan/LaporanPayroll.vue'), meta: { title: 'Laporan Payroll', icon: 'bi-file-earmark-person', permission: 'view-payroll' } },

      // Admin
      { path: 'admin/users', component: () => import('@/pages/admin/Users.vue'), meta: { title: 'Manajemen User', icon: 'bi-person-gear', permission: 'manage-users' } },
      { path: 'admin/roles', component: () => import('@/pages/admin/Roles.vue'), meta: { title: 'Role & Akses', icon: 'bi-key', permission: 'manage-roles' } },

      // Accounting
      { path: 'accounting/supplier',   component: () => import('@/pages/accounting/Supplier.vue'),           meta: { title: 'Supplier',            icon: 'bi-building-check', permission: 'view-accounting' } },
      { path: 'accounting/invoice',    component: () => import('@/pages/accounting/InvoiceSupplier.vue'),    meta: { title: 'Invoice Supplier',    icon: 'bi-receipt',        permission: 'view-accounting' } },
      { path: 'accounting/jurnal',     component: () => import('@/pages/accounting/Jurnal.vue'),             meta: { title: 'Jurnal & Ledger',     icon: 'bi-journal-text',   permission: 'view-accounting' } },
      { path: 'accounting/kas-kecil',  component: () => import('@/pages/accounting/KasKecil.vue'),           meta: { title: 'Kas Kecil',           icon: 'bi-wallet2',        permission: 'view-accounting' } },
      { path: 'accounting/kas-besar',  component: () => import('@/pages/accounting/KasBesar.vue'),           meta: { title: 'Kas Besar',           icon: 'bi-bank',           permission: 'view-accounting' } },
      { path: 'accounting/pembayaran', component: () => import('@/pages/accounting/PembayaranSupplier.vue'), meta: { title: 'Pembayaran Supplier', icon: 'bi-cash-coin',      permission: 'view-accounting' } },

      // Payroll
      { path: 'payroll/penggajian',    component: () => import('@/pages/payroll/Payroll.vue'),            meta: { title: 'Penggajian',        icon: 'bi-cash-stack',  permission: 'view-payroll'   } },
      { path: 'payroll/pinjaman',      component: () => import('@/pages/payroll/PinjamanKaryawan.vue'),   meta: { title: 'Pinjaman Karyawan', icon: 'bi-credit-card', permission: 'view-payroll'   } },
      { path: 'payroll/komponen-gaji', component: () => import('@/pages/payroll/KomponenGaji.vue'),       meta: { title: 'Komponen Gaji',     icon: 'bi-list-check',  permission: 'manage-payroll' } },

      // Profile
      { path: 'profile', component: () => import('@/pages/auth/Profile.vue'), meta: { title: 'Profil Saya' } },
    ],
  },

  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  const token = localStorage.getItem('csm_token')

  if (to.meta.requiresAuth) {
    if (!token) return next('/login')
    if (!authStore.user) {
      try { await authStore.fetchUser() } catch { return next('/login') }
    }
    if (to.meta.permission && !authStore.hasPermission(to.meta.permission) && !authStore.user?.is_superuser) {
      return next('/dashboard')
    }
  }

  if (to.meta.guest && token && authStore.user) {
    return next('/dashboard')
  }

  next()
})

// Bersihkan backdrop Bootstrap modal yang tersisa saat navigasi antar halaman
router.afterEach(() => {
  setTimeout(() => {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove())
    document.body.classList.remove('modal-open')
    document.body.style.removeProperty('overflow')
    document.body.style.removeProperty('padding-right')
  }, 350)
})

export default router