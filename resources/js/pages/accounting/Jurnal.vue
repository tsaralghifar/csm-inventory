<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Jurnal Akuntansi</h5>
        <small class="text-muted">Journal Entry & General Ledger (double-entry bookkeeping)</small>
      </div>
      <button v-if="can('manage-accounting')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Jurnal Manual
      </button>
    </div>

    <!-- Tab -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <button class="nav-link" :class="tab === 'journal' ? 'active' : ''" @click="tab = 'journal'">
          <i class="bi bi-journal-text me-1"></i>Journal Entries
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="tab === 'ledger' ? 'active' : ''" @click="tab = 'ledger'; loadLedger()">
          <i class="bi bi-table me-1"></i>General Ledger
        </button>
      </li>
    </ul>

    <!-- ── TAB: JOURNAL ENTRIES ── -->
    <div v-if="tab === 'journal'">
      <!-- Filter -->
      <div class="csm-card mb-3">
        <div class="csm-card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small mb-1">Dari Tanggal</label>
              <input v-model="filter.date_from" type="date" class="form-control form-control-sm" @change="load" />
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Sampai Tanggal</label>
              <input v-model="filter.date_to" type="date" class="form-control form-control-sm" @change="load" />
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Status</label>
              <select v-model="filter.status" class="form-select form-select-sm" @change="load">
                <option value="">Semua</option>
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small mb-1">Sumber</label>
              <select v-model="filter.reference_type" class="form-select form-select-sm" @change="load">
                <option value="">Semua</option>
                <option value="main_cash_transaction">Kas Besar</option>
                <option value="petty_cash_transaction">Kas Kecil</option>
                <option value="supplier_payment">Pembayaran Supplier</option>
                <option value="payroll">Payroll</option>
                <option value="">Manual</option>
              </select>
            </div>
            <div class="col-md-2">
              <input v-model="filter.search" class="form-control form-control-sm" placeholder="🔍 Cari..." @input="debouncedLoad" />
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
                  <th>No. Jurnal</th><th>Tgl Entry</th><th>Keterangan</th><th>Sumber</th>
                  <th class="text-end">Total Debit</th><th class="text-end">Total Credit</th>
                  <th>Status</th><th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!entries.length"><td colspan="8" class="text-center text-muted py-4">Tidak ada data jurnal</td></tr>
                <template v-for="entry in entries" :key="entry.id">
                  <tr class="cursor-pointer" @click="toggleDetail(entry.id)" style="cursor:pointer;">
                    <td><code class="small">{{ entry.journal_number }}</code></td>
                    <td class="small">{{ $formatDate(entry.entry_date) }}</td>
                    <td class="small">{{ entry.description }}</td>
                    <td>
                      <span class="badge" :class="sourceClass(entry.reference_type)">
                        {{ sourceLabel(entry.reference_type) }}
                      </span>
                    </td>
                    <td class="text-end small text-primary fw-semibold">{{ $formatCurrency(entry.total_debit) }}</td>
                    <td class="text-end small text-danger fw-semibold">{{ $formatCurrency(entry.total_credit) }}</td>
                    <td>
                      <span class="badge" :class="entry.status === 'posted' ? 'bg-success' : 'bg-warning text-dark'">
                        {{ entry.status === 'posted' ? 'Posted' : 'Draft' }}
                      </span>
                    </td>
                    <td>
                      <div class="d-flex gap-1">
                        <button class="btn btn-xs btn-outline-secondary" @click.stop="toggleDetail(entry.id)" title="Lihat detail">
                          <i :class="expanded === entry.id ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                        </button>
                        <button v-if="entry.status === 'draft' && can('approve-accounting')"
                          class="btn btn-xs btn-outline-success" @click.stop="postEntry(entry)" title="Post ke General Ledger">
                          <i class="bi bi-send-check"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <!-- Detail rows -->
                  <tr v-if="expanded === entry.id" class="table-light">
                    <td colspan="8" class="p-0">
                      <div class="px-4 py-2">
                        <table class="table table-sm table-bordered mb-0">
                          <thead class="table-secondary">
                            <tr>
                              <th style="width:120px">Kode Akun</th>
                              <th>Nama Akun</th>
                              <th>Tipe</th>
                              <th class="text-end">Debit</th>
                              <th class="text-end">Credit</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="item in entry.items" :key="item.id">
                              <td class="small font-monospace">{{ item.account_code }}</td>
                              <td class="small">{{ item.account_name }}</td>
                              <td><span class="badge" :class="acctTypeClass(item.account_type)">{{ item.account_type }}</span></td>
                              <td class="text-end small" :class="item.debit > 0 ? 'text-primary fw-semibold' : 'text-muted'">
                                {{ item.debit > 0 ? $formatCurrency(item.debit) : '—' }}
                              </td>
                              <td class="text-end small" :class="item.credit > 0 ? 'text-danger fw-semibold' : 'text-muted'">
                                {{ item.credit > 0 ? $formatCurrency(item.credit) : '—' }}
                              </td>
                            </tr>
                            <tr class="table-dark fw-bold">
                              <td colspan="3" class="text-end small">Total</td>
                              <td class="text-end small text-info">{{ $formatCurrency(entry.total_debit) }}</td>
                              <td class="text-end small text-warning">{{ $formatCurrency(entry.total_credit) }}</td>
                            </tr>
                          </tbody>
                        </table>
                        <small class="text-muted mt-1 d-block" v-if="entry.creator">
                          Dibuat oleh: {{ entry.creator?.name }} &nbsp;|&nbsp;
                          <span v-if="entry.status === 'posted'">Diposting: {{ $formatDate(entry.posted_at) }}</span>
                        </small>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center px-3 py-2" v-if="meta.last_page > 1">
            <small class="text-muted">{{ meta.total }} entri jurnal</small>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
              <button class="btn btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── TAB: GENERAL LEDGER ── -->
    <div v-if="tab === 'ledger'">
      <div class="csm-card mb-3">
        <div class="csm-card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small mb-1">Dari Tanggal</label>
              <input v-model="ledgerFilter.date_from" type="date" class="form-control form-control-sm" @change="loadLedger" />
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Sampai Tanggal</label>
              <input v-model="ledgerFilter.date_to" type="date" class="form-control form-control-sm" @change="loadLedger" />
            </div>
            <div class="col-md-3">
              <label class="form-label small mb-1">Tipe Akun</label>
              <select v-model="ledgerFilter.account_type" class="form-select form-select-sm" @change="loadLedger">
                <option value="">Semua</option>
                <option value="asset">Asset</option>
                <option value="liability">Liability</option>
                <option value="equity">Equity</option>
                <option value="revenue">Revenue</option>
                <option value="expense">Expense</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="csm-card">
        <div class="csm-card-body p-0">
          <div v-if="loadingLedger" class="p-4 text-center"><div class="csm-spinner"></div></div>
          <div v-else>
            <!-- Ringkasan per tipe -->
            <div class="row g-3 p-3 border-bottom">
              <div v-for="group in ledgerGroups" :key="group.type" class="col-md-4 col-6">
                <div class="p-2 rounded border">
                  <div class="small text-muted text-uppercase fw-semibold">{{ group.type }}</div>
                  <div class="fw-bold mt-1" :class="group.balance >= 0 ? 'text-success' : 'text-danger'">
                    {{ $formatCurrency(Math.abs(group.balance)) }}
                    <span class="small fw-normal text-muted">({{ group.balance >= 0 ? 'Dr' : 'Cr' }})</span>
                  </div>
                </div>
              </div>
            </div>
            <!-- Tabel detail -->
            <div class="table-responsive">
              <table class="table csm-table mb-0">
                <thead>
                  <tr>
                    <th>Kode Akun</th><th>Nama Akun</th><th>Tipe</th>
                    <th class="text-end">Total Debit</th><th class="text-end">Total Credit</th><th class="text-end">Saldo</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!ledger.length"><td colspan="6" class="text-center text-muted py-4">Belum ada data General Ledger</td></tr>
                  <tr v-for="row in ledger" :key="row.account_code">
                    <td class="font-monospace small">{{ row.account_code }}</td>
                    <td class="small fw-semibold">{{ row.account_name }}</td>
                    <td><span class="badge" :class="acctTypeClass(row.account_type)">{{ row.account_type }}</span></td>
                    <td class="text-end small text-primary">{{ $formatCurrency(row.total_debit) }}</td>
                    <td class="text-end small text-danger">{{ $formatCurrency(row.total_credit) }}</td>
                    <td class="text-end small fw-bold" :class="row.balance >= 0 ? 'text-success' : 'text-danger'">
                      {{ $formatCurrency(Math.abs(row.balance)) }}
                      <small class="text-muted">{{ row.balance >= 0 ? 'Dr' : 'Cr' }}</small>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Jurnal Manual -->
    <div class="modal fade" id="jurnalModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-journal-plus me-2"></i>Buat Jurnal Manual</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Tanggal Entry <span class="text-danger">*</span></label>
                <input v-model="jForm.entry_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-8">
                <label class="form-label fw-semibold small">Keterangan <span class="text-danger">*</span></label>
                <input v-model="jForm.description" class="form-control form-control-sm" placeholder="Keterangan transaksi..." />
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-bordered">
                <thead class="table-primary">
                  <tr>
                    <th>Kode Akun</th><th>Nama Akun</th><th>Tipe</th>
                    <th class="text-end">Debit</th><th class="text-end">Credit</th><th>Ket.</th><th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, i) in jForm.items" :key="i">
                    <td style="min-width:120px">
                      <select v-model="item.account_code" class="form-select form-select-sm" @change="fillAccount(item)">
                        <option value="">--</option>
                        <option v-for="a in coaOptions" :key="a.code" :value="a.code">{{ a.code }} — {{ a.name }}</option>
                      </select>
                    </td>
                    <td><input v-model="item.account_name" class="form-control form-control-sm" placeholder="Nama akun" /></td>
                    <td style="width:110px">
                      <select v-model="item.account_type" class="form-select form-select-sm">
                        <option value="asset">asset</option>
                        <option value="liability">liability</option>
                        <option value="equity">equity</option>
                        <option value="revenue">revenue</option>
                        <option value="expense">expense</option>
                      </select>
                    </td>
                    <td style="width:140px">
                      <input v-model.number="item.debit" type="number" min="0" class="form-control form-control-sm text-end" placeholder="0" />
                    </td>
                    <td style="width:140px">
                      <input v-model.number="item.credit" type="number" min="0" class="form-control form-control-sm text-end" placeholder="0" />
                    </td>
                    <td>
                      <input v-model="item.description" class="form-control form-control-sm" placeholder="Keterangan baris" />
                    </td>
                    <td>
                      <button class="btn btn-xs btn-outline-danger" @click="removeItem(i)" :disabled="jForm.items.length <= 2">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr>
                    <td colspan="3" class="text-end">Total</td>
                    <td class="text-end" :class="totalDebit !== totalCredit ? 'text-danger' : 'text-success'">
                      {{ $formatCurrency(totalDebit) }}
                    </td>
                    <td class="text-end" :class="totalDebit !== totalCredit ? 'text-danger' : 'text-success'">
                      {{ $formatCurrency(totalCredit) }}
                    </td>
                    <td colspan="2">
                      <span v-if="totalDebit === totalCredit" class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Balance</span>
                      <span v-else class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Tidak Balance</span>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <button class="btn btn-outline-primary btn-sm" @click="addItem">
              <i class="bi bi-plus me-1"></i>Tambah Baris
            </button>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="saveJurnal" :disabled="saving || totalDebit !== totalCredit">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>
              <i class="bi bi-save me-1" v-else></i>Simpan Jurnal
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
const { listenJurnal, stopJurnal } = useRealtime()
const can = (p) => auth.hasPermission(p)

