<template>
  <div class="csm-wrapper">
    <!-- Sidebar -->
    <nav class="csm-sidebar" :class="{ open: sidebarOpen }">
      <div class="csm-sidebar-brand">
        <div class="d-flex align-items-center gap-2">
          <span style="font-size:1.6rem;">🏗️</span>
          <div>
            <h5>CSM Inventory</h5>
            <small>PT. Cipta Sarana Makmur</small>
          </div>
        </div>
      </div>

      <nav class="csm-sidebar-nav">
        <!-- Dashboard -->
        <router-link to="/dashboard" class="csm-nav-link" :class="{ active: $route.path === '/dashboard' }">
          <i class="bi bi-speedometer2"></i> Dashboard
        </router-link>

        <!-- Master Data -->
        <div class="csm-nav-section">Master Data</div>
        <router-link v-if="can('view-items')" to="/master/barang" class="csm-nav-link" :class="{ active: $route.path.startsWith('/master/barang') }">
          <i class="bi bi-box-seam"></i> Barang / Sparepart
        </router-link>
        <router-link v-if="can('view-items')" to="/master/kategori" class="csm-nav-link" :class="{ active: $route.path.startsWith('/master/kategori') }">
          <i class="bi bi-tags"></i> Kategori
        </router-link>
        <router-link v-if="can('view-warehouses')" to="/master/gudang" class="csm-nav-link" :class="{ active: $route.path.startsWith('/master/gudang') }">
          <i class="bi bi-building"></i> Gudang
        </router-link>
        <router-link v-if="can('view-units')" to="/master/unit" class="csm-nav-link" :class="{ active: $route.path.startsWith('/master/unit') }">
          <i class="bi bi-clipboard-check"></i> Unit Alat Berat
        </router-link>
        <router-link v-if="can('view-employees')" to="/master/karyawan" class="csm-nav-link" :class="{ active: $route.path.startsWith('/master/karyawan') }">
          <i class="bi bi-people"></i> Karyawan
        </router-link>

        <!-- Stok -->
        <div class="csm-nav-section">Inventori</div>
        <router-link v-if="can('view-stocks')" to="/stok/ho" class="csm-nav-link" :class="{ active: $route.path.startsWith('/stok/ho') }">
          <i class="bi bi-boxes"></i> Stok Gudang HO
        </router-link>
        <router-link v-if="can('view-stocks')" to="/stok/site" class="csm-nav-link" :class="{ active: $route.path.startsWith('/stok/site') }">
          <i class="bi bi-geo-alt"></i> Stok Site
        </router-link>
        <router-link v-if="can('manage-items')" to="/stok/import-saldo-awal" class="csm-nav-link" :class="{ active: $route.path.startsWith('/stok/import-saldo-awal') }">
          <i class="bi bi-file-earmark-arrow-up"></i> Import Saldo Awal
        </router-link>
        <router-link v-if="can('view-stocks')" to="/stok/opname" class="csm-nav-link" :class="{ active: $route.path.startsWith('/stok/opname') }">
          <i class="bi bi-clipboard-check"></i> Stok Opname
        </router-link>
        <router-link v-if="can('view-stocks')" to="/mutasi" class="csm-nav-link" :class="{ active: $route.path.startsWith('/mutasi') }">
          <i class="bi bi-arrow-left-right"></i> Histori Mutasi
        </router-link>

        <!-- Operasional -->
        <div class="csm-nav-section">Operasional</div>
        <router-link v-if="can('view-pm')" to="/permintaan-material" class="csm-nav-link" :class="{ active: $route.path.startsWith('/permintaan-material') }">
          <i class="bi bi-clipboard-heart"></i> Permintaan Material
          <span v-if="pendingPM > 0" class="badge bg-danger ms-auto rounded-pill">{{ pendingPM }}</span>
        </router-link>
        <router-link v-if="can('view-po')" to="/purchase-order" class="csm-nav-link" :class="{ active: $route.path.startsWith('/purchase-order') }">
          <i class="bi bi-file-earmark-text"></i> Purchase Order
        </router-link>
        <router-link v-if="can('view-bon')" to="/bon-pengeluaran" class="csm-nav-link" :class="{ active: $route.path.startsWith('/bon-pengeluaran') }">
          <i class="bi bi-box-arrow-right"></i> Bon Pengeluaran
        </router-link>
        <router-link v-if="can('view-sj')" to="/tanda-terima-barang" class="csm-nav-link" :class="{ active: $route.path.startsWith('/tanda-terima-barang') }">
          <i class="bi bi-clipboard-check"></i> Tanda Terima Barang
        </router-link>
        <router-link v-if="can('view-retur')" to="/retur-barang" class="csm-nav-link" :class="{ active: $route.path.startsWith('/retur-barang') }">
          <i class="bi bi-arrow-return-left"></i> Retur Barang
        </router-link>
        <router-link v-if="can('view-transfer')" to="/transfer-barang" class="csm-nav-link" :class="{ active: $route.path.startsWith('/transfer-barang') }">
          <i class="bi bi-arrow-left-right"></i> Transfer Barang
        </router-link>
        <router-link v-if="can('view-fuel')" to="/bbm" class="csm-nav-link" :class="{ active: $route.path.startsWith('/bbm') }">
          <i class="bi bi-fuel-pump"></i> Pengeluaran Solar
        </router-link>
        <router-link v-if="can('view-apd')" to="/apd" class="csm-nav-link" :class="{ active: $route.path.startsWith('/apd') }">
          <i class="bi bi-shield-check"></i> APD Karyawan
        </router-link>

        <!-- Laporan -->
        <div class="csm-nav-section">Laporan</div>
        <router-link v-if="can('view-reports')" to="/laporan/stok" class="csm-nav-link" :class="{ active: $route.path === '/laporan/stok' }">
          <i class="bi bi-file-earmark-text"></i> Laporan Stok
        </router-link>
        <router-link v-if="can('view-reports')" to="/laporan/pengeluaran" class="csm-nav-link" :class="{ active: $route.path === '/laporan/pengeluaran' }">
          <i class="bi bi-file-earmark-bar-graph"></i> Laporan Pengeluaran
        </router-link>
        <router-link v-if="can('view-reports')" to="/laporan/mutasi" class="csm-nav-link" :class="{ active: $route.path === '/laporan/mutasi' }">
          <i class="bi bi-arrow-left-right"></i> Laporan Mutasi
        </router-link>
        <router-link v-if="can('view-accounting')" to="/laporan/accounting" class="csm-nav-link" :class="{ active: $route.path === '/laporan/accounting' }">
          <i class="bi bi-file-earmark-bar-graph"></i> Laporan Accounting
        </router-link>
        <router-link v-if="can('view-accounting')" to="/laporan/cash-flow" class="csm-nav-link" :class="{ active: $route.path === '/laporan/cash-flow' }">
          <i class="bi bi-arrow-left-right"></i> Laporan Cash Flow
        </router-link>
        <router-link v-if="can('view-accounting')" to="/laporan/hutang-supplier" class="csm-nav-link" :class="{ active: $route.path === '/laporan/hutang-supplier' }">
          <i class="bi bi-receipt-cutoff"></i> Hutang Supplier
        </router-link>
        <router-link v-if="can('view-accounting')" to="/laporan/beban" class="csm-nav-link" :class="{ active: $route.path === '/laporan/beban' }">
          <i class="bi bi-cash-stack"></i> Laporan Beban
        </router-link>
        <router-link v-if="can('view-payroll')" to="/laporan/payroll" class="csm-nav-link" :class="{ active: $route.path === '/laporan/payroll' }">
          <i class="bi bi-file-earmark-person"></i> Laporan Payroll
        </router-link>

        <!-- Accounting -->
        <template v-if="can('view-accounting')">
          <div class="csm-nav-section">Accounting</div>
          <router-link to="/accounting/supplier" class="csm-nav-link" :class="{ active: $route.path.startsWith('/accounting/supplier') }">
            <i class="bi bi-building-check"></i> Supplier
          </router-link>
          <router-link to="/accounting/invoice" class="csm-nav-link" :class="{ active: $route.path.startsWith('/accounting/invoice') }">
            <i class="bi bi-receipt"></i> Invoice Supplier
          </router-link>
          <router-link to="/accounting/pembayaran" class="csm-nav-link" :class="{ active: $route.path.startsWith('/accounting/pembayaran') }">
            <i class="bi bi-cash-coin"></i> Pembayaran Supplier
          </router-link>
          <router-link to="/accounting/jurnal" class="csm-nav-link" :class="{ active: $route.path.startsWith('/accounting/jurnal') }">
            <i class="bi bi-journal-text"></i> Jurnal & Ledger
          </router-link>
          <router-link to="/accounting/kas-kecil" class="csm-nav-link" :class="{ active: $route.path.startsWith('/accounting/kas-kecil') }">
            <i class="bi bi-wallet2"></i> Kas Kecil
          </router-link>
          <router-link to="/accounting/kas-besar" class="csm-nav-link" :class="{ active: $route.path.startsWith('/accounting/kas-besar') }">
            <i class="bi bi-bank"></i> Kas Besar
          </router-link>
        </template>

        <!-- Payroll -->
        <template v-if="can('view-payroll')">
          <div class="csm-nav-section">Payroll</div>
          <router-link to="/payroll/penggajian" class="csm-nav-link" :class="{ active: $route.path.startsWith('/payroll/penggajian') }">
            <i class="bi bi-cash-stack"></i> Penggajian
          </router-link>
          <router-link to="/payroll/pinjaman" class="csm-nav-link" :class="{ active: $route.path.startsWith('/payroll/pinjaman') }">
            <i class="bi bi-credit-card"></i> Pinjaman Karyawan
          </router-link>
          <router-link v-if="can('manage-payroll')" to="/payroll/komponen-gaji" class="csm-nav-link" :class="{ active: $route.path.startsWith('/payroll/komponen-gaji') }">
            <i class="bi bi-list-check"></i> Komponen Gaji
          </router-link>
        </template>

        <!-- Admin -->
        <template v-if="can('manage-users')">
          <div class="csm-nav-section">Administrasi</div>
          <router-link to="/admin/users" class="csm-nav-link" :class="{ active: $route.path === '/admin/users' }">
            <i class="bi bi-person-gear"></i> Manajemen User
          </router-link>
          <router-link v-if="can('manage-roles')" to="/admin/roles" class="csm-nav-link" :class="{ active: $route.path === '/admin/roles' }">
            <i class="bi bi-key"></i> Role & Hak Akses
          </router-link>
        </template>
      </nav>

      <!-- Sidebar footer -->
      <div class="p-2 border-top" style="border-color: rgba(255,255,255,0.1)!important;">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:34px;height:34px;flex-shrink:0;">
            <i class="bi bi-person-fill text-white"></i>
          </div>
          <div class="flex-1 overflow-hidden">
            <div class="text-white fw-semibold" style="font-size:0.8rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ auth.user?.name }}</div>
            <div style="font-size:0.68rem;color:rgba(255,255,255,0.5);">{{ auth.user?.roles?.[0] }}</div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main -->
    <main class="csm-main">
      <!-- Topbar -->
      <header class="csm-topbar">
        <div class="d-flex align-items-center gap-3">
          <button class="btn btn-sm btn-outline-secondary d-md-none" @click="sidebarOpen = !sidebarOpen">
            <i class="bi bi-list"></i>
          </button>
          <span class="csm-topbar-title">{{ $route.meta.title || 'CSM Inventory' }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-light text-dark border" style="font-size:0.72rem;">
            <i class="bi bi-building me-1"></i>{{ auth.userWarehouse?.name || 'Semua Gudang' }}
          </span>

          <!-- ── Notification Bell ── -->
          <NotificationBell />

          <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle me-1"></i>{{ auth.user?.name }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><router-link class="dropdown-item" to="/profile"><i class="bi bi-person me-2"></i>Profil Saya</router-link></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="#" @click.prevent="doLogout"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
            </ul>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <div class="csm-content">
        <!-- ── Low Stock Alert Banner ── -->
        <LowStockAlert />

        <router-view />
      </div>
    </main>

    <!-- Overlay for mobile -->
    <div v-if="sidebarOpen" class="position-fixed inset-0 bg-dark bg-opacity-50 d-md-none" style="z-index:999;" @click="sidebarOpen=false"></div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import { useToast } from 'vue-toastification'
import NotificationBell from '@/components/NotificationBell.vue'
import LowStockAlert from '@/components/LowStockAlert.vue'

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()
const sidebarOpen = ref(false)
const pendingMR = ref(0)
const pendingPM = ref(0)

function can(permission) {
  return auth.hasPermission(permission)
}

async function doLogout() {
  await auth.logout()
  router.push('/login')
  toast.success('Berhasil keluar')
}
</script>