<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Kas Kecil (Petty Cash)</h5>
        <small class="text-muted">Kelola transaksi kas kecil per site / gudang</small>
      </div>
      <div class="d-flex gap-2">
        <button v-if="can('manage-accounting')" class="btn btn-outline-secondary btn-sm" @click="openAccountModal()">
          <i class="bi bi-wallet2 me-1"></i>Kelola Akun Kas
        </button>
        <button v-if="can('manage-accounting')" class="btn btn-csm-primary btn-sm" @click="openTrxModal()">
          <i class="bi bi-plus-circle me-1"></i>Transaksi Baru
        </button>
      </div>
    </div>

    <!-- Kartu Saldo Kas -->
    <div class="row g-3 mb-3">
      <div class="col-md-4" v-for="acc in accounts" :key="acc.id">
        <div class="csm-card h-100">
          <div class="csm-card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small">{{ acc.name }}</div>
                <div class="fw-bold fs-5 mt-1" :class="acc.balance < acc.limit * 0.2 ? 'text-danger' : 'text-success'">
                  {{ $formatCurrency(acc.balance) }}
                </div>
                <div class="text-muted" style="font-size:11px;">Limit: {{ $formatCurrency(acc.limit) }}</div>
              </div>
              <div class="rounded-circle d-flex align-items-center justify-content-center"
                style="width:42px;height:42px;background:#e8f4fd;">
                <i class="bi bi-wallet2 text-primary fs-5"></i>
              </div>
            </div>
            <div class="mt-2">
              <div class="progress" style="height:4px;">
                <div class="progress-bar" :class="acc.balance < acc.limit * 0.2 ? 'bg-danger' : 'bg-success'"
                  :style="`width:${acc.limit > 0 ? Math.min((acc.balance/acc.limit)*100,100) : 0}%`"></div>
              </div>
              <div class="text-muted mt-1" style="font-size:10px;">
                {{ acc.warehouse?.name || 'Tidak ada site' }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter Transaksi -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <select v-model="accountFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Akun Kas</option>
              <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="typeFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Tipe</option>
              <option value="in">Masuk</option>
              <option value="out">Keluar</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="statusFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Disetujui</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel Transaksi -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. Transaksi</th><th>Akun Kas</th><th>Tgl</th><th>Keterangan</th>
                <th>Tipe</th><th class="text-end">Jumlah</th><th>Status</th><th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!transactions.length"><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi</td></tr>
              <tr v-for="t in transactions" :key="t.id">
                <td><code class="small text-primary">{{ t.transaction_number }}</code></td>
                <td class="small">{{ t.account?.name }}</td>
                <td class="small">{{ $formatDate(t.transaction_date) }}</td>
                <td class="small">{{ t.description }}
                  <span v-if="t.type === 'in' && t.main_cash_account" class="text-muted d-block" style="font-size:0.75rem;">
                    <i class="bi bi-bank me-1"></i>dari {{ t.main_cash_account.name }}
                  </span>
                </td>
                <td>
                  <span :class="t.type === 'in' ? 'badge bg-success' : 'badge bg-danger'">
                    <i :class="t.type === 'in' ? 'bi bi-arrow-down' : 'bi bi-arrow-up'"></i>
                    {{ t.type === 'in' ? 'Masuk' : 'Keluar' }}
                  </span>
                </td>
                <td class="text-end fw-bold" :class="t.type === 'in' ? 'text-success' : 'text-danger'">
                  {{ t.type === 'in' ? '+' : '-' }}{{ $formatCurrency(t.amount) }}
                </td>
                <td>
                  <span :class="t.status === 'approved' ? 'badge bg-success' : t.status === 'rejected' ? 'badge bg-danger' : 'badge bg-warning text-dark'">
                    {{ t.status === 'approved' ? 'Disetujui' : t.status === 'rejected' ? 'Ditolak' : 'Pending' }}
                  </span>
                </td>
                <td>
                  <button v-if="can('approve-accounting') && t.status === 'pending'"
                    class="btn btn-xs btn-outline-success" @click="approve(t)" title="Setujui">
                    <i class="bi bi-check-lg"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center px-3 py-2" v-if="meta.last_page > 1">
          <small class="text-muted">{{ meta.total }} transaksi</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
            <button class="btn btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Transaksi Baru -->
    <div class="modal fade" id="trxModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Transaksi Kas Kecil</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Akun Kas <span class="text-danger">*</span></label>
                <select v-model="form.petty_cash_account_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Akun --</option>
                  <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tipe <span class="text-danger">*</span></label>
                <select v-model="form.type" class="form-select form-select-sm">
                  <option value="in">Kas Masuk (Top-up)</option>
                  <option value="out">Kas Keluar</option>
                </select>
              </div>
              <!-- Sumber rekening kas besar — wajib saat top-up -->
              <div class="col-12" v-if="form.type === 'in'">
                <label class="form-label fw-semibold small">Sumber Dana (Rekening Kas Besar) <span class="text-danger">*</span></label>
                <select v-model="form.main_cash_account_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Rekening --</option>
                  <option v-for="a in mainAccounts" :key="a.id" :value="a.id">
                    {{ a.name }} — Saldo: {{ $formatCurrency(a.balance) }}
                  </option>
                </select>
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Saldo rekening ini akan berkurang sejumlah top-up</small>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jumlah <span class="text-danger">*</span></label>
                <input v-model.number="form.amount" type="number" min="1" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tanggal <span class="text-danger">*</span></label>
                <input v-model="form.transaction_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Keterangan <span class="text-danger">*</span></label>
                <input v-model="form.description" class="form-control form-control-sm" placeholder="Deskripsi transaksi" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">No. Referensi / Bukti</label>
                <input v-model="form.reference_number" class="form-control form-control-sm" placeholder="No. nota / kwitansi" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="form.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="saveTrx" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>Simpan
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Kelola Akun Kas -->
    <div class="modal fade" id="accountModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Tambah Akun Kas Kecil</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold small">Nama Akun <span class="text-danger">*</span></label>
                <input v-model="accForm.name" class="form-control form-control-sm" placeholder="Kas Kecil Site A" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Site / Gudang</label>
                <select v-model="accForm.warehouse_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Site --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Limit Kas</label>
                <input v-model.number="accForm.limit" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="accForm.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="saveAccount" :disabled="saving">Simpan</button>
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
const { listenKasKecil, stopKasKecil, listenKasBesar, stopKasBesar } = useRealtime()
const can = (p) => auth.hasPermission(p)
const accounts = ref([]); const warehouses = ref([]); const transactions = ref([])
const mainAccounts = ref([])
const loading = ref(false); const saving = ref(false)
const accountFilter = ref(''); const typeFilter = ref(''); const statusFilter = ref('')
const meta = ref({ total: 0, page: 1, last_page: 1 })
const form = ref({ petty_cash_account_id: '', main_cash_account_id: '', type: 'out', amount: 0, description: '', reference_number: '', transaction_date: new Date().toISOString().split('T')[0], notes: '' })
const accForm = ref({ name: '', warehouse_id: '', limit: 0, notes: '' })
let trxModal = null; let accountModal = null

onMounted(async () => {
  trxModal = new Modal(document.getElementById('trxModal'))
  accountModal = new Modal(document.getElementById('accountModal'))
  const [aRes, wRes, mRes] = await Promise.all([
    axios.get('/petty-cash/accounts'),
    axios.get('/warehouses'),
    axios.get('/main-cash/accounts'),
  ])
  accounts.value = aRes.data.data
  warehouses.value = wRes.data.data
  mainAccounts.value = mRes.data.data || []
  load()
  listenKasKecil(() => { load(); refreshAccounts() })
  listenKasBesar(() => refreshMainAccounts())
})

onUnmounted(() => { stopKasKecil(); stopKasBesar() })

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/petty-cash/transactions', { params: { account_id: accountFilter.value, type: typeFilter.value, status: statusFilter.value, page: meta.value.page } })
    transactions.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}