const tab = ref('journal')
const entries = ref([]); const ledger = ref([])
const loading = ref(false); const loadingLedger = ref(false); const saving = ref(false)
const expanded = ref(null)
const meta = ref({ total: 0, page: 1, last_page: 1 })

const filter = ref({ date_from: '', date_to: '', status: '', reference_type: '', search: '' })
const ledgerFilter = ref({ date_from: '', date_to: '', account_type: '' })

// COA default options
const coaOptions = [
  { code: '1-1001', name: 'Kas / Bank',                 type: 'asset' },
  { code: '1-1002', name: 'Kas Kecil',                  type: 'asset' },
  { code: '1-1003', name: 'Piutang Usaha',              type: 'asset' },
  { code: '1-2001', name: 'Persediaan Barang',          type: 'asset' },
  { code: '2-1001', name: 'Hutang Dagang',              type: 'liability' },
  { code: '2-1002', name: 'Utang Potongan Karyawan',    type: 'liability' },
  { code: '2-1003', name: 'Hutang Pajak',               type: 'liability' },
  { code: '3-1001', name: 'Modal',                      type: 'equity' },
  { code: '4-1001', name: 'Pendapatan Lain-lain',       type: 'revenue' },
  { code: '5-1001', name: 'Beban Operasional',          type: 'expense' },
  { code: '5-1002', name: 'Beban Gaji & Tunjangan',     type: 'expense' },
  { code: '5-1003', name: 'Beban BPJS',                 type: 'expense' },
  { code: '5-1004', name: 'Beban Pemeliharaan',         type: 'expense' },
  { code: '5-1005', name: 'Beban BBM & Transportasi',   type: 'expense' },
]

