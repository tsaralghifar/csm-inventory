<template>
  <div>
    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-danger">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-value">{{ data?.kpi?.minus_items || 0 }}</div>
              <div class="kpi-label">Barang Stok Minus</div>
            </div>
            <i class="bi bi-exclamation-triangle kpi-icon"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-warning">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-value">{{ data?.kpi?.critical_items || 0 }}</div>
              <div class="kpi-label">Stok Kritis / Rendah</div>
            </div>
            <i class="bi bi-graph-down kpi-icon"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-info">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-value">{{ data?.kpi?.pending_mr || 0 }}</div>
              <div class="kpi-label">MR Menunggu Proses</div>
            </div>
            <i class="bi bi-clipboard-check kpi-icon"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-xl-3">
        <div class="kpi-card kpi-success">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-value">{{ data?.kpi?.today_movements || 0 }}</div>
              <div class="kpi-label">Transaksi Hari Ini</div>
            </div>
            <i class="bi bi-arrow-left-right kpi-icon"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <!-- Minus & Critical Items -->
      <div class="col-12 col-xl-5">
        <!-- Stok Minus -->
        <div class="csm-card mb-3" v-if="data?.minus_list?.length > 0">
          <div class="csm-card-header">
            <h6><i class="bi bi-exclamation-octagon text-danger me-2"></i>Barang Stok Minus</h6>
            <router-link to="/stok/ho" class="btn btn-sm btn-outline-danger">Lihat Semua</router-link>
          </div>
          <div class="csm-card-body p-0">
            <div class="table-responsive">
              <table class="table csm-table mb-0">
                <thead><tr><th>Barang</th><th>Gudang</th><th class="text-end">Stok</th></tr></thead>
                <tbody>
                  <tr v-for="s in data.minus_list" :key="s.id">
                    <td>
                      <div class="fw-semibold">{{ s.item?.name }}</div>
                      <small class="text-muted">{{ s.item?.part_number }}</small>
                    </td>
                    <td><small>{{ s.warehouse?.name }}</small></td>
                    <td class="text-end"><span class="stock-minus">{{ $formatNumber(s.qty) }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Stok Kritis -->
        <div class="csm-card" v-if="!isPurchasing">
          <div class="csm-card-header">
            <h6><i class="bi bi-graph-down-arrow text-warning me-2"></i>Stok Kritis / Perlu Restock</h6>
          </div>
          <div class="csm-card-body p-0">
            <div v-if="loading" class="p-4 text-center text-muted">
              <div class="csm-spinner"></div>
            </div>
            <div class="table-responsive" v-else>
              <table class="table csm-table mb-0">
                <thead><tr><th>Barang</th><th>Gudang</th><th class="text-end">Stok</th><th class="text-end">Min</th></tr></thead>
                <tbody>
                  <tr v-for="s in data?.critical_list" :key="s.id">
                    <td>
                      <div class="fw-semibold small">{{ s.item?.name }}</div>
                      <small class="text-muted">{{ s.item?.part_number }}</small>
                    </td>
                    <td><small>{{ s.warehouse?.name }}</small></td>
                    <td class="text-end"><span :class="s.qty < 0 ? 'stock-minus' : 'stock-low'">{{ $formatNumber(s.qty) }}</span></td>
                    <td class="text-end text-muted small">{{ s.item?.min_stock }}</td>
                  </tr>
                  <tr v-if="!data?.critical_list?.length">
                    <td colspan="4" class="text-center text-muted py-4">✅ Semua stok dalam kondisi aman</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div class="col-12 col-xl-7">
        <!-- Pending MRs -->
        <div class="csm-card mb-3">
          <div class="csm-card-header">
            <h6><i class="bi bi-clipboard-check text-primary me-2"></i>Material Request Pending</h6>
            <router-link to="/permintaan-material" class="btn btn-sm btn-outline-primary">Lihat Semua</router-link>
          </div>
          <div class="csm-card-body p-0">
            <div class="table-responsive">
              <table class="table csm-table mb-0">
                <thead><tr><th>No. MR / PM</th><th>Gudang</th><th>Diajukan</th><th>Status</th><th>Tanggal</th></tr></thead>
                <tbody>
                  <tr v-for="mr in data?.pending_mrs" :key="`${mr.type}-${mr.id}`">
                    <td>
                      <router-link :to="mr.url" class="fw-semibold text-primary">{{ mr.nomor }}</router-link>
                      <span class="badge ms-1 small" :class="mr.type === 'pm' ? 'bg-primary' : 'bg-secondary'">
                        {{ mr.type === 'pm' ? 'PM' : 'MR' }}
                      </span>
                    </td>
                    <td><small>{{ mr.warehouse?.name || '-' }}</small></td>
                    <td><small>{{ mr.requester?.name || '-' }}</small></td>
                    <td>
                      <span class="badge rounded-pill" :class="{
                        'bg-warning text-dark': ['pending_chief','pending_manager','pending_ho','submitted'].includes(mr.status),
                        'bg-primary': ['approved','manager_approved'].includes(mr.status),
                        'bg-info text-dark': ['purchasing','partial_ordered'].includes(mr.status),
                      }">{{ pmStatusLabel(mr.status) }}</span>
                    </td>
                    <td><small>{{ $formatDate(mr.created_at) }}</small></td>
                  </tr>
                  <tr v-if="!data?.pending_mrs?.length">
                    <td colspan="5" class="text-center text-muted py-3">Tidak ada MR / PM pending</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Recent movements -->
        <div class="csm-card" v-if="!isPurchasing">
          <div class="csm-card-header">
            <h6><i class="bi bi-clock-history text-secondary me-2"></i>Aktivitas Terbaru</h6>
            <router-link to="/mutasi" class="btn btn-sm btn-outline-secondary">Histori Lengkap</router-link>
          </div>
          <div class="csm-card-body p-0">
            <div class="table-responsive">
              <table class="table csm-table mb-0">
                <thead><tr><th>Barang</th><th>Tipe</th><th class="text-end">Qty</th><th>User</th><th>Waktu</th></tr></thead>
                <tbody>
                  <tr v-for="m in data?.recent_movements?.slice(0,8)" :key="m.id">
                    <td>
                      <div class="fw-semibold small">{{ m.item?.name }}</div>
                      <small class="text-muted">{{ m.from_warehouse?.name || m.to_warehouse?.name }}</small>
                    </td>
                    <td>
                      <span class="badge rounded-pill" :class="{
                        'bg-success': m.type === 'in' || m.type === 'transfer_in',
                        'bg-danger': m.type === 'out' || m.type === 'transfer_out',
                        'bg-secondary': m.type === 'adjustment' || m.type === 'opname',
                      }">{{ movLabel(m.type) }}</span>
                    </td>
                    <td class="text-end fw-semibold">{{ $formatNumber(m.qty) }}</td>
                    <td><small>{{ m.creator?.name }}</small></td>
                    <td><small class="text-muted">{{ $formatDate(m.created_at) }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ FINANCE DASHBOARD (untuk accounting/superuser) ═══ -->
    <div v-if="isAccounting" class="mt-4">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0" style="color:#1a3a5c;">
          <i class="bi bi-bank me-2"></i>Finance Overview
        </h6>
        <router-link to="/laporan/accounting" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-file-earmark-bar-graph me-1"></i>Laporan Lengkap
        </router-link>
      </div>

      <div v-if="loadingFinance" class="text-center py-3"><div class="csm-spinner"></div></div>
      <div v-else>
        <!-- Finance KPI Cards -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-2-4">
            <div class="kpi-card kpi-primary">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="kpi-value" style="font-size:1.1rem;">{{ $formatCurrency(finance.total_kas_besar || 0) }}</div>
                  <div class="kpi-label">Total Kas Besar</div>
                </div>
                <i class="bi bi-bank kpi-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2-4">
            <div class="kpi-card kpi-info">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="kpi-value" style="font-size:1.1rem;">{{ $formatCurrency(finance.total_kas_kecil || 0) }}</div>
                  <div class="kpi-label">Total Kas Kecil</div>
                </div>
                <i class="bi bi-wallet2 kpi-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2-4">
            <div class="kpi-card kpi-danger">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="kpi-value" style="font-size:1.1rem;">{{ $formatCurrency(finance.total_hutang_supplier || 0) }}</div>
                  <div class="kpi-label">Total Hutang Supplier</div>
                </div>
                <i class="bi bi-receipt kpi-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2-4">
            <div class="kpi-card kpi-warning">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="kpi-value" style="font-size:1.1rem;">{{ $formatCurrency(finance.kas_keluar_bulan_ini || 0) }}</div>
                  <div class="kpi-label">Pengeluaran Bulan Ini</div>
                </div>
                <i class="bi bi-cash-stack kpi-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2-4">
            <div class="kpi-card kpi-success">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="kpi-value" style="font-size:1.1rem;">{{ $formatCurrency(finance.total_gaji_bulan_ini || 0) }}</div>
                  <div class="kpi-label">Biaya Payroll</div>
                  <div class="kpi-sub" v-if="finance.periode_berjalan" style="font-size:10px;opacity:0.8;">{{ finance.periode_berjalan }}</div>
                </div>
                <i class="bi bi-people kpi-icon"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Alert Invoice Jatuh Tempo -->
        <div class="row g-3">
          <div class="col-md-6">
            <div class="csm-card">
              <div class="csm-card-header">
                <h6><i class="bi bi-exclamation-circle text-danger me-2"></i>Invoice Jatuh Tempo</h6>
                <router-link to="/accounting/invoice" class="btn btn-sm btn-outline-danger">Lihat Semua</router-link>
              </div>
              <div class="csm-card-body">
                <div v-if="finance.invoice_jatuh_tempo > 0" class="alert alert-danger py-2 mb-0 small">
                  <i class="bi bi-exclamation-triangle me-2"></i>
                  <strong>{{ finance.invoice_jatuh_tempo }} invoice</strong> sudah melewati jatuh tempo dan belum dibayar.
                  Segera proses pembayaran.
                </div>
                <div v-else class="text-success small">
                  <i class="bi bi-check-circle me-2"></i>Tidak ada invoice yang jatuh tempo. Semua tagihan aman.
                </div>
                <div class="d-flex justify-content-between mt-3 small">
                  <span class="text-muted">Pembayaran Menunggu Persetujuan:</span>
                  <span class="fw-bold" :class="finance.pembayaran_pending > 0 ? 'text-warning' : 'text-success'">
                    {{ finance.pembayaran_pending || 0 }} pembayaran
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="csm-card">
              <div class="csm-card-header">
                <h6><i class="bi bi-bar-chart text-primary me-2"></i>Ringkasan Keuangan Bulan Ini</h6>
              </div>
              <div class="csm-card-body">
                <div class="d-flex justify-content-between border-bottom py-2 small">
                  <span class="text-muted">Total Kas (Besar + Kecil)</span>
                  <span class="fw-bold text-success">{{ $formatCurrency((finance.total_kas_besar || 0) + (finance.total_kas_kecil || 0)) }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2 small">
                  <span class="text-muted">Hutang Supplier Outstanding</span>
                  <span class="fw-bold text-danger">{{ $formatCurrency(finance.total_hutang_supplier || 0) }}</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2 small">
                  <span class="text-muted">Pengeluaran Kas Besar</span>
                  <span class="fw-bold text-warning">{{ $formatCurrency(finance.kas_keluar_bulan_ini || 0) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 small">
                  <span class="text-muted">Biaya Payroll</span>
                  <span class="fw-bold">{{ $formatCurrency(finance.total_gaji_bulan_ini || 0) }}</span>
                </div>
                <div class="d-flex justify-content-between pt-2 border-top small fw-bold">
                  <span>Posisi Kas Bersih (est.)</span>
                  <span :class="((finance.total_kas_besar || 0) + (finance.total_kas_kecil || 0) - (finance.kas_keluar_bulan_ini || 0)) >= 0 ? 'text-success' : 'text-danger'">
                    {{ $formatCurrency((finance.total_kas_besar || 0) + (finance.total_kas_kecil || 0) - (finance.kas_keluar_bulan_ini || 0)) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { useAuthStore } from '@/store/auth'

const auth = useAuthStore()
const isPurchasing = computed(() => auth.hasRole('purchasing'))
const isAccounting = computed(() => auth.hasPermission('view-accounting') || auth.user?.is_superuser)

const data = ref(null)
const finance = ref({})
const loading = ref(true)
const loadingFinance = ref(false)

const { listenStok, stopStok } = useRealtime()

const pmStatusLabel = (s) => ({
  draft: "Draft", pending_chief: "Menunggu Chief", pending_manager: "Menunggu Manager",
  pending_ho: "Menunggu HO", manager_approved: "Disetujui Manager", approved: "Disetujui HO",
  purchasing: "Proses Purchasing", partial_ordered: "Sebagian PO", submitted: "Submitted",
  bon_pengeluaran: "Bon Pengeluaran", completed: "Selesai"
}[s] || s)

const movLabel = (type) => ({
  in: 'Masuk', out: 'Keluar', transfer_in: 'Terima', transfer_out: 'Kirim',
  adjustment: 'Adj', opname: 'Opname'
}[type] || type)

async function load() {
  try {
    const res = await axios.get('/dashboard')
    data.value = res.data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadFinance() {
  if (!isAccounting.value) return
  loadingFinance.value = true
  try {
    const res = await axios.get('/dashboard/accounting')
    finance.value = res.data.data
  } catch (e) {
    console.error('Finance dashboard error:', e)
  } finally {
    loadingFinance.value = false
  }
}

onMounted(() => {
  load()
  loadFinance()
  listenStok(() => load())
})

onUnmounted(() => stopStok())
</script>