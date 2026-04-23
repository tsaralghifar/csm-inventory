<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Laporan Beban / Pengeluaran</h5>
        <small class="text-muted">Rincian pengeluaran dari kas besar dan kas kecil</small>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="exportExcel">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
            <input v-model="filter.date_from" type="date" class="form-control form-control-sm" @change="load" />
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
            <input v-model="filter.date_to" type="date" class="form-control form-control-sm" @change="load" />
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary btn-sm" @click="resetFilter">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
    <div v-else>
      <!-- KPI -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="csm-card h-100">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Pengeluaran Kas Besar</div>
              <div class="fs-5 fw-bold text-danger">{{ $formatCurrency(data.total_main || 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="csm-card h-100">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Pengeluaran Kas Kecil</div>
              <div class="fs-5 fw-bold text-warning">{{ $formatCurrency(data.total_petty || 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="csm-card h-100 border-2 border-danger">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Total Pengeluaran</div>
              <div class="fs-4 fw-bold text-danger">{{ $formatCurrency(data.grand_total || 0) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Kas Besar vs Kecil -->
      <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
          <button class="nav-link" :class="activeTab === 'main' ? 'active' : ''" @click="activeTab = 'main'">
            <i class="bi bi-bank me-1"></i>Kas Besar
            <span class="badge bg-danger ms-1">{{ $formatCurrency(data.total_main || 0) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" :class="activeTab === 'petty' ? 'active' : ''" @click="activeTab = 'petty'">
            <i class="bi bi-wallet2 me-1"></i>Kas Kecil
            <span class="badge bg-warning text-dark ms-1">{{ $formatCurrency(data.total_petty || 0) }}</span>
          </button>
        </li>
      </ul>

      <!-- Tabel Kas Besar -->
      <div class="csm-card" v-if="activeTab === 'main'">
        <div class="csm-card-body p-0">
          <div class="table-responsive">
            <table class="table csm-table mb-0">
              <thead>
                <tr><th>Tgl</th><th>No. Transaksi</th><th>Akun</th><th>Keterangan</th><th>Ref</th><th>Dibuat Oleh</th><th class="text-end">Jumlah</th></tr>
              </thead>
              <tbody>
                <tr v-if="!data.main_expenses?.length">
                  <td colspan="7" class="text-center text-muted py-4">Tidak ada pengeluaran kas besar pada periode ini</td>
                </tr>
                <tr v-for="t in data.main_expenses" :key="t.id">
                  <td class="small">{{ $formatDate(t.transaction_date) }}</td>
                  <td><code class="small">{{ t.transaction_number }}</code></td>
                  <td class="small">{{ t.account?.name }}</td>
                  <td class="small">{{ t.description }}</td>
                  <td class="small text-muted">{{ t.reference_number || '—' }}</td>
                  <td class="small">{{ t.creator?.name }}</td>
                  <td class="text-end small fw-semibold text-danger">{{ $formatCurrency(t.amount) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="data.main_expenses?.length" class="table-secondary fw-bold">
                <tr>
                  <td colspan="6" class="text-end">Total Pengeluaran Kas Besar</td>
                  <td class="text-end text-danger">{{ $formatCurrency(data.total_main || 0) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <!-- Tabel Kas Kecil -->
      <div class="csm-card" v-if="activeTab === 'petty'">
        <div class="csm-card-body p-0">
          <div class="table-responsive">
            <table class="table csm-table mb-0">
              <thead>
                <tr><th>Tgl</th><th>No. Transaksi</th><th>Akun</th><th>Keterangan</th><th>Ref</th><th>Dibuat Oleh</th><th class="text-end">Jumlah</th></tr>
              </thead>
              <tbody>
                <tr v-if="!data.petty_expenses?.length">
                  <td colspan="7" class="text-center text-muted py-4">Tidak ada pengeluaran kas kecil pada periode ini</td>
                </tr>
                <tr v-for="t in data.petty_expenses" :key="t.id">
                  <td class="small">{{ $formatDate(t.transaction_date) }}</td>
                  <td><code class="small">{{ t.transaction_number }}</code></td>
                  <td class="small">{{ t.account?.name }}</td>
                  <td class="small">{{ t.description }}</td>
                  <td class="small text-muted">{{ t.reference_number || '—' }}</td>
                  <td class="small">{{ t.creator?.name }}</td>
                  <td class="text-end small fw-semibold text-warning">{{ $formatCurrency(t.amount) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="data.petty_expenses?.length" class="table-secondary fw-bold">
                <tr>
                  <td colspan="6" class="text-end">Total Pengeluaran Kas Kecil</td>
                  <td class="text-end text-warning">{{ $formatCurrency(data.total_petty || 0) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

// Export CSV helper (tanpa library eksternal)
function exportCSV(rows, filename) {
  if (!rows.length) return
  const headers = Object.keys(rows[0])
  const csvContent = [
    headers.join(','),
    ...rows.map(r => headers.map(h => {
      const val = r[h] ?? ''
      return typeof val === 'string' && (val.includes(',') || val.includes('"') || val.includes('\n'))
        ? `"${val.replace(/"/g, '""')}"` : val
    }).join(','))
  ].join('\n')
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = Object.assign(document.createElement('a'), { href: url, download: filename.replace('.xlsx','.csv') })
  document.body.appendChild(a); a.click()
  document.body.removeChild(a); URL.revokeObjectURL(url)
}

const loading = ref(false)
const activeTab = ref('main')
const data = ref({ main_expenses: [], petty_expenses: [], total_main: 0, total_petty: 0, grand_total: 0 })

const now = new Date()
const filter = ref({
  date_from: new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0],
  date_to:   new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0],
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/expense', { params: filter.value })
    data.value = r.data.data
  } finally { loading.value = false }
}

function resetFilter() {
  filter.value.date_from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
  filter.value.date_to   = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0]
  load()
}

function exportExcel() {
  const mainRows  = (data.value.main_expenses  || []).map(t => ({ 'Sumber': 'Kas Besar',  'Tgl': t.transaction_date, 'No. Transaksi': t.transaction_number, 'Akun': t.account?.name, 'Keterangan': t.description, 'Jumlah': t.amount }))
  const pettyRows = (data.value.petty_expenses || []).map(t => ({ 'Sumber': 'Kas Kecil', 'Tgl': t.transaction_date, 'No. Transaksi': t.transaction_number, 'Akun': t.account?.name, 'Keterangan': t.description, 'Jumlah': t.amount }))
  exportCSV([...mainRows, ...pettyRows], `laporan-beban-${filter.value.date_from}-to-${filter.value.date_to}.csv`)
}
</script>
