<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Pembayaran Supplier</h5>
        <small class="text-muted">Catat dan kelola pembayaran tagihan supplier</small>
      </div>
      <button class="btn btn-success btn-sm" @click="openPayFromInvoice">
        <i class="bi bi-cash-coin me-1"></i>Catat Pembayaran Baru
      </button>
    </div>

    <!-- Ringkasan -->
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="csm-card text-center">
          <div class="csm-card-body py-3">
            <div class="text-muted small">Total Pending</div>
            <div class="fw-bold fs-5 text-warning">{{ pendingCount }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="csm-card text-center">
          <div class="csm-card-body py-3">
            <div class="text-muted small">Disetujui Bulan Ini</div>
            <div class="fw-bold fs-5 text-success">{{ $formatCurrency(approvedThisMonth) }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="csm-card text-center">
          <div class="csm-card-body py-3">
            <div class="text-muted small">Total Hutang Supplier</div>
            <div class="fw-bold fs-5 text-danger">{{ $formatCurrency(totalOutstanding) }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="csm-card text-center">
          <div class="csm-card-body py-3">
            <div class="text-muted small">Pembayaran Hari Ini</div>
            <div class="fw-bold fs-5 text-primary">{{ $formatCurrency(paidToday) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Invoice Belum Lunas -->
    <div class="csm-card mb-3">
      <div class="csm-card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 text-danger">
          <i class="bi bi-exclamation-circle me-2"></i>
          Invoice Belum Lunas
          <span v-if="unpaidInvoices.length" class="badge bg-danger ms-1">{{ unpaidInvoices.length }}</span>
        </h6>
        <div class="d-flex gap-2">
          <button class="btn btn-xs btn-warning" @click="generateMissingInvoices" :disabled="generating">
            <span v-if="generating" class="csm-spinner me-1"></span>
            <i class="bi bi-magic me-1" v-else></i>Generate Invoice
          </button>
          <button class="btn btn-xs btn-outline-secondary" @click="loadUnpaidInvoices" :disabled="loadingInvoices">
            <span v-if="loadingInvoices" class="csm-spinner me-1"></span>
            <i class="bi bi-arrow-clockwise" v-else></i> Refresh
          </button>
        </div>
      </div>
      <div class="csm-card-body p-0">
        <!-- Generate result -->
        <div v-if="generateResult" class="alert mx-3 mt-3 small"
          :class="generateResult.count > 0 ? 'alert-success' : (generateResult.errors?.length ? 'alert-danger' : 'alert-info')">
          <i class="bi bi-info-circle me-1"></i>{{ generateResult.message }}
          <div v-if="generateResult.errors?.length" class="mt-1">
            <small v-for="e in generateResult.errors" :key="e" class="d-block text-danger">• {{ e }}</small>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loadingInvoices" class="p-4 text-center text-muted">
          <div class="csm-spinner mb-2"></div>
          <small>Memuat invoice...</small>
        </div>

        <!-- Error -->
        <div v-else-if="invoiceLoadError" class="p-3">
          <div class="alert alert-danger small mb-2">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ invoiceLoadError }}
          </div>
          <div class="alert alert-info small mb-0">
            <strong>Kemungkinan penyebab:</strong> Akun Anda tidak memiliki permission <code>view-accounting</code>.
            Hubungi administrator untuk mengaktifkan akses.
          </div>
        </div>

        <!-- Kosong -->
        <div v-else-if="!unpaidInvoices.length" class="p-4 text-center">
          <i class="bi bi-check-circle text-success fs-3 mb-2 d-block"></i>
          <div class="fw-semibold text-success mb-1">Tidak ada invoice yang belum lunas</div>
          <small class="text-muted d-block mb-3">
            Jika ada PO Kredit yang statusnya "Selesai" tapi invoice belum muncul di sini,
            klik tombol di bawah untuk generate invoice secara manual.
          </small>
          <button class="btn btn-warning btn-sm" @click="generateMissingInvoices" :disabled="generating">
            <span v-if="generating" class="csm-spinner me-1"></span>
            <i class="bi bi-magic me-1" v-else></i>
            Generate Invoice dari PO Kredit Selesai
          </button>
          <div v-if="generateResult" class="alert mt-2 text-start small"
            :class="generateResult.count > 0 ? 'alert-success' : 'alert-info'">
            {{ generateResult.message }}
          </div>
        </div>

        <!-- Ada data -->
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. Invoice</th>
                <th>Supplier</th>
                <th>No. PO</th>
                <th class="text-end">Total</th>
                <th class="text-end">Terbayar</th>
                <th class="text-end">Sisa</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="inv in unpaidInvoices" :key="inv.id"
                :class="inv.is_overdue ? 'table-danger' : ''">
                <td>
                  <code class="small text-muted d-block" style="font-size:10px;">{{ inv.internal_number }}</code>
                  <span v-if="inv.invoice_number" class="small text-primary fw-semibold">{{ inv.invoice_number }}</span>
                  <span v-else class="badge bg-warning text-dark" style="font-size:9px;">Belum ada no. supplier</span>
                </td>
                <td class="small fw-semibold">{{ inv.supplier?.name }}</td>
                <td><code class="small text-muted">{{ inv.purchase_order?.po_number || '-' }}</code></td>
                <td class="text-end small">{{ $formatCurrency(inv.total_amount) }}</td>
                <td class="text-end small text-success">{{ $formatCurrency(inv.paid_amount) }}</td>
                <td class="text-end fw-bold" :class="inv.is_overdue ? 'text-danger' : 'text-warning'">
                  {{ $formatCurrency(inv.remaining_amount) }}
                </td>
                <td class="small" :class="inv.is_overdue ? 'text-danger fw-semibold' : 'text-muted'">
                  {{ inv.due_date ? $formatDate(inv.due_date) : '-' }}
                  <span v-if="inv.is_overdue" class="badge bg-danger ms-1" style="font-size:.6rem;">Lewat</span>
                </td>
                <td>
                  <span class="badge" :class="inv.status === 'partial' ? 'bg-warning text-dark' : 'bg-secondary'">
                    {{ inv.status === 'partial' ? 'Sebagian' : 'Belum Bayar' }}
                  </span>
                </td>
                <td>
                  <button class="btn btn-success btn-xs" @click="openPayForInvoice(inv)"
                    v-if="can('create-supplier-payment')">
                    <i class="bi bi-cash-coin me-1"></i>Bayar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Filter riwayat -->
    <div class="csm-card mb-3">
      <div class="csm-card-header"><h6 class="mb-0">Riwayat Pembayaran</h6></div>
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <select v-model="supplierFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Supplier</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="statusFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Disetujui</option>
              <option value="rejected">Ditolak</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel riwayat -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. Pembayaran</th>
                <th>Supplier</th>
                <th>No. Invoice</th>
                <th>Tgl Bayar</th>
                <th>Metode</th>
                <th>No. Referensi</th>
                <th class="text-end">Jumlah</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!payments.length">
                <td colspan="9" class="text-center text-muted py-4">Tidak ada data pembayaran</td>
              </tr>
              <tr v-for="p in payments" :key="p.id">
                <td><code class="small text-primary">{{ p.payment_number }}</code></td>
                <td class="small fw-semibold">{{ p.supplier?.name }}</td>
                <td>
                  <code class="small text-muted d-block" style="font-size:10px;">{{ p.invoice?.internal_number }}</code>
                  <span v-if="p.invoice?.invoice_number" class="small text-primary">{{ p.invoice.invoice_number }}</span>
                  <span v-else class="text-muted small">—</span>
                </td>
                <td class="small">{{ $formatDate(p.payment_date) }}</td>
                <td>
                  <span :class="methodClass(p.payment_method)">
                    {{ methodLabel(p.payment_method) }}
                  </span>
                </td>
                <td class="small text-muted">{{ p.reference_number || '-' }}</td>
                <td class="text-end fw-bold">{{ $formatCurrency(p.amount) }}</td>
                <td><span :class="statusClass(p.status)">{{ statusLabel(p.status) }}</span></td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-primary" @click="openDetail(p)">Detail</button>
                    <button v-if="p.status === 'pending' && can('approve-supplier-payment')"
                      class="btn btn-xs btn-success" @click="approve(p)">Setujui</button>
                    <button v-if="p.status === 'pending' && can('approve-supplier-payment')"
                      class="btn btn-xs btn-danger" @click="reject(p)">Tolak</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="d-flex justify-content-center py-3 gap-2">
          <button class="btn btn-sm btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
          <span class="small text-muted align-self-center">{{ meta.page }} / {{ meta.last_page }}</span>
          <button class="btn btn-sm btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
        </div>
      </div>
    </div>

    <!-- ===== Modal Catat Pembayaran ===== -->
    <div class="modal fade" id="payModalPS" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-cash-coin me-2 text-success"></i>Catat Pembayaran</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Pilih Invoice (jika dibuka tanpa invoice terpilih) -->
            <div class="mb-3" v-if="!payForm.supplier_invoice_id || !selectedInvForPay">
              <label class="form-label fw-semibold small">Pilih Invoice <span class="text-danger">*</span></label>
              <select v-model="payForm.supplier_invoice_id" class="form-select form-select-sm"
                @change="onSelectInvoice">
                <option value="">-- Pilih Invoice --</option>
                <option v-for="inv in unpaidInvoices" :key="inv.id" :value="inv.id">
                  {{ inv.invoice_number || inv.internal_number }} — {{ inv.supplier?.name }} — Sisa {{ $formatCurrency(inv.remaining_amount) }}
                </option>
              </select>
            </div>

            <!-- Info invoice terpilih -->
            <div v-if="selectedInvForPay" class="alert alert-info small py-2 mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <code class="text-muted me-1" style="font-size:10px;">{{ selectedInvForPay.internal_number }}</code>
                  <strong v-if="selectedInvForPay.invoice_number">{{ selectedInvForPay.invoice_number }}</strong>
                  <span v-else class="badge bg-warning text-dark me-1" style="font-size:9px;">Belum ada no. supplier</span>
                  — {{ selectedInvForPay.supplier?.name }}
                  <span v-if="selectedInvForPay.purchase_order?.po_number" class="ms-1 text-muted">
                    ({{ selectedInvForPay.purchase_order.po_number }})
                  </span>
                </div>
                <button type="button" class="btn btn-xs btn-outline-secondary" @click="clearSelectedInv">Ganti</button>
              </div>
              <div class="mt-1">
                Sisa tagihan: <strong class="text-danger">{{ $formatCurrency(selectedInvForPay.remaining_amount) }}</strong>
              </div>
            </div>

            <!-- Field no. invoice supplier — muncul hanya jika invoice belum punya nomor dari supplier -->
            <div v-if="selectedInvForPay && !selectedInvForPay.invoice_number" class="mb-3">
              <label class="form-label fw-semibold small">
                <i class="bi bi-receipt me-1 text-warning"></i>
                No. Invoice dari Supplier
                <span class="badge bg-warning text-dark ms-1" style="font-size:9px;">Belum diisi</span>
              </label>
              <input
                v-model="payForm.invoice_number_supplier"
                class="form-control form-control-sm"
                placeholder="Cth: INV/2026/001 — isi jika sudah terima invoice fisik (opsional)"
              />
              <div class="form-text">Nomor tertera pada dokumen invoice dari supplier. Bisa dikosongkan dan diisi nanti.</div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jumlah Bayar <span class="text-danger">*</span></label>
                <input v-model.number="payForm.amount" type="number" min="1"
                  :max="selectedInvForPay?.remaining_amount"
                  class="form-control form-control-sm" placeholder="0" />
                <div v-if="selectedInvForPay" class="mt-1">
                  <button type="button" class="btn btn-xs btn-outline-success"
                    @click="payForm.amount = selectedInvForPay.remaining_amount">
                    Lunaskan Semua ({{ $formatCurrency(selectedInvForPay.remaining_amount) }})
                  </button>
                </div>
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
                <input v-model="payForm.reference_number" class="form-control form-control-sm"
                  placeholder="No. transfer / cek / giro..." />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="payForm.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-success btn-sm" @click="savePayment" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-cash-coin me-1" v-else></i>Simpan Pembayaran
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail Pembayaran -->
    <div class="modal fade" id="detailPaymentModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content" v-if="selectedPayment">
          <div class="modal-header">
            <h5 class="modal-title">Detail Pembayaran</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <table class="table table-sm">
              <tr><td class="text-muted">No. Pembayaran</td><td><code>{{ selectedPayment.payment_number }}</code></td></tr>
              <tr><td class="text-muted">Supplier</td><td>{{ selectedPayment.supplier?.name }}</td></tr>
              <tr>
                <td class="text-muted">Invoice</td>
                <td>
                  <code class="text-muted d-block" style="font-size:10px;">{{ selectedPayment.invoice?.internal_number }}</code>
                  <span v-if="selectedPayment.invoice?.invoice_number" class="fw-semibold">{{ selectedPayment.invoice.invoice_number }}</span>
                  <span v-else class="badge bg-warning text-dark" style="font-size:9px;">Belum ada no. supplier</span>
                </td>
              </tr>
              <tr><td class="text-muted">Tgl Bayar</td><td>{{ $formatDate(selectedPayment.payment_date) }}</td></tr>
              <tr><td class="text-muted">Metode</td><td>{{ methodLabel(selectedPayment.payment_method) }}</td></tr>
              <tr><td class="text-muted">No. Referensi</td><td>{{ selectedPayment.reference_number || '-' }}</td></tr>
              <tr><td class="text-muted">Jumlah</td><td class="fw-bold text-success">{{ $formatCurrency(selectedPayment.amount) }}</td></tr>
              <tr><td class="text-muted">Status</td><td><span :class="statusClass(selectedPayment.status)">{{ statusLabel(selectedPayment.status) }}</span></td></tr>
              <tr v-if="selectedPayment.notes"><td class="text-muted">Catatan</td><td>{{ selectedPayment.notes }}</td></tr>
            </table>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button v-if="selectedPayment.status === 'pending' && can('approve-supplier-payment')"
              class="btn btn-success btn-sm" @click="approve(selectedPayment); detailModal.hide()">Setujui</button>
            <button v-if="selectedPayment.status === 'pending' && can('approve-supplier-payment')"
              class="btn btn-danger btn-sm" @click="reject(selectedPayment); detailModal.hide()">Tolak</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { useRealtime } from '@/composables/useRealtime'

const auth = useAuthStore(); const toast = useToast()
const { listenPembayaranSupplier, stopPembayaranSupplier } = useRealtime()
const can = (p) => auth.hasPermission(p)

const payments       = ref([])
const suppliers      = ref([])
const cashAccounts   = ref([])
const unpaidInvoices = ref([])
const loading        = ref(false)
const saving         = ref(false)
const supplierFilter = ref('')
const statusFilter   = ref('')
const meta           = ref({ total: 0, page: 1, last_page: 1 })
const selectedPayment   = ref(null)
const selectedInvForPay = ref(null)

const payForm = ref({
  supplier_invoice_id: '', supplier_id: '',
  amount: 0, payment_date: new Date().toISOString().split('T')[0],
  payment_method: 'transfer', main_cash_account_id: '',
  reference_number: '', notes: '',
  invoice_number_supplier: '',   // opsional — no. invoice dari supplier jika belum diisi sebelumnya
})

let detailModal = null; let payModal = null

// Summary
const pendingCount      = computed(() => payments.value.filter(p => p.status === 'pending').length)
const approvedThisMonth = computed(() => {
  const now = new Date()
  return payments.value
    .filter(p => p.status === 'approved' && new Date(p.payment_date).getMonth() === now.getMonth())
    .reduce((a, p) => a + parseFloat(p.amount), 0)
})
const totalOutstanding = computed(() => unpaidInvoices.value.reduce((a, i) => a + parseFloat(i.remaining_amount || 0), 0))
const paidToday = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return payments.value
    .filter(p => p.status === 'approved' && p.payment_date === today)
    .reduce((a, p) => a + parseFloat(p.amount), 0)
})

onMounted(async () => {
  detailModal = new Modal(document.getElementById('detailPaymentModal'))
  payModal    = new Modal(document.getElementById('payModalPS'))
  const [sRes, cRes] = await Promise.all([
    axios.get('/suppliers'),
    axios.get('/main-cash/accounts'),
  ])
  suppliers.value    = sRes.data.data || []
  cashAccounts.value = cRes.data.data || cRes.data || []
  await loadUnpaidInvoices()
  load()
  listenPembayaranSupplier(() => { load(); loadUnpaidInvoices() })
})
onUnmounted(() => stopPembayaranSupplier())

const loadingInvoices = ref(false)
const invoiceLoadError = ref('')

const generating     = ref(false)
const generateResult = ref(null)

async function generateMissingInvoices() {
  generating.value = true
  generateResult.value = null
  try {
    const r = await axios.post('/purchase-orders/generate-missing-invoices')
    const { count, message, generated } = r.data
    generateResult.value = { count, message }
    if (count > 0) {
      toast.success(message)
      await loadUnpaidInvoices()
    }
  } catch (e) {
    generateResult.value = { count: 0, message: 'Gagal: ' + (e.response?.data?.message || e.message) }
    toast.error(generateResult.value.message)
  } finally {
    generating.value = false
  }
}

async function loadUnpaidInvoices() {
  loadingInvoices.value = true
  invoiceLoadError.value = ''
  try {
    const r = await axios.get('/supplier-invoices', { params: { status: 'unpaid,partial', per_page: 999 } })
    const all = r.data.data || []
    unpaidInvoices.value = all.filter(i => i.status !== 'paid')
  } catch (e) {
    invoiceLoadError.value = e.response?.data?.message || 'Gagal memuat invoice'
  } finally {
    loadingInvoices.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/supplier-payments', {
      params: { supplier_id: supplierFilter.value, status: statusFilter.value, page: meta.value.page }
    })
    payments.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}

