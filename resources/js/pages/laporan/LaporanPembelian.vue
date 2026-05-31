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
        <button class="btn btn-danger btn-sm" @click="onClickExportPdf" :disabled="exportingPdf">
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
  <!-- Modal Pilih Penandatangan -->
  <SignerPickerModal
    v-model="showSignerModal"
    :slots="slots"
    :is-finalized="isFinalized"
    :finalized-at="finalizedAt"
    :loading="signerLoading"
    :action-loading="signerActionLoading"
    @add-slot="handleAddSlot"
    @finalize="handleFinalize"
    @print="handlePrint"
  />
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'
import { useSignerPicker } from '@/composables/useSignerPicker'
import SignerPickerModal from '@/components/SignerPickerModal.vue'
import dayjs from 'dayjs'

const toast = useToast()

// ── Signer Picker ────────────────────────────────────────────────────
const {
  showSignerModal, slots, isFinalized, finalizedAt,
  loading: signerLoading, actionLoading: signerActionLoading,
  openSignerPicker, addMySlot, finalizeDoc, closeModal,
} = useSignerPicker()

// ── State ────────────────────────────────────────────────────────────
const suppliers      = ref([])
const orders         = ref([])
const summary        = ref({ total_po: 0, total_nilai: 0, total_cash: 0, total_kredit: 0, nilai_cash: 0, nilai_kredit: 0 })
const loading        = ref(false)
const loaded         = ref(false)
const exportingExcel = ref(false)
const exportingPdf   = ref(false)

const today = dayjs().format('YYYY-MM-DD')

const params = ref({
  date_from:    dayjs().startOf('month').format('YYYY-MM-DD'),
  date_to:      dayjs().format('YYYY-MM-DD'),
  payment_type: '',
  status:       '',
  supplier_id:  '',
})

// ── Lifecycle ────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const r = await axios.get('/suppliers', { params: { per_page: 999 } })
    suppliers.value = r.data.data || r.data || []
  } catch {
    suppliers.value = []
  }
})

// ── Load Data ────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/purchase', {
      params: {
        date_from:    params.value.date_from    || undefined,
        date_to:      params.value.date_to      || undefined,
        payment_type: params.value.payment_type || undefined,
        status:       params.value.status       || undefined,
        supplier_id:  params.value.supplier_id  || undefined,
      }
    })
    orders.value  = (r.data.data || []).map(po => ({ ...po, _expanded: false }))
    summary.value = r.data.summary || { total_po: 0, total_nilai: 0, total_cash: 0, total_kredit: 0, nilai_cash: 0, nilai_kredit: 0 }
    loaded.value  = true
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal memuat laporan pembelian')
  } finally {
    loading.value = false
  }
}

function resetFilter() {
  params.value = {
    date_from:    dayjs().startOf('month').format('YYYY-MM-DD'),
    date_to:      dayjs().format('YYYY-MM-DD'),
    payment_type: '',
    status:       '',
    supplier_id:  '',
  }
  orders.value  = []
  summary.value = { total_po: 0, total_nilai: 0, total_cash: 0, total_kredit: 0, nilai_cash: 0, nilai_kredit: 0 }
  loaded.value  = false
}

// ── Status Helpers ────────────────────────────────────────────────────
const statusLabel = (s) => ({
  draft:            'Draft',
  sent_to_vendor:   'Dikirim ke Vendor',
  partial_received: 'Diterima Sebagian',
  completed:        'Selesai',
  cancelled:        'Dibatalkan',
}[s] || s)

const statusClass = (s) => ({
  draft:            'bg-secondary',
  sent_to_vendor:   'bg-info text-dark',
  partial_received: 'bg-warning text-dark',
  completed:        'bg-success',
  cancelled:        'bg-danger',
}[s] || 'bg-secondary')

// ── Due Date Helpers ──────────────────────────────────────────────────
function isOverdue(po) {
  if (po.payment_type !== 'kredit' || !po.payment_due_date) return false
  return po.payment_due_date < today && po.status !== 'cancelled'
}

function overdayCount(po) {
  if (!po.payment_due_date) return 0
  return dayjs().diff(dayjs(po.payment_due_date), 'day')
}

// ── Invoice Status ────────────────────────────────────────────────────
function invoiceStatus(po) {
  const invs = po.supplier_invoices || []
  if (!invs.length) return 'unpaid'
  if (invs.every(i => i.status === 'paid')) return 'paid'
  if (invs.some(i => i.status === 'paid' || i.status === 'partial')) return 'partial'
  if (invs.some(i => i.status !== 'paid' && i.due_date < today)) return 'overdue'
  return 'unpaid'
}

