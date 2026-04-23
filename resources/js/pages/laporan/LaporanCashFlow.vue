<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Laporan Cash Flow</h5>
        <small class="text-muted">Arus kas masuk dan keluar per periode</small>
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
      <!-- Summary Cards -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="csm-card h-100">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Kas Besar Masuk</div>
              <div class="fs-5 fw-bold text-success">{{ $formatCurrency(data.main_cash_in || 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card h-100">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Kas Besar Keluar</div>
              <div class="fs-5 fw-bold text-danger">{{ $formatCurrency(data.main_cash_out || 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card h-100">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Pembayaran Supplier</div>
              <div class="fs-5 fw-bold text-warning">{{ $formatCurrency(data.supplier_paid || 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card h-100 border-2" :class="(data.net_cash_flow || 0) >= 0 ? 'border-success' : 'border-danger'">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Net Cash Flow</div>
              <div class="fs-5 fw-bold" :class="(data.net_cash_flow || 0) >= 0 ? 'text-success' : 'text-danger'">
                {{ $formatCurrency(data.net_cash_flow || 0) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cash Flow Statement -->
      <div class="csm-card mb-4">
        <div class="csm-card-header">
          <h6><i class="bi bi-bar-chart me-2"></i>Ringkasan Arus Kas</h6>
        </div>
        <div class="csm-card-body p-0">
          <table class="table csm-table mb-0">
            <tbody>
              <tr class="table-light">
                <td colspan="2" class="fw-bold text-primary">A. Arus Kas dari Aktivitas Operasional</td>
              </tr>
              <tr>
                <td class="ps-4">Kas Masuk (Kas Besar)</td>
                <td class="text-end text-success fw-semibold">{{ $formatCurrency(data.main_cash_in || 0) }}</td>
              </tr>
              <tr>
                <td class="ps-4">Kas Kecil Masuk</td>
                <td class="text-end text-success">{{ $formatCurrency(data.petty_cash_in || 0) }}</td>
              </tr>
              <tr>
                <td class="ps-4">Kas Keluar (Kas Besar)</td>
                <td class="text-end text-danger">({{ $formatCurrency(data.main_cash_out || 0) }})</td>
              </tr>
              <tr>
                <td class="ps-4">Kas Kecil Keluar</td>
                <td class="text-end text-danger">({{ $formatCurrency(data.petty_cash_out || 0) }})</td>
              </tr>
              <tr class="table-light fw-bold border-top">
                <td class="ps-4">Subtotal Arus Kas Operasional</td>
                <td class="text-end" :class="((data.main_cash_in||0)+(data.petty_cash_in||0)-(data.main_cash_out||0)-(data.petty_cash_out||0)) >= 0 ? 'text-success' : 'text-danger'">
                  {{ $formatCurrency((data.main_cash_in||0)+(data.petty_cash_in||0)-(data.main_cash_out||0)-(data.petty_cash_out||0)) }}
                </td>
              </tr>
              <tr class="table-light">
                <td colspan="2" class="fw-bold text-danger">B. Pembayaran Kewajiban</td>
              </tr>
              <tr>
                <td class="ps-4">Pembayaran Hutang Supplier</td>
                <td class="text-end text-danger">({{ $formatCurrency(data.supplier_paid || 0) }})</td>
              </tr>
              <tr>
                <td class="ps-4">Biaya Payroll</td>
                <td class="text-end text-danger">({{ $formatCurrency(data.payroll_cost || 0) }})</td>
              </tr>
              <tr class="table-dark fw-bold">
                <td>NET CASH FLOW</td>
                <td class="text-end fs-6" :class="(data.net_cash_flow||0) >= 0 ? 'text-success' : 'text-danger'">
                  {{ $formatCurrency(data.net_cash_flow || 0) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Rincian Transaksi Kas Besar -->
      <div class="csm-card">
        <div class="csm-card-header">
          <h6><i class="bi bi-list-ul me-2"></i>Rincian Transaksi Kas Besar</h6>
        </div>
        <div class="csm-card-body p-0">
          <div class="table-responsive">
            <table class="table csm-table mb-0">
              <thead>
                <tr>
                  <th>Tgl</th><th>No. Transaksi</th><th>Akun</th><th>Keterangan</th>
                  <th>Ref</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!data.transactions?.length">
                  <td colspan="7" class="text-center text-muted py-4">Tidak ada transaksi pada periode ini</td>
                </tr>
                <tr v-for="t in data.transactions" :key="t.id">
                  <td class="small">{{ $formatDate(t.transaction_date) }}</td>
                  <td><code class="small">{{ t.transaction_number }}</code></td>
                  <td class="small">{{ t.account?.name }}</td>
                  <td class="small">{{ t.description }}</td>
                  <td class="small text-muted">{{ t.reference_number || '—' }}</td>
                  <td class="text-end small text-success fw-semibold">
                    {{ t.type === 'in' ? $formatCurrency(t.amount) : '—' }}
                  </td>
                  <td class="text-end small text-danger fw-semibold">
                    {{ t.type === 'out' ? $formatCurrency(t.amount) : '—' }}
                  </td>
                </tr>
              </tbody>
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
const data = ref({ main_cash_in: 0, main_cash_out: 0, petty_cash_in: 0, petty_cash_out: 0, supplier_paid: 0, payroll_cost: 0, net_cash_flow: 0, transactions: [] })

const now = new Date()
const filter = ref({
  date_from: new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0],
  date_to:   new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0],
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/cash-flow', { params: filter.value })
    data.value = r.data.data
  } finally { loading.value = false }
}

function resetFilter() {
  filter.value.date_from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
  filter.value.date_to   = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0]
  load()
}

function exportExcel() {
  const rows = (data.value.transactions || []).map(t => ({
    'Tanggal': t.transaction_date, 'No. Transaksi': t.transaction_number,
    'Akun': t.account?.name, 'Keterangan': t.description, 'Referensi': t.reference_number || '',
    'Kas Masuk': t.type === 'in' ? t.amount : 0, 'Kas Keluar': t.type === 'out' ? t.amount : 0,
  }))
  exportCSV(rows, `cash-flow-${filter.value.date_from}-to-${filter.value.date_to}.csv`)
}
</script>