function changePage(p) { meta.value.page = p; load() }
function openDetail(p) { selectedPayment.value = p; detailModal.show() }

function resetPayForm() {
  payForm.value = {
    supplier_invoice_id: '', supplier_id: '',
    amount: 0, payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'transfer', main_cash_account_id: '',
    reference_number: '', notes: '',
    invoice_number_supplier: '',
  }
  selectedInvForPay.value = null
}

function openPayFromInvoice() {
  resetPayForm()
  payModal.show()
}

function openPayForInvoice(inv) {
  resetPayForm()
  selectedInvForPay.value          = inv
  payForm.value.supplier_invoice_id = inv.id
  payForm.value.supplier_id         = inv.supplier_id
  payForm.value.amount              = inv.remaining_amount
  payModal.show()
}

function onSelectInvoice() {
  const inv = unpaidInvoices.value.find(i => i.id == payForm.value.supplier_invoice_id)
  if (inv) {
    selectedInvForPay.value   = inv
    payForm.value.supplier_id = inv.supplier_id
    payForm.value.amount      = inv.remaining_amount
  }
}

function clearSelectedInv() {
  selectedInvForPay.value           = null
  payForm.value.supplier_invoice_id = ''
  payForm.value.supplier_id         = ''
  payForm.value.amount              = 0
}

