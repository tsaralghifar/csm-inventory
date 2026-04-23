<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Laporan Hutang Supplier</h5>
        <small class="text-muted">Daftar invoice belum lunas dan status pembayaran</small>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="exportExcel">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
      </button>
    </div>

    <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
    <div v-else>
      <!-- Summary -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="csm-card h-100">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Total Hutang Outstanding</div>
              <div class="fs-4 fw-bold text-danger">{{ $formatCurrency(summary.total_payable || 0) }}</div>
              <div class="text-muted small">{{ summary.invoice_count || 0 }} invoice</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="csm-card h-100 border border-danger">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Sudah Jatuh Tempo</div>
              <div class="fs-4 fw-bold text-danger">{{ $formatCurrency(summary.total_overdue || 0) }}</div>
              <div class="text-danger small">{{ overdueCount }} invoice overdue</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="csm-card h-100">
            <div class="csm-card-body text-center">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Belum Jatuh Tempo</div>
              <div class="fs-4 fw-bold text-warning">{{ $formatCurrency((summary.total_payable || 0) - (summary.total_overdue || 0)) }}</div>
              <div class="text-muted small">Masih dalam grace period</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Aging Analysis -->
      <div class="csm-card mb-4">
        <div class="csm-card-header">
          <h6><i class="bi bi-clock-history me-2 text-warning"></i>Aging Analysis Hutang</h6>
        </div>
        <div class="csm-card-body">
          <div class="row g-3">
            <div class="col" v-for="bucket in agingBuckets" :key="bucket.label">
              <div class="text-center p-2 rounded" :class="bucket.class">
                <div class="small text-muted fw-semibold">{{ bucket.label }}</div>
                <div class="fw-bold mt-1">{{ $formatCurrency(bucket.amount) }}</div>
                <div class="small text-muted">{{ bucket.count }} inv.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabel Invoice -->
      <div class="csm-card">
        <div class="csm-card-header">
          <h6><i class="bi bi-receipt me-2"></i>Detail Invoice Belum Lunas</h6>
        </div>
        <div class="csm-card-body p-0">
          <div class="table-responsive">
            <table class="table csm-table mb-0">
              <thead>
                <tr>
                  <th>No. Invoice</th><th>Supplier</th><th>Ref PO</th>
                  <th>Tgl Invoice</th><th>Jatuh Tempo</th><th>Hari Overdue</th>
                  <th class="text-end">Total</th><th class="text-end">Terbayar</th>
                  <th class="text-end">Sisa</th><th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!invoices.length">
                  <td colspan="10" class="text-center text-muted py-4">
                    <i class="bi bi-check-circle text-success me-2 fs-5"></i>
                    Tidak ada hutang outstanding. Semua invoice sudah lunas!
                  </td>
                </tr>
                <tr v-for="inv in invoices" :key="inv.id"
                  :class="inv.is_overdue ? 'table-danger' : ''">
                  <td>
                    <div class="small fw-semibold text-primary">{{ inv.invoice_number }}</div>
                  </td>
                  <td class="small fw-semibold">{{ inv.supplier }}</td>
                  <td class="small">
                    <span v-if="inv.po_number" class="badge bg-info text-dark">{{ inv.po_number }}</span>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td class="small">{{ $formatDate(inv.invoice_date) }}</td>
                  <td class="small" :class="inv.is_overdue ? 'text-danger fw-bold' : ''">
                    {{ $formatDate(inv.due_date) }}
                  </td>
                  <td class="text-center">
                    <span v-if="inv.is_overdue" class="badge bg-danger">{{ inv.days_overdue }} hari</span>
                    <span v-else class="text-success small"><i class="bi bi-check-circle"></i></span>
                  </td>
                  <td class="text-end small">{{ $formatCurrency(inv.total_amount) }}</td>
                  <td class="text-end small text-success">{{ $formatCurrency(inv.paid_amount) }}</td>
                  <td class="text-end small fw-bold text-danger">{{ $formatCurrency(inv.remaining_amount) }}</td>
                  <td>
                    <span class="badge" :class="inv.status === 'partial' ? 'bg-warning text-dark' : 'bg-danger'">
                      {{ inv.status === 'partial' ? 'Parsial' : 'Belum Bayar' }}
                    </span>
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="invoices.length" class="table-secondary fw-bold">
                <tr>
                  <td colspan="6" class="text-end">Total Hutang Outstanding</td>
                  <td class="text-end">{{ $formatCurrency(invoices.reduce((s,i) => s + parseFloat(i.total_amount), 0)) }}</td>
                  <td class="text-end text-success">{{ $formatCurrency(invoices.reduce((s,i) => s + parseFloat(i.paid_amount), 0)) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(summary.total_payable || 0) }}</td>
                  <td></td>
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
import { ref, computed, onMounted } from 'vue'
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
const summary = ref({ total_payable: 0, total_overdue: 0, invoice_count: 0 })
const invoices = ref([])

const overdueCount = computed(() => invoices.value.filter(i => i.is_overdue).length)

const agingBuckets = computed(() => {
  const b = [
    { label: 'Belum JT',  min: null, max: 0,   class: 'bg-light border', amount: 0, count: 0 },
    { label: '1-30 hari', min: 1,    max: 30,  class: 'bg-warning bg-opacity-25', amount: 0, count: 0 },
    { label: '31-60 hari',min: 31,   max: 60,  class: 'bg-warning bg-opacity-50', amount: 0, count: 0 },
    { label: '61-90 hari',min: 61,   max: 90,  class: 'bg-danger bg-opacity-25', amount: 0, count: 0 },
    { label: '>90 hari',  min: 91,   max: null, class: 'bg-danger bg-opacity-50', amount: 0, count: 0 },
  ]
  invoices.value.forEach(inv => {
    const d = inv.days_overdue || 0
    if (!inv.is_overdue) { b[0].amount += parseFloat(inv.remaining_amount); b[0].count++ }
    else if (d <= 30) { b[1].amount += parseFloat(inv.remaining_amount); b[1].count++ }
    else if (d <= 60) { b[2].amount += parseFloat(inv.remaining_amount); b[2].count++ }
    else if (d <= 90) { b[3].amount += parseFloat(inv.remaining_amount); b[3].count++ }
    else              { b[4].amount += parseFloat(inv.remaining_amount); b[4].count++ }
  })
  return b
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/supplier-payable')
    summary.value  = r.data.data.summary
    invoices.value = r.data.data.invoices
  } finally { loading.value = false }
}

function exportExcel() {
  const rows = invoices.value.map(i => ({
    'No. Invoice': i.invoice_number, 'Supplier': i.supplier, 'Ref PO': i.po_number || '',
    'Tgl Invoice': i.invoice_date, 'Jatuh Tempo': i.due_date,
    'Hari Overdue': i.days_overdue || 0, 'Total': i.total_amount,
    'Terbayar': i.paid_amount, 'Sisa': i.remaining_amount, 'Status': i.status,
  }))
  exportCSV(rows, `hutang-supplier-${new Date().toISOString().split('T')[0]}.csv`)
}
</script>
