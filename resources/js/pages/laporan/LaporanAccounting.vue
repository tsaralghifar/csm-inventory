<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Laporan Accounting</h5>
        <small class="text-muted">Ringkasan kas, hutang supplier, dan arus kas</small>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="exportExcel">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
      </button>
    </div>

    <!-- Filter Periode -->
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
          <div class="col-md-3">
            <button class="btn btn-outline-secondary btn-sm" @click="resetFilter">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
    <div v-else>
      <!-- Ringkasan Kas -->
      <h6 class="fw-bold text-primary mb-2"><i class="bi bi-wallet2 me-1"></i>Posisi Kas</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="csm-card">
            <div class="csm-card-body">
              <div class="fw-bold mb-2 text-muted small text-uppercase">Kas Kecil</div>
              <div v-for="a in summary.petty_cash" :key="a.id" class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                  <div class="small fw-semibold">{{ a.name }}</div>
                  <div class="text-muted" style="font-size:11px;">{{ a.warehouse_name || 'Kantor Pusat' }}</div>
                </div>
                <div class="fw-bold" :class="a.balance < a.limit * 0.2 ? 'text-danger' : 'text-success'">
                  {{ $formatCurrency(a.balance) }}
                </div>
              </div>
              <div class="d-flex justify-content-between mt-2 pt-1 border-top fw-bold">
                <span>Total</span>
                <span class="text-success">{{ $formatCurrency(summary.petty_cash?.reduce((a,i)=>a+parseFloat(i.balance),0) || 0) }}</span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="csm-card">
            <div class="csm-card-body">
              <div class="fw-bold mb-2 text-muted small text-uppercase">Kas Besar / Rekening</div>
              <div v-for="a in summary.main_cash" :key="a.id" class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                  <div class="small fw-semibold">{{ a.name }}</div>
                  <div class="text-muted" style="font-size:11px;">{{ a.bank_name || 'Tunai' }} {{ a.account_number ? '· ' + a.account_number : '' }}</div>
                </div>
                <div class="fw-bold text-primary">{{ $formatCurrency(a.balance) }}</div>
              </div>
              <div class="d-flex justify-content-between mt-2 pt-1 border-top fw-bold">
                <span>Total</span>
                <span class="text-primary">{{ $formatCurrency(summary.main_cash?.reduce((a,i)=>a+parseFloat(i.balance),0) || 0) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Hutang Supplier -->
      <h6 class="fw-bold text-danger mb-2"><i class="bi bi-receipt me-1"></i>Hutang Supplier (Invoice Belum Lunas)</h6>
      <div class="csm-card mb-4">
        <div class="csm-card-body p-0">
          <div class="table-responsive">
            <table class="table csm-table mb-0">
              <thead>
                <tr>
                  <th>Supplier</th><th>No. Invoice</th><th>Tgl Invoice</th><th>Jatuh Tempo</th>
                  <th class="text-end">Total</th><th class="text-end">Terbayar</th><th class="text-end">Sisa</th><th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!summary.unpaid_invoices?.length">
                  <td colspan="8" class="text-center text-muted py-3">Tidak ada hutang outstanding</td>
                </tr>
                <tr v-for="inv in summary.unpaid_invoices" :key="inv.id" :class="isOverdue(inv) ? 'table-danger' : ''">
                  <td class="small fw-semibold">{{ inv.supplier?.name }}</td>
                  <td><code class="small">{{ inv.invoice_number }}</code></td>
                  <td class="small">{{ $formatDate(inv.invoice_date) }}</td>
                  <td class="small" :class="isOverdue(inv) ? 'text-danger fw-bold' : ''">{{ $formatDate(inv.due_date) }}</td>
                  <td class="text-end small">{{ $formatCurrency(inv.total_amount) }}</td>
                  <td class="text-end small text-success">{{ $formatCurrency(inv.paid_amount) }}</td>
                  <td class="text-end small fw-bold text-danger">{{ $formatCurrency(inv.remaining_amount) }}</td>
                  <td><span :class="inv.status === 'partial' ? 'badge bg-warning text-dark' : 'badge bg-danger'">
                    {{ inv.status === 'partial' ? 'Sebagian' : 'Belum Bayar' }}
                  </span></td>
                </tr>
              </tbody>
              <tfoot class="table-light" v-if="summary.unpaid_invoices?.length">
                <tr>
                  <td colspan="4" class="fw-bold">TOTAL HUTANG</td>
                  <td class="text-end fw-bold">{{ $formatCurrency(summary.unpaid_invoices.reduce((a,i)=>a+parseFloat(i.total_amount),0)) }}</td>
                  <td class="text-end fw-bold text-success">{{ $formatCurrency(summary.unpaid_invoices.reduce((a,i)=>a+parseFloat(i.paid_amount),0)) }}</td>
                  <td class="text-end fw-bold text-danger fs-6">{{ $formatCurrency(summary.unpaid_invoices.reduce((a,i)=>a+parseFloat(i.remaining_amount),0)) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <!-- Arus Kas Periode -->
      <h6 class="fw-bold text-info mb-2"><i class="bi bi-arrow-left-right me-1"></i>Arus Kas Periode</h6>
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="text-muted small">Kas Kecil Masuk</div>
              <div class="fw-bold text-success">{{ $formatCurrency(summary.cash_flow?.petty_in || 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="text-muted small">Kas Kecil Keluar</div>
              <div class="fw-bold text-danger">{{ $formatCurrency(summary.cash_flow?.petty_out || 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="text-muted small">Kas Besar Masuk</div>
              <div class="fw-bold text-success">{{ $formatCurrency(summary.cash_flow?.main_in || 0) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="text-muted small">Kas Besar Keluar</div>
              <div class="fw-bold text-danger">{{ $formatCurrency(summary.cash_flow?.main_out || 0) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const toast = useToast()
const loading = ref(false)
const summary = ref({ petty_cash: [], main_cash: [], unpaid_invoices: [], cash_flow: {} })
const now = new Date()
const firstDay = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
const lastDay  = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0]
const filter = ref({ date_from: firstDay, date_to: lastDay })

onMounted(() => load())

async function load() {
  loading.value = true
  try {
    const [pcRes, mcRes, invRes, pctRes, mctRes] = await Promise.all([
      axios.get('/petty-cash/accounts'),
      axios.get('/main-cash/accounts'),
      axios.get('/supplier-invoices', { params: { status: 'unpaid,partial', per_page: 100 } }),
      axios.get('/petty-cash/transactions', { params: { status: 'approved', per_page: 500, ...filter.value } }),
      axios.get('/main-cash/transactions', { params: { status: 'approved', per_page: 500, ...filter.value } }),
    ])

    // Arus kas periode
    const pct = pctRes.data.data || []
    const mct = mctRes.data.data || []

    summary.value = {
      petty_cash:      pcRes.data.data,
      main_cash:       mcRes.data.data,
      unpaid_invoices: invRes.data.data,
      cash_flow: {
        petty_in:  pct.filter(t => t.type === 'in').reduce((a, t) => a + parseFloat(t.amount), 0),
        petty_out: pct.filter(t => t.type === 'out').reduce((a, t) => a + parseFloat(t.amount), 0),
        main_in:   mct.filter(t => t.type === 'in').reduce((a, t) => a + parseFloat(t.amount), 0),
        main_out:  mct.filter(t => t.type === 'out').reduce((a, t) => a + parseFloat(t.amount), 0),
      }
    }
  } finally { loading.value = false }
}

function resetFilter() {
  filter.value = { date_from: firstDay, date_to: lastDay }
  load()
}

function isOverdue(inv) { return new Date(inv.due_date) < new Date() }

function exportExcel() {
  toast.info('Fitur export Excel akan segera tersedia')
  // TODO: implement export via axios blob download
}
</script>