async function savePayment() {
  if (!payForm.value.supplier_invoice_id) return toast.error('Pilih invoice terlebih dahulu')
  if (!payForm.value.amount || payForm.value.amount <= 0) return toast.error('Jumlah bayar harus lebih dari 0')
  if (!payForm.value.payment_date) return toast.error('Tanggal pembayaran wajib diisi')
  if (selectedInvForPay.value && payForm.value.amount > selectedInvForPay.value.remaining_amount)
    return toast.error('Jumlah melebihi sisa tagihan')

  saving.value = true
  try {
    // Jika user mengisi nomor invoice supplier di modal ini, update dulu sebelum POST payment
    const noSupplier = payForm.value.invoice_number_supplier?.trim()
    if (noSupplier && selectedInvForPay.value && !selectedInvForPay.value.invoice_number) {
      await axios.patch(
        `/supplier-invoices/${selectedInvForPay.value.id}/invoice-number`,
        { invoice_number: noSupplier }
      )
    }

    // Kirim hanya field yang dibutuhkan API pembayaran (tanpa invoice_number_supplier)
    const { invoice_number_supplier, ...payData } = payForm.value
    await axios.post('/supplier-payments', payData)
    toast.success('Pembayaran berhasil dicatat — menunggu approval')
    payModal.hide()
    load()
    loadUnpaidInvoices()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan pembayaran')
  } finally { saving.value = false }
}