async function refreshAccounts() {
  const r = await axios.get('/petty-cash/accounts')
  accounts.value = r.data.data
}
async function refreshMainAccounts() {
  const r = await axios.get('/main-cash/accounts')
  mainAccounts.value = r.data.data || []
}
function changePage(p) { meta.value.page = p; load() }
function openTrxModal() {
  form.value = { petty_cash_account_id: '', main_cash_account_id: '', type: 'out', amount: 0, description: '', reference_number: '', transaction_date: new Date().toISOString().split('T')[0], notes: '' }
  trxModal.show()
}
function openAccountModal() { accForm.value = { name: '', warehouse_id: '', limit: 0, notes: '' }; accountModal.show() }

async function saveTrx() {
  if (form.value.type === 'in' && !form.value.main_cash_account_id)
    return toast.error('Pilih rekening kas besar sebagai sumber dana top-up')
  if (form.value.type === 'in') {
    const src = mainAccounts.value.find(a => a.id === form.value.main_cash_account_id)
    if (src && form.value.amount > parseFloat(src.balance))
      return toast.error(`Saldo ${src.name} tidak cukup (saldo: Rp ${new Intl.NumberFormat('id-ID').format(src.balance)})`)
  }
  saving.value = true
  try {
    await axios.post('/petty-cash/transactions', form.value)
    toast.success('Transaksi berhasil dicatat')
    trxModal.hide(); load()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
async function saveAccount() {
  saving.value = true
  try {
    const r = await axios.post('/petty-cash/accounts', accForm.value)
    accounts.value.push(r.data.data)
    toast.success('Akun kas kecil ditambahkan')
    accountModal.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
async function approve(t) {
  if (!confirm('Setujui transaksi ini?')) return
  try {
    await axios.post(`/petty-cash/transactions/${t.id}/approve`)
    toast.success('Transaksi disetujui')
    load()
    refreshAccounts()
    refreshMainAccounts()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyetujui') }
}
</script>