const newItem = () => ({ account_code: '', account_name: '', account_type: 'expense', debit: 0, credit: 0, description: '' })
const jForm = ref({ entry_date: new Date().toISOString().split('T')[0], description: '', items: [newItem(), newItem()] })

let modal = null; let timer = null

const totalDebit  = computed(() => jForm.value.items.reduce((s, i) => s + (parseFloat(i.debit)  || 0), 0))
const totalCredit = computed(() => jForm.value.items.reduce((s, i) => s + (parseFloat(i.credit) || 0), 0))

const ledgerGroups = computed(() => {
  const groups = {}
  ledger.value.forEach(r => {
    if (!groups[r.account_type]) groups[r.account_type] = { type: r.account_type, balance: 0 }
    groups[r.account_type].balance += parseFloat(r.balance)
  })
  return Object.values(groups)
})

onMounted(() => {
  modal = new Modal(document.getElementById('jurnalModal'))
  load()
  listenJurnal(() => load())
})

onUnmounted(() => stopJurnal())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/journal-entries', { params: { ...filter.value, page: meta.value.page } })
    entries.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}
function debouncedLoad() { clearTimeout(timer); timer = setTimeout(load, 400) }
function changePage(p) { meta.value.page = p; load() }

async function loadLedger() {
  loadingLedger.value = true
  try {
    const r = await axios.get('/general-ledger', { params: ledgerFilter.value })
    ledger.value = r.data.data
  } finally { loadingLedger.value = false }
}