async function approve(p) {
  if (!confirm(`Setujui pembayaran ${p.payment_number}?`)) return
  try {
    await axios.post(`/supplier-payments/${p.id}/approve`)
    toast.success('Pembayaran disetujui')
    load(); loadUnpaidInvoices()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyetujui') }
}

async function reject(p) {
  const reason = prompt('Alasan penolakan (opsional):')
  if (reason === null) return
  try {
    await axios.post(`/supplier-payments/${p.id}/reject`, { notes: reason })
    toast.success('Pembayaran ditolak')
    load()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menolak') }
}

function statusClass(s) {
  return { pending: 'badge bg-warning text-dark', approved: 'badge bg-success', rejected: 'badge bg-danger' }[s] || 'badge bg-secondary'
}
function statusLabel(s) {
  return { pending: 'Menunggu Approval', approved: 'Disetujui', rejected: 'Ditolak' }[s] || s
}
function methodClass(m) {
  return { transfer: 'badge bg-info text-dark', cash: 'badge bg-success', giro: 'badge bg-primary', cek: 'badge bg-secondary' }[m] || 'badge bg-secondary'
}
function methodLabel(m) {
  return { transfer: 'Transfer', cash: 'Tunai', giro: 'Giro', cek: 'Cek' }[m] || m
}
</script>