<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Invoice Supplier</h5>
        <small class="text-muted">Pencatatan tagihan dari supplier</small>
      </div>
      <button v-if="can('manage-accounting')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Input Invoice
      </button>
    </div>

    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-4">
            <input v-model="search" class="form-control form-control-sm" placeholder="🔍 Cari no. invoice..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="statusFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Status</option>
              <option value="unpaid">Belum Bayar</option>
              <option value="partial">Bayar Sebagian</option>
              <option value="paid">Lunas</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </div>
          <div class="col-md-3">
            <select v-model="supplierFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Supplier</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. Invoice</th><th>Supplier</th><th>Ref PO</th><th>Tgl Invoice</th><th>Jatuh Tempo</th>
                <th class="text-end">Total</th><th class="text-end">Terbayar</th><th class="text-end">Sisa</th>
                <th>Status</th><th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!invoices.length"><td colspan="10" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="inv in invoices" :key="inv.id" :class="isOverdue(inv) && inv.status !== 'paid' ? 'table-danger' : ''">
                <td>
                  <div class="fw-semibold small text-primary">{{ inv.invoice_number }}</div>
                  <div class="text-muted" style="font-size:11px;">{{ inv.internal_number }}</div>
                </td>
                <td class="small">{{ inv.supplier?.name }}</td>
                <td class="small">
                  <span v-if="inv.purchase_order_id" class="badge bg-info text-dark">
                    <i class="bi bi-file-earmark-text me-1"></i>PO #{{ inv.purchase_order_id }}
                  </span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="small">{{ $formatDate(inv.invoice_date) }}</td>
                <td class="small" :class="isOverdue(inv) && inv.status !== 'paid' ? 'text-danger fw-bold' : ''">
                  {{ $formatDate(inv.due_date) }}
                  <span v-if="isOverdue(inv) && inv.status !== 'paid'" class="badge bg-danger ms-1" style="font-size:9px;">JATUH TEMPO</span>
                </td>
                <td class="text-end small fw-semibold">{{ $formatCurrency(inv.total_amount) }}</td>
                <td class="text-end small text-success">{{ $formatCurrency(inv.paid_amount) }}</td>
                <td class="text-end small fw-bold" :class="inv.remaining_amount > 0 ? 'text-danger' : 'text-success'">
                  {{ $formatCurrency(inv.remaining_amount) }}
                </td>
                <td><span :class="statusClass(inv.status)">{{ statusLabel(inv.status) }}</span></td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-info" title="Detail" @click="openDetail(inv)">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button v-if="can('manage-accounting') && inv.status !== 'paid' && inv.status !== 'cancelled'"
                      class="btn btn-xs btn-outline-success" title="Bayar" @click="openPayment(inv)">
                      <i class="bi bi-cash-coin"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center px-3 py-2" v-if="meta.last_page > 1">
          <small class="text-muted">Halaman {{ meta.page }} dari {{ meta.last_page }} ({{ meta.total }} invoice)</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
            <button class="btn btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Input Invoice -->
    <div class="modal fade" id="invoiceModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Input Invoice Supplier</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Supplier <span class="text-danger">*</span></label>
                <select v-model="form.supplier_id" class="form-select form-select-sm" @change="onSupplierChange">
                  <option value="">-- Pilih Supplier --</option>
                  <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">No. Invoice Supplier <span class="text-danger">*</span></label>
                <input v-model="form.invoice_number" class="form-control form-control-sm" placeholder="INV/2026/001" />
              </div>

              <!-- PO Dropdown — auto-pull data -->
              <div class="col-12">
                <label class="form-label fw-semibold small">
                  <i class="bi bi-file-earmark-text me-1 text-info"></i>
                  Referensi Purchase Order
                  <span class="text-muted fw-normal ms-1">(opsional — pilih PO untuk tarik data otomatis)</span>
                </label>
                <div class="input-group input-group-sm">
                  <select v-model="form.purchase_order_id" class="form-select form-select-sm"
                    :disabled="!form.supplier_id || loadingPO" @change="onPOChange">
                    <option value="">-- Pilih PO (opsional) --</option>
                    <option v-for="po in availablePOs" :key="po.id" :value="po.id"
                      :disabled="po.status === 'cancelled' || po.fully_invoiced">
                      {{ po.po_number }} — {{ po.vendor_name }} — {{ $formatCurrency(po.grand_total) }}
                      [{{ po.status }}]{{ po.fully_invoiced ? ' — SUDAH PENUH DIINVOICE' : (po.invoiced_amount > 0 ? ` — terpakai Rp ${Number(po.invoiced_amount).toLocaleString('id')}` : '') }}
                    </option>
                  </select>
                  <span v-if="loadingPO" class="input-group-text bg-white">
                    <div class="csm-spinner" style="width:14px;height:14px;"></div>
                  </span>
                </div>
                <div v-if="selectedPO" class="mt-2 p-2 rounded border-start border-3 bg-light"
                  :class="selectedPO.fully_invoiced ? 'border-danger' : 'border-info'">
                  <small class="fw-semibold" :class="selectedPO.fully_invoiced ? 'text-danger' : 'text-info'">
                    <i class="bi me-1" :class="selectedPO.fully_invoiced ? 'bi-x-circle' : 'bi-check-circle'"></i>
                    <span v-if="selectedPO.fully_invoiced">PO ini sudah sepenuhnya diinvoice — tidak bisa digunakan</span>
                    <span v-else>Data PO <strong>{{ selectedPO.po_number }}</strong> ditarik otomatis</span>
                  </small>
                  <div class="small text-muted mt-1">
                    Grand Total PO: <strong class="text-dark">{{ $formatCurrency(selectedPO.grand_total) }}</strong>
                    <span v-if="selectedPO.invoiced_amount > 0">
                      &nbsp;|&nbsp; Sudah diinvoice: <strong class="text-warning">{{ $formatCurrency(selectedPO.invoiced_amount) }}</strong>
                      &nbsp;|&nbsp; Sisa bisa diinvoice: <strong :class="selectedPO.fully_invoiced ? 'text-danger' : 'text-success'">{{ $formatCurrency(selectedPO.remaining_invoiceable) }}</strong>
                    </span>
                  </div>
                </div>
                <small v-if="!form.supplier_id" class="text-muted">
                  <i class="bi bi-info-circle me-1"></i>Pilih supplier dulu untuk melihat daftar PO
                </small>
                <small v-else-if="!loadingPO && availablePOs.length === 0 && form.supplier_id" class="text-warning">
                  <i class="bi bi-exclamation-triangle me-1"></i>Tidak ada PO untuk supplier ini — pastikan nama vendor di PO sesuai dengan nama supplier
                </small>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold small">Tgl Invoice <span class="text-danger">*</span></label>
                <input v-model="form.invoice_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Jatuh Tempo <span class="text-danger">*</span></label>
                <input v-model="form.due_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4"></div>

              <div class="col-md-4">
                <label class="form-label fw-semibold small">Subtotal <span class="text-danger">*</span></label>
                <input v-model.number="form.subtotal" type="number" min="0" class="form-control form-control-sm" @input="calcTotal" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">PPN</label>
                <input v-model.number="form.tax_amount" type="number" min="0" class="form-control form-control-sm" @input="calcTotal" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Total</label>
                <input :value="$formatCurrency(form.total_amount)" class="form-control form-control-sm bg-light" readonly />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="form.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="save" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>
              <i class="bi bi-save me-1" v-else></i>Simpan Invoice
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedInvoice">
          <div class="modal-header">
            <h5 class="modal-title">Detail Invoice — {{ selectedInvoice.invoice_number }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6"><div class="small text-muted">Supplier</div><div class="fw-bold">{{ selectedInvoice.supplier?.name }}</div></div>
              <div class="col-md-6"><div class="small text-muted">No. Internal</div><div class="fw-bold font-monospace">{{ selectedInvoice.internal_number }}</div></div>
              <div class="col-md-4"><div class="small text-muted">Tgl Invoice</div><div>{{ $formatDate(selectedInvoice.invoice_date) }}</div></div>
              <div class="col-md-4"><div class="small text-muted">Jatuh Tempo</div><div :class="isOverdue(selectedInvoice) && selectedInvoice.status !== 'paid' ? 'text-danger fw-bold' : ''">{{ $formatDate(selectedInvoice.due_date) }}</div></div>
              <div class="col-md-4"><div class="small text-muted">Status</div><span :class="statusClass(selectedInvoice.status)">{{ statusLabel(selectedInvoice.status) }}</span></div>
              <div class="col-12"><hr class="my-1"></div>
              <div class="col-md-4 text-end"><div class="small text-muted">Subtotal</div><div>{{ $formatCurrency(selectedInvoice.subtotal) }}</div></div>
              <div class="col-md-4 text-end"><div class="small text-muted">PPN</div><div>{{ $formatCurrency(selectedInvoice.tax_amount) }}</div></div>
              <div class="col-md-4 text-end"><div class="small text-muted">Total</div><div class="fw-bold fs-6">{{ $formatCurrency(selectedInvoice.total_amount) }}</div></div>
              <div class="col-md-6 text-end"><div class="small text-muted">Terbayar</div><div class="text-success fw-bold">{{ $formatCurrency(selectedInvoice.paid_amount) }}</div></div>
              <div class="col-md-6 text-end"><div class="small text-muted">Sisa Tagihan</div><div class="fw-bold" :class="selectedInvoice.remaining_amount > 0 ? 'text-danger' : 'text-success'">{{ $formatCurrency(selectedInvoice.remaining_amount) }}</div></div>
              <div class="col-12" v-if="selectedInvoice.payments?.length">
                <div class="small fw-semibold text-muted mb-2">Riwayat Pembayaran</div>
                <table class="table table-sm table-bordered mb-0">
                  <thead class="table-light"><tr><th>Tgl</th><th>Metode</th><th>Ref</th><th class="text-end">Jumlah</th><th>Status</th></tr></thead>
                  <tbody>
                    <tr v-for="p in selectedInvoice.payments" :key="p.id">
                      <td class="small">{{ $formatDate(p.payment_date) }}</td>
                      <td class="small">{{ p.payment_method }}</td>
                      <td class="small font-monospace">{{ p.reference_number || '—' }}</td>
                      <td class="text-end small">{{ $formatCurrency(p.amount) }}</td>
                      <td><span class="badge" :class="p.status === 'approved' ? 'bg-success' : p.status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary'">{{ p.status }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="modal-footer"><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button></div>
        </div>
      </div>
    </div>

    <!-- Modal Pembayaran -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content" v-if="selectedInvoice">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Catat Pembayaran</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info small py-2">
              <strong>{{ selectedInvoice.invoice_number }}</strong> — {{ selectedInvoice.supplier?.name }}<br>
              Sisa tagihan: <strong class="text-danger">{{ $formatCurrency(selectedInvoice.remaining_amount) }}</strong>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jumlah Bayar <span class="text-danger">*</span></label>
                <input v-model.number="payForm.amount" type="number" min="1" :max="selectedInvoice.remaining_amount" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tgl Pembayaran <span class="text-danger">*</span></label>
                <input v-model="payForm.payment_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Metode <span class="text-danger">*</span></label>
                <select v-model="payForm.payment_method" class="form-select form-select-sm">
                  <option value="transfer">Transfer Bank</option>
                  <option value="cash">Tunai</option>
                  <option value="giro">Giro</option>
                  <option value="cek">Cek</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Kas / Rekening</label>
                <select v-model="payForm.main_cash_account_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Kas --</option>
                  <option v-for="c in cashAccounts" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">No. Referensi</label>
                <input v-model="payForm.reference_number" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="payForm.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <div v-if="selectedInvoice?.status === 'paid'" class="alert alert-success py-1 px-3 mb-0 me-auto small">
              <i class="bi bi-check-circle me-1"></i>Invoice ini sudah <strong>LUNAS</strong> — tidak bisa diproses lagi
            </div>
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-success btn-sm" @click="savePayment"
              :disabled="saving || selectedInvoice?.status === 'paid' || selectedInvoice?.remaining_amount <= 0">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>
              <i class="bi bi-cash-coin me-1" v-else></i>Simpan Pembayaran
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { useRealtime } from '@/composables/useRealtime'

const auth = useAuthStore(); const toast = useToast()
const { listenInvoiceSupplier, stopInvoiceSupplier } = useRealtime()
const can = (p) => auth.hasPermission(p)
const invoices = ref([]); const suppliers = ref([]); const cashAccounts = ref([])
const loading = ref(false); const saving = ref(false); const loadingPO = ref(false)
const search = ref(''); const statusFilter = ref(''); const supplierFilter = ref('')
const meta = ref({ total: 0, page: 1, last_page: 1 })
const selectedInvoice = ref(null)
const availablePOs = ref([])
const selectedPO = ref(null)

const form = ref({ supplier_id: '', invoice_number: '', purchase_order_id: '', subtotal: 0, tax_amount: 0, total_amount: 0, invoice_date: '', due_date: '', notes: '' })
const payForm = ref({ amount: 0, payment_date: '', payment_method: 'transfer', main_cash_account_id: '', reference_number: '', notes: '' })
let modal = null; let paymentModal = null; let detailModal = null; let timer = null

onMounted(async () => {
  modal        = new Modal(document.getElementById('invoiceModal'))
  paymentModal = new Modal(document.getElementById('paymentModal'))
  detailModal  = new Modal(document.getElementById('detailModal'))
  const [sRes, cRes] = await Promise.all([axios.get('/suppliers'), axios.get('/main-cash/accounts')])
  suppliers.value = sRes.data.data; cashAccounts.value = cRes.data.data
  load()
  listenInvoiceSupplier(() => load())
})

onUnmounted(() => stopInvoiceSupplier())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/supplier-invoices', { params: { search: search.value, status: statusFilter.value, supplier_id: supplierFilter.value, page: meta.value.page } })
    invoices.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}
function debouncedLoad() { clearTimeout(timer); timer = setTimeout(load, 400) }
function changePage(p) { meta.value.page = p; load() }
function calcTotal() { form.value.total_amount = (form.value.subtotal || 0) + (form.value.tax_amount || 0) }
function isOverdue(inv) { return new Date(inv.due_date) < new Date() }
function statusClass(s) { return { unpaid:'badge bg-danger', partial:'badge bg-warning text-dark', paid:'badge bg-success', cancelled:'badge bg-secondary' }[s] || 'badge bg-secondary' }
function statusLabel(s) { return { unpaid:'Belum Bayar', partial:'Bayar Sebagian', paid:'Lunas', cancelled:'Dibatalkan' }[s] || s }

async function onSupplierChange() {
  form.value.purchase_order_id = ''; selectedPO.value = null; availablePOs.value = []
  if (!form.value.supplier_id) return
  loadingPO.value = true
  try {
    const res = await axios.get(`/purchase-orders-by-supplier/${form.value.supplier_id}`)
    availablePOs.value = res.data.data
  } catch(e) { console.error(e) } finally { loadingPO.value = false }
}

async function onPOChange() {
  if (!form.value.purchase_order_id) {
    selectedPO.value = null
    form.value.subtotal = 0; form.value.tax_amount = 0; form.value.total_amount = 0
    return
  }
  try {
    const res = await axios.get(`/purchase-orders/${form.value.purchase_order_id}/for-invoice`)
    const po = res.data.data
    selectedPO.value = po
    form.value.subtotal     = parseFloat(po.subtotal)    || 0
    form.value.tax_amount   = parseFloat(po.ppn_amount)  || 0
    form.value.total_amount = parseFloat(po.grand_total) || 0
  } catch(e) { toast.error('Gagal mengambil data PO') }
}

function openModal() {
  form.value = { supplier_id:'', invoice_number:'', purchase_order_id:'', subtotal:0, tax_amount:0, total_amount:0, invoice_date: new Date().toISOString().split('T')[0], due_date:'', notes:'' }
  availablePOs.value = []; selectedPO.value = null; modal.show()
}
function openDetail(inv) {
  axios.get(`/supplier-invoices/${inv.id}`).then(r => { selectedInvoice.value = r.data.data; detailModal.show() })
}
function openPayment(inv) {
  selectedInvoice.value = inv
  payForm.value = { amount: inv.remaining_amount, payment_date: new Date().toISOString().split('T')[0], payment_method:'transfer', main_cash_account_id:'', reference_number:'', notes:'' }
  paymentModal.show()
}

async function save() {
  if (!form.value.supplier_id || !form.value.invoice_number || !form.value.invoice_date || !form.value.due_date) {
    toast.warning('Lengkapi data wajib: Supplier, No. Invoice, Tgl Invoice, Jatuh Tempo'); return
  }
  saving.value = true
  try { await axios.post('/supplier-invoices', form.value); toast.success('Invoice berhasil disimpan'); modal.hide(); load() }
  catch(e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') } finally { saving.value = false }
}
async function savePayment() {
  if (selectedInvoice.value.status === 'paid')
    return toast.error('Invoice ini sudah lunas')
  if (selectedInvoice.value.remaining_amount <= 0)
    return toast.error('Sisa tagihan sudah nol')
  if (payForm.value.amount > selectedInvoice.value.remaining_amount)
    return toast.error(`Jumlah melebihi sisa tagihan (${$formatCurrency(selectedInvoice.value.remaining_amount)})`)
  saving.value = true
  try {
    await axios.post('/supplier-payments', { ...payForm.value, supplier_id: selectedInvoice.value.supplier_id, supplier_invoice_id: selectedInvoice.value.id })
    toast.success('Pembayaran berhasil dicatat'); paymentModal.hide(); load()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal menyimpan pembayaran') } finally { saving.value = false }
}
</script>