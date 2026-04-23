<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Pembayaran Supplier</h5>
        <small class="text-muted">Riwayat dan approval pembayaran tagihan supplier</small>
      </div>
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

    <!-- Filter -->
    <div class="csm-card mb-3">
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

    <!-- Tabel -->
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
                <td><code class="small text-muted">{{ p.invoice?.invoice_number }}</code></td>
                <td class="small">{{ $formatDate(p.payment_date) }}</td>
                <td>
                  <span :class="methodClass(p.payment_method)">
                    {{ methodLabel(p.payment_method) }}
                  </span>
                </td>
                <td class="small text-muted">{{ p.reference_number || '-' }}</td>
                <td class="text-end fw-bold">{{ $formatCurrency(p.amount) }}</td>
                <td>
                  <span :class="statusClass(p.status)">{{ statusLabel(p.status) }}</span>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-info" @click="openDetail(p)" title="Detail">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button
                      v-if="can('approve-accounting') && p.status === 'pending'"
                      class="btn btn-xs btn-outline-success"
                      @click="approve(p)"
                      title="Setujui & Proses Pembayaran"
                    >
                      <i class="bi bi-check-lg"></i>
                    </button>
                    <button
                      v-if="can('approve-accounting') && p.status === 'pending'"
                      class="btn btn-xs btn-outline-danger"
                      @click="reject(p)"
                      title="Tolak"
                    >
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center px-3 py-2" v-if="meta.last_page > 1">
          <small class="text-muted">Halaman {{ meta.page }} dari {{ meta.last_page }} ({{ meta.total }} pembayaran)</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
            <button class="btn btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailPaymentModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content" v-if="selectedPayment">
          <div class="modal-header">
            <h5 class="modal-title">Detail Pembayaran</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <table class="table table-sm table-borderless">
              <tbody>
                <tr><td class="text-muted small" style="width:40%">No. Pembayaran</td><td><code class="text-primary">{{ selectedPayment.payment_number }}</code></td></tr>
                <tr><td class="text-muted small">Supplier</td><td class="fw-semibold">{{ selectedPayment.supplier?.name }}</td></tr>
                <tr><td class="text-muted small">No. Invoice</td><td>{{ selectedPayment.invoice?.invoice_number }}</td></tr>
                <tr><td class="text-muted small">Tanggal Bayar</td><td>{{ $formatDate(selectedPayment.payment_date) }}</td></tr>
                <tr><td class="text-muted small">Metode</td><td><span :class="methodClass(selectedPayment.payment_method)">{{ methodLabel(selectedPayment.payment_method) }}</span></td></tr>
                <tr><td class="text-muted small">No. Referensi</td><td>{{ selectedPayment.reference_number || '-' }}</td></tr>
                <tr><td class="text-muted small">Jumlah</td><td class="fw-bold fs-6 text-success">{{ $formatCurrency(selectedPayment.amount) }}</td></tr>
                <tr><td class="text-muted small">Status</td><td><span :class="statusClass(selectedPayment.status)">{{ statusLabel(selectedPayment.status) }}</span></td></tr>
                <tr v-if="selectedPayment.notes"><td class="text-muted small">Catatan</td><td>{{ selectedPayment.notes }}</td></tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button
              v-if="can('approve-accounting') && selectedPayment.status === 'pending'"
              class="btn btn-success btn-sm"
              @click="approve(selectedPayment); detailModal.hide()"
            >
              <i class="bi bi-check-lg me-1"></i>Setujui Pembayaran
            </button>
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
const payments = ref([]); const suppliers = ref([])
const loading = ref(false)
const supplierFilter = ref(''); const statusFilter = ref('')
const meta = ref({ total: 0, page: 1, last_page: 1 })
const selectedPayment = ref(null)
let detailModal = null

// Summary stats
const pendingCount = computed(() => payments.value.filter(p => p.status === 'pending').length)
const approvedThisMonth = computed(() => {
  const now = new Date()
  return payments.value
    .filter(p => p.status === 'approved' && new Date(p.payment_date).getMonth() === now.getMonth())
    .reduce((a, p) => a + parseFloat(p.amount), 0)
})
const totalOutstanding = computed(() => suppliers.value.reduce((a, s) => a + parseFloat(s.outstanding_balance || 0), 0))
const paidToday = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return payments.value
    .filter(p => p.status === 'approved' && p.payment_date === today)
    .reduce((a, p) => a + parseFloat(p.amount), 0)
})

onMounted(async () => {
  detailModal = new Modal(document.getElementById('detailPaymentModal'))
  const sRes = await axios.get('/suppliers')
  suppliers.value = sRes.data.data
  load()
  listenPembayaranSupplier(() => load())
})

onUnmounted(() => stopPembayaranSupplier())

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

async function approve(p) {
  if (!confirm(`Setujui pembayaran ${p.payment_number} sebesar ${p.amount?.toLocaleString('id-ID')}?`)) return
  try {
    await axios.post(`/supplier-payments/${p.id}/approve`)
    toast.success('Pembayaran disetujui dan saldo kas diperbarui')
    load()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyetujui') }
}

async function reject(p) {
  const reason = prompt('Alasan penolakan (opsional):')
  if (reason === null) return // user cancel
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
  return { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak' }[s] || s
}
function methodClass(m) {
  return { transfer: 'badge bg-info text-dark', cash: 'badge bg-success', giro: 'badge bg-primary', cek: 'badge bg-secondary' }[m] || 'badge bg-secondary'
}
function methodLabel(m) {
  return { transfer: 'Transfer', cash: 'Tunai', giro: 'Giro', cek: 'Cek' }[m] || m
}
</script>