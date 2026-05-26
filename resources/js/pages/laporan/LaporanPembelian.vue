<template>
  <div>

    <!-- PAGE HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Laporan Pembelian Barang</h5>
        <small class="text-muted">Rekap Purchase Order — Cash, Kredit, maupun keduanya</small>
      </div>
      <div v-if="loaded" class="d-flex gap-2">
        <button class="btn btn-success btn-sm" @click="exportExcel" :disabled="exportingExcel">
          <span v-if="exportingExcel" class="spinner-border spinner-border-sm me-1" style="width:12px;height:12px;border-width:2px;"></span>
          <i v-else class="bi bi-file-earmark-excel me-1"></i>Excel
        </button>
        <button class="btn btn-danger btn-sm" @click="exportPdf" :disabled="exportingPdf">
          <span v-if="exportingPdf" class="spinner-border spinner-border-sm me-1" style="width:12px;height:12px;border-width:2px;"></span>
          <i v-else class="bi bi-file-earmark-pdf me-1"></i>PDF
        </button>
      </div>
    </div>

    <!-- FILTER -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-3">
        <div class="row g-2 align-items-end">

          <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold mb-1">Tgl. Dari</label>
            <input v-model="params.date_from" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold mb-1">Tgl. Sampai</label>
            <input v-model="params.date_to" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Jenis Pembayaran</label>
            <select v-model="params.payment_type" class="form-select form-select-sm">
              <option value="">Semua</option>
              <option value="cash">Cash</option>
              <option value="kredit">Kredit</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Status PO</label>
            <select v-model="params.status" class="form-select form-select-sm">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="sent_to_vendor">Dikirim ke Vendor</option>
              <option value="partial_received">Diterima Sebagian</option>
              <option value="completed">Selesai</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Supplier</label>
            <select v-model="params.supplier_id" class="form-select form-select-sm">
              <option value="">Semua Supplier</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="col-6 col-md-2 d-flex gap-2">
            <button class="btn btn-csm-primary btn-sm flex-grow-1" @click="load" :disabled="loading">
              <span v-if="loading" class="spinner-border spinner-border-sm me-1" style="width:12px;height:12px;border-width:2px;"></span>
              <i v-else class="bi bi-funnel me-1"></i>Tampilkan
            </button>
            <button v-if="loaded" class="btn btn-outline-secondary btn-sm px-2" @click="resetFilter" title="Reset">
              <i class="bi bi-arrow-counterclockwise"></i>
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- KPI CARDS -->
    <div v-if="loaded" class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="kpi-card kpi-primary">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-label">Total PO</div>
              <div class="kpi-value">{{ summary.total_po }}</div>
            </div>
            <i class="bi bi-file-earmark-text kpi-icon" style="font-size:1.6rem;opacity:.7;"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card kpi-success">
          <div class="d-flex justify-content-between align-items-start">
            <div style="min-width:0;flex:1;">
              <div class="kpi-label">Total Nilai Pembelian</div>
              <div class="fw-bold" style="font-size:0.92rem;line-height:1.4;word-break:break-all;">
                {{ $formatCurrency(summary.total_nilai) }}
              </div>
            </div>
            <i class="bi bi-cash-stack kpi-icon ms-2" style="font-size:1.6rem;opacity:.7;flex-shrink:0;"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#e0f2fe,#bae6fd);border-left:4px solid #0284c7;">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-label" style="color:#0c4a6e;">PO Cash</div>
              <div class="kpi-value" style="color:#0369a1;">{{ summary.total_cash }}</div>
              <div class="small" style="color:#0369a1;font-size:0.72rem;">{{ $formatCurrency(summary.nilai_cash) }}</div>
            </div>
            <i class="bi bi-wallet2" style="font-size:1.6rem;opacity:.7;color:#0369a1;"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fef3c7,#fde68a);border-left:4px solid #d97706;">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-label" style="color:#78350f;">PO Kredit</div>
              <div class="kpi-value" style="color:#b45309;">{{ summary.total_kredit }}</div>
              <div class="small" style="color:#b45309;font-size:0.72rem;">{{ $formatCurrency(summary.nilai_kredit) }}</div>
            </div>
            <i class="bi bi-credit-card" style="font-size:1.6rem;opacity:.7;color:#b45309;"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- TABLE -->
    <div v-if="loaded" class="csm-card">
      <div class="csm-card-header">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <h6 class="mb-0 fw-bold">Daftar Purchase Order</h6>
          <span class="badge rounded-pill bg-primary">{{ orders.length }} PO</span>
          <span v-if="params.payment_type === 'cash'" class="badge bg-info text-dark">Cash</span>
          <span v-if="params.payment_type === 'kredit'" class="badge bg-warning text-dark">Kredit</span>
        </div>
        <small class="text-muted d-none d-md-inline">
          {{ params.date_from || '—' }} s/d {{ params.date_to || '—' }}
        </small>
      </div>

      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center py-5">
          <div class="csm-spinner mx-auto mb-2"></div>
          <small class="text-muted">Memuat data...</small>
        </div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th class="ps-3" style="width:36px;">#</th>
                <th style="width:160px;">No. PO</th>
                <th>Vendor / Supplier</th>
                <th style="width:90px;">Gudang</th>
                <th style="width:80px;">Tgl. PO</th>
                <th style="width:75px;">Pembayaran</th>
                <th style="width:65px;">Tenor</th>
                <th style="width:90px;">Jatuh Tempo</th>
                <th class="text-end" style="width:110px;">Subtotal</th>
                <th class="text-end" style="width:100px;">Nilai PPN</th>
                <th class="text-end" style="width:120px;">Grand Total</th>
                <th style="width:90px;">Status</th>
                <th style="width:90px;">Status Bayar</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!orders.length">
                <td colspan="13" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                  Tidak ada data. Atur filter lalu klik <strong>Tampilkan</strong>.
                </td>
              </tr>

              <template v-for="(po, i) in orders" :key="po.id">
                <!-- PO ROW -->
                <tr :class="{ 'table-danger': isOverdue(po) }"
                    style="cursor:pointer;"
                    @click="po._expanded = !po._expanded">
                  <td class="ps-3 text-muted small fw-bold">{{ i + 1 }}</td>
                  <td>
                    <div class="d-flex align-items-center gap-1">
                      <i :class="po._expanded ? 'bi bi-chevron-down' : 'bi bi-chevron-right'"
                         class="text-muted" style="font-size:.65rem;"></i>
                      <code class="small fw-bold" style="color:#1a3a5c;font-size:0.75rem;">{{ po.po_number }}</code>
                    </div>
                    <small class="text-muted" style="font-size:0.7rem;">{{ po.items_count }} item</small>
                  </td>
                  <td>
                    <div class="small fw-semibold">{{ po.vendor_name }}</div>
                    <small v-if="po.supplier" class="text-muted">{{ po.supplier.name }}</small>
                  </td>
                  <td class="small text-muted">{{ po.warehouse?.name?.replace('Gudang ', '') || '—' }}</td>
                  <td class="small text-muted">{{ $formatDate(po.created_at) }}</td>
                  <td>
                    <span class="badge"
                      :class="po.payment_type === 'cash' ? 'bg-info text-dark' : 'bg-warning text-dark'">
                      <i :class="po.payment_type === 'cash' ? 'bi bi-wallet2' : 'bi bi-credit-card'"
                         class="me-1" style="font-size:.65rem;"></i>
                      {{ po.payment_type === 'cash' ? 'Cash' : 'Kredit' }}
                    </span>
                  </td>
                  <td class="text-center small text-muted">
                    {{ po.payment_term_days ? po.payment_term_days + ' hr' : '—' }}
                  </td>
                  <td class="small" :class="isOverdue(po) ? 'text-danger fw-bold' : 'text-muted'">
                    {{ po.payment_due_date ? $formatDate(po.payment_due_date) : '—' }}
                    <span v-if="isOverdue(po)" class="badge bg-danger ms-1" style="font-size:.6rem;">
                      OD {{ overdayCount(po) }}h
                    </span>
                  </td>
                  <td class="text-end small">{{ $formatCurrency(po.total_amount) }}</td>
                  <td class="text-end small">
                    <span v-if="po.ppn_percent > 0">
                      <span class="text-muted" style="font-size:.65rem;">{{ po.ppn_percent }}%</span><br>
                      <span style="color:#0369a1;">{{ $formatCurrency(po.ppn_amount) }}</span>
                    </span>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td class="text-end fw-bold small" style="color:#1a3a5c;">
                    {{ $formatCurrency(po.grand_total) }}
                  </td>
                  <td>
                    <span class="badge" :class="statusClass(po.status)" style="font-size:.68rem;">
                      {{ statusLabel(po.status) }}
                    </span>
                  </td>
                  <td>
                    <span v-if="po.payment_type === 'cash'" class="badge bg-success" style="font-size:.68rem;">Lunas</span>
                    <template v-else>
                      <span v-if="invoiceStatus(po) === 'paid'"    class="badge bg-success"          style="font-size:.68rem;">Lunas</span>
                      <span v-else-if="invoiceStatus(po) === 'partial'" class="badge bg-warning text-dark" style="font-size:.68rem;">Sebagian</span>
                      <span v-else-if="invoiceStatus(po) === 'overdue'" class="badge bg-danger"           style="font-size:.68rem;">Jatuh Tempo</span>
                      <span v-else                                  class="badge bg-secondary"        style="font-size:.68rem;">Belum Bayar</span>
                    </template>
                  </td>
                </tr>

                <!-- EXPANDED: items detail -->
                <template v-if="po._expanded">
                  <tr v-for="(item, ii) in (po.items || [])" :key="'item-'+item.id"
                      style="background:rgba(26,58,92,0.018);">
                    <td class="ps-3" style="border-left:3px solid #1a3a5c;"></td>
                    <td colspan="2">
                      <div class="d-flex align-items-start gap-2 ps-3">
                        <span class="text-muted" style="font-size:.7rem;min-width:14px;padding-top:1px;">{{ ii + 1 }}.</span>
                        <div>
                          <div class="small fw-semibold">{{ item.nama_barang }}
                            <code v-if="item.part_number" class="text-muted ms-1" style="font-size:.68rem;">{{ item.part_number }}</code>
                          </div>
                          <div v-if="item.item?.category" class="mt-1">
                            <span class="badge" style="font-size:.6rem;background:#e2e8f0;color:#475569;font-weight:500;">{{ item.item.category }}</span>
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="small text-muted">{{ item.kode_unit || '—' }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="text-end small text-muted">
                      {{ $formatNumber(item.qty) }} {{ item.satuan }}
                      × {{ $formatCurrency(item.harga_satuan) }}
                    </td>
                    <td></td>
                    <td class="text-end small fw-semibold" style="color:#1a3a5c;">
                      {{ $formatCurrency(item.total_harga) }}
                    </td>
                    <td colspan="2"></td>
                  </tr>

                  <!-- Sub total row -->
                  <tr v-if="(po.items || []).length > 0"
                      style="background:rgba(26,58,92,0.03);border-top:1px dashed #dee2e6;">
                    <td colspan="8" class="text-end small text-muted pe-2">
                      Subtotal {{ po.items.length }} item
                      <span v-if="po.diskon_persen > 0" class="text-danger ms-2">
                        (Diskon {{ po.diskon_persen }}% = {{ $formatCurrency(po.diskon_amount) }})
                      </span>
                    </td>
                    <td class="text-end small text-muted">{{ $formatCurrency(po.total_amount) }}</td>
                    <td class="text-center small">
                      <span v-if="po.ppn_percent > 0" class="text-info">
                        +{{ $formatCurrency(po.ppn_amount) }}
                      </span>
                      <span v-else class="text-muted">—</span>
                    </td>
                    <td class="text-end small fw-bold text-primary">{{ $formatCurrency(po.grand_total) }}</td>
                    <td colspan="2"></td>
                  </tr>

                  <!-- Invoice kredit detail -->
                  <template v-if="po.payment_type === 'kredit' && (po.supplier_invoices || []).length">
                    <tr v-for="inv in po.supplier_invoices" :key="'inv-'+inv.id"
                        style="background:#fffbeb;">
                      <td class="ps-3 text-muted" style="font-size:.68rem;">
                        <span class="ms-3 text-muted">↳</span>
                      </td>
                      <td colspan="2">
                        <div class="d-flex align-items-center gap-2 ps-3">
                          <span class="badge bg-warning text-dark" style="font-size:.6rem;">Invoice</span>
                          <span class="small fw-semibold text-warning-emphasis">{{ inv.invoice_number || inv.internal_number }}</span>
                        </div>
                      </td>
                      <td></td>
                      <td class="small text-muted">{{ $formatDate(inv.invoice_date) }}</td>
                      <td></td>
                      <td></td>
                      <td class="small" :class="inv.status !== 'paid' && inv.due_date < today ? 'text-danger fw-bold' : 'text-muted'">
                        {{ $formatDate(inv.due_date) }}
                      </td>
                      <td class="text-end small">{{ $formatCurrency(inv.total_amount) }}</td>
                      <td class="text-end small">
                        <span class="text-success">Bayar: {{ $formatCurrency(inv.paid_amount) }}</span><br>
                        <span class="text-danger fw-bold">Sisa: {{ $formatCurrency(inv.remaining_amount) }}</span>
                      </td>
                      <td colspan="2">
                        <span class="badge"
                          :class="inv.status==='paid' ? 'bg-success' : inv.status==='partial' ? 'bg-warning text-dark' : 'bg-danger'"
                          style="font-size:.65rem;">
                          {{ inv.status === 'paid' ? 'Lunas' : inv.status === 'partial' ? 'Parsial' : 'Belum Bayar' }}
                        </span>
                      </td>
                    </tr>
                  </template>
                </template>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Footer summary -->
        <div v-if="orders.length && !loading"
          class="d-flex justify-content-between align-items-center px-3 py-2 border-top"
          style="background:#f8fafc;border-radius:0 0 10px 10px;">
          <small class="text-muted">
            <strong>{{ orders.length }}</strong> Purchase Order
            <span class="ms-1">·
              <span class="badge bg-info text-dark me-1" style="font-size:.65rem;">Cash: {{ summary.total_cash }}</span>
              <span class="badge bg-warning text-dark" style="font-size:.65rem;">Kredit: {{ summary.total_kredit }}</span>
            </span>
          </small>
          <small class="text-muted d-none d-md-inline">
            Total Nilai:
            <strong class="text-success">{{ $formatCurrency(summary.total_nilai) }}</strong>
          </small>
        </div>
      </div>
    </div>

    <!-- EMPTY STATE -->
    <div v-else-if="!loading" class="csm-card">
      <div class="csm-card-body text-center py-5">
        <i class="bi bi-bag-check d-block mb-3 text-muted" style="font-size:2.5rem;opacity:.2;"></i>
        <p class="fw-semibold text-muted mb-1">Belum ada data</p>
        <small class="text-muted">Atur filter periode lalu klik <strong>Tampilkan</strong></small>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const toast = useToast()

const suppliers   = ref([])
const orders      = ref([])
const loading     = ref(false)
const loaded      = ref(false)
const exportingExcel = ref(false)
const exportingPdf   = ref(false)

const today = new Date().toISOString().slice(0, 10)

// Default: bulan berjalan
const nowDate = new Date()
const firstDay = new Date(nowDate.getFullYear(), nowDate.getMonth(), 1).toISOString().slice(0, 10)
const lastDay  = new Date(nowDate.getFullYear(), nowDate.getMonth() + 1, 0).toISOString().slice(0, 10)

const params = ref({
  date_from:    firstDay,
  date_to:      lastDay,
  payment_type: '',
  status:       '',
  supplier_id:  '',
})

const summary = ref({
  total_po: 0, total_nilai: 0,
  total_cash: 0, nilai_cash: 0,
  total_kredit: 0, nilai_kredit: 0,
})

// ─── Helpers ──────────────────────────────────────────────────────────────────

function statusLabel(s) {
  return {
    draft:            'Draft',
    sent_to_vendor:   'Dikirim',
    partial_received: 'Sebagian',
    completed:        'Selesai',
    cancelled:        'Dibatalkan',
  }[s] || s
}

function statusClass(s) {
  return {
    draft:            'bg-secondary',
    sent_to_vendor:   'bg-primary',
    partial_received: 'bg-info text-dark',
    completed:        'bg-success',
    cancelled:        'bg-danger',
  }[s] || 'bg-secondary'
}

function isOverdue(po) {
  return po.payment_type === 'kredit'
    && po.payment_due_date
    && po.payment_due_date < today
    && invoiceStatus(po) !== 'paid'
}

function overdayCount(po) {
  if (!po.payment_due_date) return 0
  const diff = new Date(today) - new Date(po.payment_due_date)
  return Math.floor(diff / 86400000)
}

function invoiceStatus(po) {
  const invs = po.supplier_invoices || []
  if (!invs.length) return 'none'
  if (invs.every(i => i.status === 'paid')) return 'paid'
  if (invs.some(i => i.status === 'partial')) return 'partial'
  if (isOverdue(po)) return 'overdue'
  return 'unpaid'
}

// ─── Data load ────────────────────────────────────────────────────────────────

onMounted(async () => {
  const r = await axios.get('/suppliers')
  suppliers.value = r.data.data || []
})

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/purchase', { params: params.value })
    orders.value  = r.data.data
    summary.value = r.data.summary
    loaded.value  = true
  } catch (e) {
    toast.error('Gagal memuat data laporan pembelian')
  } finally {
    loading.value = false
  }
}

