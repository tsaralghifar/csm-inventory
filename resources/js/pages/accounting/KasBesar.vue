<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Kas Besar / Rekening</h5>
        <small class="text-muted">Kelola transaksi kas besar dan rekening bank perusahaan</small>
      </div>
      <div class="d-flex gap-2">
        <button v-if="can('manage-accounting')" class="btn btn-outline-secondary btn-sm" @click="openAccountModal()">
          <i class="bi bi-bank me-1"></i>Kelola Rekening
        </button>
        <button v-if="can('manage-accounting')" class="btn btn-csm-primary btn-sm" @click="openTrxModal()">
          <i class="bi bi-plus-circle me-1"></i>Transaksi Baru
        </button>
      </div>
    </div>

    <!-- Kartu Saldo Rekening -->
    <div class="row g-3 mb-3">
      <div class="col-md-4" v-for="acc in accounts" :key="acc.id">
        <div class="csm-card h-100">
          <div class="csm-card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small">{{ acc.name }}</div>
                <div class="fw-bold fs-5 mt-1 text-primary">{{ $formatCurrency(acc.balance) }}</div>
                <div class="text-muted" style="font-size:11px;">
                  {{ acc.bank_name ? acc.bank_name + ' · ' : '' }}{{ acc.account_number || 'Tunai' }}
                </div>
              </div>
              <div class="rounded-circle d-flex align-items-center justify-content-center"
                style="width:42px;height:42px;background:#e8f4fd;">
                <i class="bi bi-bank text-primary fs-5"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <select v-model="accountFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Rekening</option>
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
                <th>No. Transaksi</th><th>Rekening</th><th>Tgl</th><th>Keterangan</th>
                <th>Tipe</th><th class="text-end">Jumlah</th><th>Status</th><th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!transactions.length"><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi</td></tr>
              <tr v-for="t in transactions" :key="t.id">
                <td><code class="small text-primary">{{ t.transaction_number }}</code></td>
                <td class="small">{{ t.account?.name }}</td>
                <td class="small">{{ $formatDate(t.transaction_date) }}</td>
                <td class="small">{{ t.description }}</td>
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

    <!-- Modal Transaksi -->
    <div class="modal fade" id="mainTrxModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Transaksi Kas Besar</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Rekening <span class="text-danger">*</span></label>
                <select v-model="form.main_cash_account_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Rekening --</option>
                  <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tipe <span class="text-danger">*</span></label>
                <select v-model="form.type" class="form-select form-select-sm">
                  <option value="in">Kas Masuk</option>
                  <option value="out">Kas Keluar</option>
                </select>
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
                <input v-model="form.description" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">No. Referensi</label>
                <input v-model="form.reference_number" class="form-control form-control-sm" />
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

    <!-- Modal Tambah Rekening -->
    <div class="modal fade" id="mainAccountModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Tambah Rekening / Kas Besar</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold small">Nama Rekening <span class="text-danger">*</span></label>
                <input v-model="accForm.name" class="form-control form-control-sm" placeholder="Kas Utama / BCA / BRI..." />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Nama Bank</label>
                <input v-model="accForm.bank_name" class="form-control form-control-sm" placeholder="BCA, BRI, Mandiri..." />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">No. Rekening</label>
                <input v-model="accForm.account_number" class="form-control form-control-sm" />
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
const { listenKasBesar, stopKasBesar } = useRealtime()
const can = (p) => auth.hasPermission(p)
const accounts = ref([]); const transactions = ref([])
const loading = ref(false); const saving = ref(false)
const accountFilter = ref(''); const typeFilter = ref('')
const meta = ref({ total: 0, page: 1, last_page: 1 })
const form = ref({ main_cash_account_id: '', type: 'out', amount: 0, description: '', reference_number: '', transaction_date: new Date().toISOString().split('T')[0], notes: '' })
const accForm = ref({ name: '', bank_name: '', account_number: '', notes: '' })
let trxModal = null; let accountModal = null

onMounted(async () => {
  trxModal = new Modal(document.getElementById('mainTrxModal'))
  accountModal = new Modal(document.getElementById('mainAccountModal'))
  const aRes = await axios.get('/main-cash/accounts')
  accounts.value = aRes.data.data
  load()
  listenKasBesar(() => load())
})

onUnmounted(() => stopKasBesar())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/main-cash/transactions', { params: { account_id: accountFilter.value, type: typeFilter.value, page: meta.value.page } })
    transactions.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}
function changePage(p) { meta.value.page = p; load() }
function openTrxModal() { form.value.transaction_date = new Date().toISOString().split('T')[0]; trxModal.show() }
function openAccountModal() { accForm.value = { name: '', bank_name: '', account_number: '', notes: '' }; accountModal.show() }

async function saveTrx() {
  saving.value = true
  try {
    await axios.post('/main-cash/transactions', form.value)
    toast.success('Transaksi berhasil dicatat')
    trxModal.hide(); load()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
async function saveAccount() {
  saving.value = true
  try {
    const r = await axios.post('/main-cash/accounts', accForm.value)
    accounts.value.push(r.data.data)
    toast.success('Rekening berhasil ditambahkan')
    accountModal.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
async function approve(t) {
  if (!confirm('Setujui transaksi ini?')) return
  try {
    await axios.post(`/main-cash/transactions/${t.id}/approve`)
    toast.success('Transaksi disetujui')
    load()
    const aRes = await axios.get('/main-cash/accounts')
    accounts.value = aRes.data.data
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyetujui') }
}
</script>