function toggleDetail(id) { expanded.value = expanded.value === id ? null : id }

async function postEntry(entry) {
  if (!confirm(`Post jurnal ${entry.journal_number} ke General Ledger?`)) return
  try {
    await axios.post(`/journal-entries/${entry.id}/post`)
    toast.success('Jurnal berhasil diposting ke General Ledger')
    load()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal posting') }
}

function openModal() {
  jForm.value = { entry_date: new Date().toISOString().split('T')[0], description: '', items: [newItem(), newItem()] }
  modal.show()
}
function addItem() { jForm.value.items.push(newItem()) }
function removeItem(i) { jForm.value.items.splice(i, 1) }
function fillAccount(item) {
  const found = coaOptions.find(a => a.code === item.account_code)
  if (found) { item.account_name = found.name; item.account_type = found.type }
}

async function saveJurnal() {
  if (!jForm.value.description || !jForm.value.entry_date) { toast.warning('Lengkapi tanggal dan keterangan'); return }
  if (Math.abs(totalDebit.value - totalCredit.value) > 0.01) { toast.warning('Total debit harus sama dengan total credit'); return }
  saving.value = true
  try {
    await axios.post('/journal-entries', jForm.value)
    toast.success('Jurnal berhasil disimpan')
    modal.hide(); load()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal menyimpan jurnal') }
  finally { saving.value = false }
}

function sourceLabel(t) {
  return { main_cash_transaction:'Kas Besar', petty_cash_transaction:'Kas Kecil', supplier_payment:'Bayar Supplier', payroll:'Payroll' }[t] || 'Manual'
}
function sourceClass(t) {
  return { main_cash_transaction:'bg-primary', petty_cash_transaction:'bg-secondary', supplier_payment:'bg-danger', payroll:'bg-success' }[t] || 'bg-dark'
}
function acctTypeClass(t) {
  return { asset:'bg-info text-dark', liability:'bg-warning text-dark', equity:'bg-primary', revenue:'bg-success', expense:'bg-danger' }[t] || 'bg-secondary'
}
</script>