function resetFilter() {
  params.value.payment_type = ''
  params.value.status       = ''
  params.value.supplier_id  = ''
}

// ─── Export Excel ─────────────────────────────────────────────────────────────

async function exportExcel() {
  exportingExcel.value = true
  try {
    if (!window._XLSXLoaded) {
      await new Promise((resolve, reject) => {
        const s = document.createElement('script')
        s.src = 'https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js'
        s.onload = () => { window._XLSXLoaded = true; resolve() }
        s.onerror = reject
        document.head.appendChild(s)
      })
    }
    const XLSX = window.XLSX

    const now     = new Date()
    const dateStr = now.toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' })
    const timeStr = now.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' })
    const fmtRp   = v => v > 0 ? 'Rp ' + Number(v).toLocaleString('id-ID') : '-'
    const ptLabel = { '': 'Semua', 'cash': 'Cash', 'kredit': 'Kredit' }

    const wb = XLSX.utils.book_new()
    const ws = {}
    ws['!merges'] = []

    ws['!cols'] = [
      {wch:4},   // # 
      {wch:22},  // No. PO / nama barang
      {wch:24},  // Vendor
      {wch:16},  // Gudang
      {wch:11},  // Tgl PO
      {wch:11},  // Pembayaran
      {wch:9},   // Tenor
      {wch:13},  // Jatuh Tempo
      {wch:19},  // Subtotal / qty×harga
      {wch:15},  // Nilai PPN
      {wch:17},  // Grand Total
      {wch:12},  // Status PO
      {wch:13},  // Status Bayar
    ]
    ws['!rows'] = []
    const setRowH = (r, hpt) => { ws['!rows'][r] = { hpt } }

    const B = (style='thin', color='D0D9E8') => ({ style, color: { rgb: color } })
    const brd = () => ({ top:B(), bottom:B(), left:B(), right:B() })

    const sc = (r, c, v, s) => {
      const addr = XLSX.utils.encode_cell({ r, c })
      ws[addr] = { v: v ?? '', t: typeof v === 'number' ? 'n' : 's', s: s || {} }
    }
    const mg = (r1, c1, r2, c2) => ws['!merges'].push({ s:{r:r1,c:c1}, e:{r:r2,c:c2} })

    const S = {
      h1: { font:{bold:true,sz:14,color:{rgb:'FFFFFF'}}, fill:{fgColor:{rgb:'1A3A5C'}}, alignment:{vertical:'center',indent:1} },
      h2: { font:{sz:9,color:{rgb:'BFD0E8'}},            fill:{fgColor:{rgb:'243F6A'}}, alignment:{vertical:'center',indent:1} },
      iL: { font:{bold:true,sz:9,color:{rgb:'1A3A5C'}},  fill:{fgColor:{rgb:'D6E4F5'}}, alignment:{vertical:'center',indent:1}, border:brd() },
      iV: { font:{sz:9,color:{rgb:'1A3A5C'}},            fill:{fgColor:{rgb:'EBF3FD'}}, alignment:{vertical:'center',indent:1}, border:brd() },
      th: { font:{bold:true,sz:9,color:{rgb:'FFFFFF'}},  fill:{fgColor:{rgb:'1A3A5C'}}, alignment:{horizontal:'center',vertical:'center',wrapText:true}, border:{top:B('medium','FFFFFF'),bottom:B('medium','4A7DB5'),left:B('thin','4A7DB5'),right:B('thin','4A7DB5')} },
      kL: (clr) => ({ font:{bold:true,sz:8,color:{rgb:clr}}, fill:{fgColor:{rgb:'F8FAFC'}}, alignment:{horizontal:'center',vertical:'center'}, border:brd() }),
      kV: (clr) => ({ font:{bold:true,sz:15,color:{rgb:clr}}, fill:{fgColor:{rgb:'FFFFFF'}}, alignment:{horizontal:'center',vertical:'center'}, border:brd() }),
      re: (x={}) => ({ font:{sz:9}, fill:{fgColor:{rgb:'FFFFFF'}}, border:brd(), ...x }),
      ro: (x={}) => ({ font:{sz:9}, fill:{fgColor:{rgb:'F0F5FF'}}, border:brd(), ...x }),
      ri: (x={}) => ({ font:{sz:8,italic:true}, fill:{fgColor:{rgb:'F8F9FA'}}, border:brd(), ...x }),
      tL: { font:{bold:true,sz:10,color:{rgb:'1A3A5C'}}, fill:{fgColor:{rgb:'D6E4F5'}}, alignment:{horizontal:'right',vertical:'center',indent:1}, border:{top:B('medium','1A3A5C'),bottom:B('medium','1A3A5C'),left:B('medium','1A3A5C'),right:B('thin','D0D9E8')} },
      tV: { font:{bold:true,sz:11,color:{rgb:'047857'}}, fill:{fgColor:{rgb:'DCFCE7'}}, alignment:{horizontal:'right',vertical:'center'}, border:{top:B('medium','1A3A5C'),bottom:B('medium','1A3A5C'),left:B('thin','D0D9E8'),right:B('medium','1A3A5C')} },
      tM: { fill:{fgColor:{rgb:'D6E4F5'}}, border:{top:B('medium','1A3A5C'),bottom:B('medium','1A3A5C'),left:B('thin'),right:B('thin')} },
    }

    let R = 0

    // Header
    for (let c = 0; c <= 12; c++) sc(R, c, '', S.h1)
    sc(R, 0, 'PT. CIPTA SARANA MAKMUR', S.h1); mg(R, 0, R, 12); setRowH(R, 22); R++
    for (let c = 0; c <= 12; c++) sc(R, c, '', S.h2)
    sc(R, 0, 'LAPORAN PEMBELIAN BARANG', S.h2); mg(R, 0, R, 12); setRowH(R, 16); R++

    // Info row
    sc(R,0,'Periode',S.iL);    sc(R,1,'',S.iL);   mg(R,0,R,1)
    sc(R,2,`${params.value.date_from || '—'} s/d ${params.value.date_to || '—'}`,S.iV); sc(R,3,'',S.iV); sc(R,4,'',S.iV); mg(R,2,R,4)
    sc(R,5,'Jenis Bayar',S.iL); sc(R,6,'',S.iL);  mg(R,5,R,6)
    sc(R,7,ptLabel[params.value.payment_type],S.iV); sc(R,8,'',S.iV); sc(R,9,'',S.iV); mg(R,7,R,9)
    sc(R,10,'Dicetak',S.iL); sc(R,11,'',S.iL); mg(R,10,R,11)
    sc(R,12,`${dateStr} ${timeStr}`,S.iV); setRowH(R, 18); R++

    // KPI
    ;[['Total PO','1A3A5C'],['Nilai Total','047857'],['PO Cash','0369A1'],['PO Kredit','B45309']]
      .forEach(([lbl, clr], ci) => {
        const s = ci * 3, e = ci === 3 ? 12 : s + 2
        sc(R, s, lbl, S.kL(clr)); for(let x=s+1;x<=e;x++) sc(R,x,'',S.kL(clr)); mg(R,s,R,e)
      }); setRowH(R, 14); R++
    ;[
      [summary.value.total_po, '1A3A5C'],
      [fmtRp(summary.value.total_nilai), '047857'],
      [summary.value.total_cash + ' PO / ' + fmtRp(summary.value.nilai_cash), '0369A1'],
      [summary.value.total_kredit + ' PO / ' + fmtRp(summary.value.nilai_kredit), 'B45309'],
    ].forEach(([val, clr], ci) => {
        const s = ci * 3, e = ci === 3 ? 12 : s + 2
        sc(R, s, val, S.kV(clr)); for(let x=s+1;x<=e;x++) sc(R,x,'',S.kV(clr)); mg(R,s,R,e)
      }); setRowH(R, 28); R++

    R++ // spacer

    // Table header
    ;['#','No. PO','Vendor / Supplier','Gudang','Tgl. PO','Pembayaran','Tenor (hr)','Jatuh Tempo','Subtotal','Nilai PPN','Grand Total','Status PO','Status Bayar']
      .forEach((h, c) => sc(R, c, h, S.th)); setRowH(R, 28); R++

    orders.value.forEach((po, i) => {
      const row = i % 2 === 0 ? S.re : S.ro
      const sLbl = statusLabel(po.status)
      const ptLbl = po.payment_type === 'cash' ? 'Cash' : 'Kredit'
      const bayar = po.payment_type === 'cash' ? 'Lunas' : (['paid'].includes(invoiceStatus(po)) ? 'Lunas' : invoiceStatus(po) === 'partial' ? 'Parsial' : isOverdue(po) ? 'Jatuh Tempo' : 'Belum Bayar')

      sc(R, 0,  i+1,                           row({font:{sz:9},alignment:{horizontal:'center',vertical:'center'}}))
      sc(R, 1,  po.po_number,                   row({font:{bold:true,sz:9,color:{rgb:'1A3A5C'}},alignment:{vertical:'center'}}))
      sc(R, 2,  po.vendor_name,                 row({font:{bold:true,sz:9},alignment:{vertical:'center'}}))
      sc(R, 3,  po.warehouse?.name || '—',      row({font:{sz:9},alignment:{vertical:'center'}}))
      sc(R, 4,  po.created_at?.slice(0,10),     row({font:{sz:9},alignment:{horizontal:'center',vertical:'center'}}))
      sc(R, 5,  ptLbl,                          row({font:{bold:true,sz:9,color:{rgb: po.payment_type==='cash'?'0369A1':'B45309'}},alignment:{horizontal:'center',vertical:'center'}}))
      sc(R, 6,  po.payment_term_days || '—',    row({font:{sz:9},alignment:{horizontal:'center',vertical:'center'}}))
      sc(R, 7,  po.payment_due_date || '—',     row({font:{sz:9},alignment:{horizontal:'center',vertical:'center'}}))
      sc(R, 8,  fmtRp(po.total_amount),         row({font:{sz:9},alignment:{horizontal:'right',vertical:'center'}}))
      sc(R, 9,  po.ppn_percent > 0 ? fmtRp(po.ppn_amount) : '-', row({font:{sz:9,color:{rgb: po.ppn_percent>0 ? '0369A1' : '94A3B8'}},alignment:{horizontal:'right',vertical:'center'}}))
      sc(R, 10, fmtRp(po.grand_total),          row({font:{bold:true,sz:10},alignment:{horizontal:'right',vertical:'center'}}))
      sc(R, 11, sLbl,                           row({font:{sz:9},alignment:{horizontal:'center',vertical:'center'}}))
      sc(R, 12, bayar,                          row({font:{bold:true,sz:9,color:{rgb:bayar==='Lunas'?'15803D':bayar==='Jatuh Tempo'?'DC2626':'D97706'}},alignment:{horizontal:'center',vertical:'center'}}))
      setRowH(R, 20); R++

      // Items — indented rows, no confusion with PO row data
      ;(po.items || []).forEach((item, ii) => {
        const iStyle = {
          font:   { sz:8, color:{rgb:'374151'} },
          fill:   { fgColor:{ rgb: i%2===0 ? 'F0F5FF' : 'E8EFFA' } },
          border: brd(),
        }
        const iLabel = {
          font:   { sz:8, italic:true, color:{rgb:'6B7280'} },
          fill:   { fgColor:{ rgb: i%2===0 ? 'F0F5FF' : 'E8EFFA' } },
          border: brd(),
          alignment: { horizontal:'center', vertical:'center' },
        }
        // Col 0: kosong (bukan nomor, agar tidak membingungkan dengan nomor PO)
        sc(R, 0, '', { ...iStyle, border:{ ...brd(), left:{ style:'medium', color:{rgb:'1A3A5C'} } } })
        // Col 1: nomor item + nama barang (merge 1-3)
        sc(R, 1, `  ${ii+1}. ${item.nama_barang}`, { ...iStyle, font:{sz:8,bold:true,color:{rgb:'1a3a5c'}}, alignment:{vertical:'center'} })
        sc(R, 2, '', iStyle); sc(R, 3, '', iStyle); mg(R, 1, R, 3)
        // Col 4: satuan/unit
        sc(R, 4, item.satuan || '—', { ...iStyle, alignment:{horizontal:'center',vertical:'center'} })
        // Col 5-7: kosong
        sc(R, 5, '', iStyle); sc(R, 6, '', iStyle); sc(R, 7, '', iStyle)
        // Col 8: qty × harga
        sc(R, 8, `${item.qty} × ${fmtRp(item.harga_satuan)}`, { ...iStyle, alignment:{horizontal:'right',vertical:'center'} })
        // Col 9: kosong (PPN)
        sc(R, 9, '', iStyle)
        // Col 10: total harga item
        sc(R, 10, fmtRp(item.total_harga), { ...iStyle, font:{sz:8,bold:true,color:{rgb:'1a3a5c'}}, alignment:{horizontal:'right',vertical:'center'} })
        // Col 11-12: kosong
        sc(R, 11, '', iStyle); sc(R, 12, '', iStyle)
        setRowH(R, 16); R++
      })
    })

    // Total
    sc(R, 0, 'TOTAL NILAI PEMBELIAN', S.tL); mg(R, 0, R, 9)
    for (let c = 1; c <= 9; c++) sc(R, c, '', S.tM)
    sc(R, 10, fmtRp(summary.value.total_nilai), S.tV)
    sc(R, 11, '', S.tM); sc(R, 12, '', S.tM)

    ws['!ref'] = XLSX.utils.encode_range({ s:{r:0,c:0}, e:{r:R,c:12} })
    XLSX.utils.book_append_sheet(wb, ws, 'Laporan Pembelian')
    XLSX.writeFile(wb, `Laporan_Pembelian_${now.toISOString().slice(0,10)}.xlsx`)
    toast.success('Export Excel berhasil')
  } finally {
    exportingExcel.value = false
  }
}

// ─── Export PDF ───────────────────────────────────────────────────────────────

async function exportPdf() {
  exportingPdf.value = true
  try {
    const supplierName = params.value.supplier_id
      ? suppliers.value.find(s => s.id == params.value.supplier_id)?.name || ''
      : 'Semua Supplier'

    const q = new URLSearchParams()
    if (params.value.date_from)    q.set('date_from',    params.value.date_from)
    if (params.value.date_to)      q.set('date_to',      params.value.date_to)
    if (params.value.payment_type) q.set('payment_type', params.value.payment_type)
    if (params.value.status)       q.set('status',       params.value.status)
    if (params.value.supplier_id)  q.set('supplier_id',  params.value.supplier_id)
    q.set('supplier_name', supplierName)

    const res = await axios.get('/reports/purchase-pdf?' + q.toString(), { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a   = document.createElement('a')
    a.href     = url
    a.download = `Laporan_Pembelian_${new Date().toISOString().slice(0,10)}.pdf`
    document.body.appendChild(a); a.click()
    document.body.removeChild(a); URL.revokeObjectURL(url)
    toast.success('Export PDF berhasil')
  } catch (e) {
    toast.error('Gagal export PDF')
  } finally {
    exportingPdf.value = false
  }
}
</script>