// ── Export Excel (CSV) ────────────────────────────────────────────────
async function exportExcel() {
  if (!orders.value.length) { toast.warning('Tidak ada data untuk diekspor'); return }
  exportingExcel.value = true
  try {
    const headers = [
      'No', 'No. PO', 'Vendor/Supplier', 'Gudang', 'Tgl. PO',
      'Jenis Pembayaran', 'Tenor (Hari)', 'Jatuh Tempo',
      'Subtotal', 'PPN %', 'Nilai PPN', 'Grand Total',
      'Status PO', 'Status Bayar',
      'No. Item', 'Part Number', 'Nama Barang', 'Qty', 'Satuan', 'Harga Satuan', 'Total Item',
    ]

    const rows = []
    orders.value.forEach((po, i) => {
      const statusBayar = po.payment_type === 'cash' ? 'Lunas' : invoiceStatus(po)
      const baseRow = [
        i + 1,
        po.po_number,
        po.vendor_name || '',
        po.warehouse?.name || '',
        po.created_at ? po.created_at.slice(0, 10) : '',
        po.payment_type === 'cash' ? 'Cash' : 'Kredit',
        po.payment_term_days || '',
        po.payment_due_date || '',
        po.total_amount,
        po.ppn_percent || 0,
        po.ppn_amount  || 0,
        po.grand_total,
        statusLabel(po.status),
        statusBayar,
      ]

      if (po.items?.length) {
        po.items.forEach((item, ii) => {
          rows.push([
            ...baseRow,
            ii + 1,
            item.part_number || '',
            item.nama_barang || '',
            item.qty,
            item.satuan || '',
            item.harga_satuan,
            item.total_harga,
          ])
        })
      } else {
        rows.push([...baseRow, '', '', '', '', '', '', ''])
      }
    })

    const csv = [headers, ...rows]
      .map(r => r.map(c => `"${String(c ?? '').replace(/"/g, '""')}"`).join(','))
      .join('\n')

    const from = params.value.date_from || 'awal'
    const to   = params.value.date_to   || 'akhir'
    const filename = `laporan_pembelian_${from}_sd_${to}.csv`
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }))
    a.download = filename
    a.click()
    toast.success('Export Excel berhasil')
  } catch {
    toast.error('Gagal export Excel')
  } finally {
    exportingExcel.value = false
  }
}

// ── Export PDF ────────────────────────────────────────────────────────
async function onClickExportPdf() {
  if (!orders.value.length) { toast.warning('Tidak ada data untuk diekspor'); return }
  openSignerPicker('report_stock', Date.now(), exportPdf)
}

async function exportPdf(resolvedSlots = []) {
  exportingPdf.value = true
  try {
    const response = await axios.get('/reports/purchase-pdf', {
      params: {
        date_from:    params.value.date_from    || undefined,
        date_to:      params.value.date_to      || undefined,
        payment_type: params.value.payment_type || undefined,
        status:       params.value.status       || undefined,
        supplier_id:  params.value.supplier_id  || undefined,
        signer_ids:   undefined,
      },
      responseType: 'blob',
    })
    const from = params.value.date_from || 'awal'
    const to   = params.value.date_to   || 'akhir'
    const filename = `laporan_pembelian_${from}_sd_${to}.pdf`
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    a.download = filename
    a.click()
    toast.success('Export PDF berhasil')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal export PDF')
  } finally {
    exportingPdf.value = false
  }
}

// ── SignerPicker handlers ──────────────────────────────────────────────────
async function handleAddSlot(slot) {
  const { success, message } = await addMySlot(slot)
  if (!success) toast.error(message)
  else toast.success(message)
}

async function handleFinalize() {
  const { success, message } = await finalizeDoc()
  if (!success) toast.error(message)
  else toast.success(message)
}

function handlePrint() {
  closeModal()
  exportPdf(slots.value)
}

</script>

<style scoped>
.kpi-card  { border-radius: 10px; padding: 14px 16px; }
.kpi-primary { background: linear-gradient(135deg,#1a3a5c,#2563eb); color:#fff; }
.kpi-success { background: linear-gradient(135deg,#065f46,#10b981); color:#fff; }
.kpi-label { font-size: 0.72rem; opacity: .85; margin-bottom: 2px; }
.kpi-value { font-size: 1.5rem; font-weight: 800; line-height: 1.1; }
.kpi-icon  { opacity: .6; }
</style>