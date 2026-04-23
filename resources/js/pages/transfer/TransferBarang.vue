<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Transfer Barang</h5>
        <small class="text-muted">Transfer barang antar gudang (HO ↔ Site)</small>
      </div>
      <button v-if="can('create-mr')" class="btn btn-csm-primary btn-sm" @click="openCreate">
        <i class="bi bi-plus-circle me-1"></i>Buat Transfer Baru
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. MR..." @input="debouncedLoad" />
          </div>
          <div class="col-md-2">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="pending_admin">Menunggu Admin</option>
              <option value="pending_atasan">Menunggu Atasan</option>
              <option value="approved">Disetujui</option>
              <option value="dispatched">Dikirim</option>
              <option value="received">Diterima</option>
              <option value="rejected">Ditolak</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="filters.from_warehouse_id" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Gudang Asal</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-1">
            <button class="btn btn-outline-secondary btn-sm w-100" @click="resetFilters">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center p-5"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. MR</th>
                <th>Gudang Asal</th>
                <th>Gudang Tujuan</th>
                <th>Item</th>
                <th>Diajukan Oleh</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="8" class="text-center text-muted py-5">Belum ada data Transfer Barang</td>
              </tr>
              <tr v-for="mr in list" :key="mr.id">
                <td>
                  <router-link :to="`/transfer-barang/${mr.id}`" class="fw-semibold text-primary text-decoration-none">
                    {{ mr.mr_number }}
                  </router-link>
                </td>
                <td><small>{{ mr.from_warehouse?.name }}</small></td>
                <td><small>{{ mr.to_warehouse?.name }}</small></td>
                <td><span class="badge bg-secondary rounded-pill">{{ mr.items_count }} item</span></td>
                <td><small>{{ mr.requester?.name }}</small></td>
                <td><small class="text-muted">{{ $formatDate(mr.created_at) }}</small></td>
                <td>
                  <span class="badge" :class="statusClass(mr.status)">{{ statusLabel(mr.status) }}</span>
                </td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <router-link :to="`/transfer-barang/${mr.id}`" class="btn btn-outline-primary" title="Detail">
                      <i class="bi bi-eye"></i>
                    </router-link>
                    <button v-if="mr.status === 'draft' && can('create-mr')"
                      class="btn btn-outline-info" title="Submit" @click="doSubmit(mr)">
                      <i class="bi bi-send"></i>
                    </button>
                    <button v-if="mr.status === 'pending_admin' && can('approve-mr')"
                      class="btn btn-outline-success" title="Setujui (Admin)" @click="openApproveAdmin(mr)">
                      <i class="bi bi-person-check"></i>
                    </button>
                    <button v-if="mr.status === 'pending_atasan' && can('approve-mr-manager')"
                      class="btn btn-outline-success" title="Setujui (Atasan)" @click="doApproveAtasan(mr)">
                      <i class="bi bi-check-circle"></i>
                    </button>
                    <button v-if="(mr.status === 'pending_admin' || mr.status === 'pending_atasan') && (can('approve-mr') || can('approve-mr-manager'))"
                      class="btn btn-outline-danger" title="Tolak" @click="openReject(mr)">
                      <i class="bi bi-x-circle"></i>
                    </button>
                    <button v-if="mr.status === 'draft' && can('create-mr')"
                      class="btn btn-outline-danger" title="Hapus" @click="doDelete(mr)">
                      <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn btn-outline-secondary" title="Print PDF" @click="printMRDirect(mr)">
                      <i class="bi bi-printer"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">Total {{ meta.total }} transfer</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Buat Transfer ===== -->
    <div class="modal fade" id="modalCreateTransfer" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title text-primary"><i class="bi bi-arrow-left-right me-2"></i>Buat MR Transfer Barang</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Gudang Asal <span class="text-danger">*</span></label>
                <select v-model="form.from_warehouse_id" class="form-select form-select-sm" @change="loadStockFrom">
                  <option value="">-- Pilih Gudang Asal --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Gudang Tujuan <span class="text-danger">*</span></label>
                <select v-model="form.to_warehouse_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Gudang Tujuan --</option>
                  <option v-for="w in warehouses.filter(w => w.id != form.from_warehouse_id)" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Tanggal Dibutuhkan</label>
                <input v-model="form.needed_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Catatan</label>
                <input v-model="form.notes" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
              </div>
            </div>

            <!-- Item List -->
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label fw-semibold mb-0">Daftar Barang</label>
              <button class="btn btn-sm btn-outline-primary" @click="addItem" :disabled="!form.from_warehouse_id">
                <i class="bi bi-plus me-1"></i>Tambah Barang
              </button>
            </div>

            <div v-if="!form.from_warehouse_id" class="alert alert-info small py-2">
              <i class="bi bi-info-circle me-1"></i>Pilih gudang asal terlebih dahulu untuk melihat stok yang tersedia.
            </div>

            <div v-for="(item, idx) in form.items" :key="idx" class="csm-card mb-2 border">
              <div class="csm-card-body py-2 px-3">
                <div class="row g-2 align-items-start">
                  <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">Barang</label>
                    <!-- Search input -->
                    <div class="position-relative">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input
                          v-model="item._search"
                          type="text"
                          class="form-control form-control-sm"
                          :placeholder="item.item_id ? item._selectedLabel : 'Cari nama / part number...'"
                          :class="item.item_id ? 'text-dark fw-semibold' : ''"
                          @input="onSearchItem(item)"
                          @focus="item._showDrop = true"
                          @blur="onBlurItem(item)"
                          autocomplete="off"
                        />
                        <button v-if="item.item_id" class="btn btn-outline-secondary btn-sm" type="button"
                          @mousedown.prevent="clearItem(item)" title="Hapus pilihan">
                          <i class="bi bi-x"></i>
                        </button>
                      </div>
                      <!-- Dropdown hasil pencarian -->
                      <div v-if="item._showDrop && item._results.length"
                        class="position-absolute w-100 bg-white border rounded shadow-sm"
                        style="z-index:1055;max-height:220px;overflow-y:auto;top:100%;left:0;">
                        <div
                          v-for="s in item._results" :key="s.item_id"
                          class="px-3 py-2 border-bottom"
                          style="cursor:pointer;"
                          :class="s.qty <= 0 ? 'text-muted' : ''"
                          @mousedown.prevent="selectItem(item, s)"
                          @mouseover="$event.currentTarget.style.background='#f0f7ff'"
                          @mouseout="$event.currentTarget.style.background=''">
                          <div class="d-flex align-items-center justify-content-between">
                            <div>
                              <div class="fw-semibold small">{{ s.item?.name }}</div>
                              <div class="d-flex gap-2 mt-1">
                                <code v-if="s.item?.part_number" class="small text-primary" style="font-size:0.7rem;">
                                  {{ s.item.part_number }}
                                </code>
                                <small class="text-muted" style="font-size:0.7rem;">{{ s.item?.category?.name }}</small>
                              </div>
                            </div>
                            <div class="text-end ms-2">
                              <span class="badge" :class="s.qty > 0 ? 'bg-success' : 'bg-danger'">
                                Stok: {{ s.qty }} {{ s.item?.unit }}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div v-if="item._showDrop && item._search.length >= 1 && !item._results.length && !item._loading"
                        class="position-absolute w-100 bg-white border rounded shadow-sm px-3 py-2 text-muted small"
                        style="z-index:1055;top:100%;left:0;">
                        Tidak ada barang ditemukan
                      </div>
                    </div>
                    <!-- Info stok terpilih -->
                    <div v-if="item.item_id" class="mt-1 d-flex gap-2">
                      <span class="badge" :class="item._stok > 0 ? 'bg-success' : 'bg-danger'" style="font-size:0.7rem;">
                        Stok tersedia: {{ item._stok }} {{ item._unit }}
                      </span>
                      <code v-if="item._partNumber" class="small text-primary" style="font-size:0.7rem;">{{ item._partNumber }}</code>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Jumlah</label>
                    <input v-model="item.qty" type="number" class="form-control form-control-sm"
                      min="0.01" :max="item._stok || undefined" step="0.01" placeholder="0" />
                    <div v-if="item.item_id && item.qty > item._stok" class="text-danger mt-1" style="font-size:0.7rem;">
                      <i class="bi bi-exclamation-triangle-fill me-1"></i>Melebihi stok tersedia
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Keterangan</label>
                    <input v-model="item.keterangan" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
                  </div>
                  <div class="col-md-1 pt-4">
                    <button class="btn btn-outline-danger btn-sm w-100" @click="removeItem(idx)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="form.items.length === 0" class="text-center text-muted small py-3">
              Belum ada barang ditambahkan
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-primary btn-sm" @click="saveTransfer" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-floppy me-1"></i>Simpan sebagai Draft
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Approve Admin (set qty_approved) ===== -->
    <div class="modal fade" id="modalApproveAdmin" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog">
        <div class="modal-content" v-if="approveTarget">
          <div class="modal-header bg-success bg-opacity-10">
            <h6 class="modal-title text-success"><i class="bi bi-person-check me-2"></i>Setujui Transfer (Admin)</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info small py-2 mb-3">
              <i class="bi bi-info-circle me-1"></i>
              Periksa ketersediaan stok dan sesuaikan jumlah yang disetujui.
            </div>
            <table class="table table-sm csm-table mb-0">
              <thead>
                <tr>
                  <th>Barang</th>
                  <th class="text-end">Diminta</th>
                  <th class="text-end">Stok Tersedia</th>
                  <th class="text-end" style="width:120px;">Disetujui</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in approveItems" :key="item.id">
                  <td class="fw-semibold small">{{ item.item?.name }}</td>
                  <td class="text-end small">{{ item.qty_request }}</td>
                  <td class="text-end small">
                    <span :class="(item._stok || 0) < item.qty_request ? 'text-danger fw-bold' : 'text-success'">
                      {{ item._stok ?? '-' }}
                    </span>
                  </td>
                  <td>
                    <input v-model="item.qty_approved_input" type="number" class="form-control form-control-sm text-end"
                      min="0" :max="item._stok || item.qty_request" step="0.01" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm" @click="doApproveAdmin" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-check-circle me-1"></i>Setujui
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Tolak ===== -->
    <div class="modal fade" id="modalRejectTransfer" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Tolak Transfer</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea v-model="rejectReason" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..."></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger btn-sm" @click="doReject" :disabled="acting || !rejectReason">
              <span v-if="acting" class="csm-spinner me-1"></span>Tolak
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'

const toast = useToast()
const auth = useAuthStore()
const can = (p) => auth.hasPermission(p)
const { listenTransfer, stopListenTransfer } = useRealtime()

const list = ref([])
const loading = ref(false)
const acting = ref(false)
const saving = ref(false)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ search: '', status: '', from_warehouse_id: '', date_from: '', date_to: '' })
const warehouses = ref([])
const stockFrom = ref([])
const selectedMR = ref(null)
const approveTarget = ref(null)
const approveItems = ref([])
const rejectReason = ref('')
let timer = null; let suppressNextToast = false

const form = ref({ from_warehouse_id: '', to_warehouse_id: '', needed_date: '', notes: '', items: [] })

const actionLabel = {
  created: 'Transfer baru dibuat',
  submitted: 'Transfer disubmit ke Admin',
  approved_admin: 'Transfer disetujui Admin',
  approved_atasan: 'Transfer disetujui Atasan',
  dispatched: 'Barang dikirim',
  received: 'Barang diterima',
  rejected: 'Transfer ditolak',
}

const statusLabel = (s) => ({
  draft: 'Draft',
  pending_admin: 'Menunggu Admin',
  pending_atasan: 'Menunggu Atasan',
  approved: 'Disetujui',
  dispatched: 'Dikirim',
  received: 'Diterima',
  rejected: 'Ditolak',
}[s] || s)

const statusClass = (s) => ({
  draft: 'bg-secondary',
  pending_admin: 'bg-warning text-dark',
  pending_atasan: 'bg-info text-dark',
  approved: 'bg-primary',
  dispatched: 'bg-warning text-dark',
  received: 'bg-success',
  rejected: 'bg-danger',
}[s] || 'bg-secondary')

onMounted(async () => {
  const res = await axios.get('/warehouses')
  warehouses.value = res.data.data
  loadData()

  // Dengarkan update real-time dari user lain
  listenTransfer((event) => {
    if (!suppressNextToast) {
      const label = actionLabel[event.action] || 'Transfer diperbarui'
      toast.info(`🔔 ${event.mr_number}: ${label}`, { timeout: 4000 })
    }
    suppressNextToast = false
    loadData()
  })
})

onUnmounted(() => {
  stopListenTransfer()
})

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/transfer-barang', { params: { ...filters.value, page: meta.value.page, per_page: 15 } })
    list.value = res.data.data
    meta.value = res.data.meta
  } finally { loading.value = false }
}

function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400) }
function changePage(p) { meta.value.page = p; loadData() }
function resetFilters() { filters.value = { search: '', status: '', from_warehouse_id: '', date_from: '', date_to: '' }; meta.value.page = 1; loadData() }

// ── Buat Transfer ──
function openCreate() {
  form.value = { from_warehouse_id: '', to_warehouse_id: '', needed_date: '', notes: '', items: [] }
  stockFrom.value = []
  new Modal('#modalCreateTransfer').show()
}

async function loadStockFrom() {
  if (!form.value.from_warehouse_id) return
  try {
    const res = await axios.get(`/warehouses/${form.value.from_warehouse_id}/stocks`, { params: { per_page: 999 } })
    stockFrom.value = (res.data.data || [])
  } catch { stockFrom.value = [] }
}

function addItem() {
  form.value.items.push({
    item_id: '', qty: 1, keterangan: '',
    _search: '', _results: [], _showDrop: false, _loading: false,
    _selectedLabel: '', _stok: 0, _unit: '', _partNumber: '',
  })
}

function onSearchItem(item) {
  item.item_id = ''
  item._selectedLabel = ''
  item._stok = 0
  item._unit = ''
  item._partNumber = ''
  const q = item._search.toLowerCase().trim()
  if (!q) { item._results = []; return }
  item._results = stockFrom.value.filter(s =>
    s.item?.name?.toLowerCase().includes(q) ||
    (s.item?.part_number && s.item.part_number.toLowerCase().includes(q))
  )
}

function selectItem(item, s) {
  item.item_id       = s.item_id
  item._search       = ''
  item._selectedLabel = s.item?.name + (s.item?.part_number ? ` [${s.item.part_number}]` : '')
  item._stok         = parseFloat(s.qty) || 0
  item._unit         = s.item?.unit || ''
  item._partNumber   = s.item?.part_number || ''
  item._results      = []
  item._showDrop     = false
  if (!item.qty || item.qty <= 0) item.qty = 1
}

function clearItem(item) {
  item.item_id = ''
  item._search = ''
  item._selectedLabel = ''
  item._stok = 0
  item._unit = ''
  item._partNumber = ''
  item._results = []
  item._showDrop = false
}

function onBlurItem(item) {
  setTimeout(() => { item._showDrop = false }, 150)
}

function removeItem(idx) {
  form.value.items.splice(idx, 1)
}

async function saveTransfer() {
  if (!form.value.from_warehouse_id) return toast.error('Pilih gudang asal')
  if (!form.value.to_warehouse_id) return toast.error('Pilih gudang tujuan')
  if (!form.value.items.length) return toast.error('Tambahkan minimal 1 barang')
  for (const i of form.value.items) {
    if (!i.item_id) return toast.error('Pilih barang untuk semua baris')
    if (!i.qty || i.qty <= 0) return toast.error('Jumlah barang harus lebih dari 0')
  }
  saving.value = true
  try {
    await axios.post('/transfer-barang', {
      from_warehouse_id: form.value.from_warehouse_id,
      to_warehouse_id: form.value.to_warehouse_id,
      needed_date: form.value.needed_date || null,
      notes: form.value.notes,
      items: form.value.items.map(i => ({ item_id: i.item_id, qty: i.qty, keterangan: i.keterangan })),
    })
    toast.success('MR Transfer berhasil dibuat')
    suppressNextToast = true
    Modal.getInstance('#modalCreateTransfer')?.hide()
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') } finally { saving.value = false }
}

// ── Submit ──
async function doSubmit(mr) {
  if (!confirm(`Submit MR ${mr.mr_number} ke Admin untuk disetujui?`)) return
  acting.value = true
  try {
    await axios.post(`/transfer-barang/${mr.id}/submit`)
    toast.success('MR disubmit ke Admin')
    suppressNextToast = true
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Approve Admin ──
async function openApproveAdmin(mr) {
  approveTarget.value = mr
  try {
    const res = await axios.get(`/transfer-barang/${mr.id}`)
    const items = res.data.data.items
    // Fetch stok gudang asal untuk setiap item
    const stockRes = await axios.get(`/warehouses/${mr.from_warehouse_id}/stocks`, { params: { per_page: 999 } })
    const stockMap = {}
    ;(stockRes.data.data || []).forEach(s => { stockMap[s.item_id] = s.qty })

    approveItems.value = items.map(i => ({
      ...i,
      _stok: stockMap[i.item_id] ?? 0,
      qty_approved_input: i.qty_request,
    }))
  } catch { approveItems.value = [] }
  new Modal('#modalApproveAdmin').show()
}

async function doApproveAdmin() {
  acting.value = true
  try {
    await axios.post(`/transfer-barang/${approveTarget.value.id}/approve-admin`, {
      items: approveItems.value.map(i => ({ id: i.id, qty_approved: i.qty_approved_input })),
    })
    toast.success('Transfer disetujui Admin, menunggu Atasan')
    suppressNextToast = true
    Modal.getInstance('#modalApproveAdmin')?.hide()
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Approve Atasan ──
async function doApproveAtasan(mr) {
  if (!confirm(`Setujui MR Transfer ${mr.mr_number} sebagai Atasan?`)) return
  acting.value = true
  try {
    await axios.post(`/transfer-barang/${mr.id}/approve-atasan`)
    toast.success('MR disetujui Atasan, siap untuk pengiriman')
    suppressNextToast = true
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Tolak ──
function openReject(mr) {
  selectedMR.value = mr
  rejectReason.value = ''
  new Modal('#modalRejectTransfer').show()
}

async function doReject() {
  acting.value = true
  try {
    await axios.post(`/transfer-barang/${selectedMR.value.id}/reject`, { reason: rejectReason.value })
    toast.success('MR Transfer ditolak')
    suppressNextToast = true
    Modal.getInstance('#modalRejectTransfer')?.hide()
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Hapus ──
async function doDelete(mr) {
  if (!confirm(`Hapus MR ${mr.mr_number}?`)) return
  try {
    await axios.delete(`/transfer-barang/${mr.id}`)
    toast.success('MR Transfer dihapus')
    suppressNextToast = true
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
}

// ── Print PDF ─────────────────────────────────────────────────────────────────
async function printMRDirect(mr) {
  try {
    const res = await axios.get('/transfer-barang/' + mr.id)
    printMR(res.data.data)
  } catch { toast.error('Gagal memuat data') }
}

function printMR(mr) {
  const fmtD = (v) => v ? new Date(v).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'}) : '-'

  const rows = (mr.items||[]).map((item,i) =>
    '<tr style="background:' + (i%2?'#f8fafc':'#fff') + '">' +
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;color:#64748b">'+(i+1)+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 8px;font-family:monospace;font-weight:700;color:#1a3a5c;font-size:9pt">'+(item.item?.part_number||'-')+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 10px;font-weight:600;color:#1f2937">'+(item.item?.name||item.nama_barang||'-')+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;font-weight:700;color:#1a3a5c">'+item.qty_request+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;color:#16a34a;font-weight:700">'+(item.qty_approved||'-')+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px">'+(item.item?.unit||item.satuan||'-')+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 10px;color:#64748b;font-size:9pt">'+(item.notes||item.keterangan||'-')+'</td>'+
    '</tr>'
  ).join('')

  const css =
    '*{margin:0;padding:0;box-sizing:border-box}'+
    'body{font-family:Arial,sans-serif;font-size:10pt;color:#1f2937;padding:20px}'+
    '@media print{body{padding:0}@page{margin:15mm 12mm;size:A4}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}}'+
    '.hdr{background:#1a3a5c;color:#fff;padding:14px 20px;border-radius:8px 8px 0 0}'+
    '.hdr h1{font-size:15pt;font-weight:800}'+
    '.hdr2{background:#2563a8;color:#fff;padding:7px 20px;display:flex;align-items:center;gap:12px}'+
    '.igrid{display:grid;grid-template-columns:1fr 1fr;border:1px solid #e2e8f0;border-top:none}'+
    '.isec{padding:12px 16px}.isec:first-child{border-right:1px solid #e2e8f0}'+
    '.ititle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #e8edf4}'+
    '.irow{display:flex;margin-bottom:5px;font-size:9pt}'+
    '.ilbl{color:#64748b;width:130px;flex-shrink:0}'+
    '.ival{font-weight:600;color:#1a3a5c}.ival2{color:#374151}'+
    '.arrow{display:flex;align-items:center;justify-content:center;gap:16px;padding:10px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-top:none;font-size:10pt}'+
    '.wbox{background:#e8edf4;border-radius:6px;padding:4px 12px;font-weight:700;color:#1a3a5c}'+
    'table.it{width:100%;border-collapse:collapse;margin-top:14px}'+
    'table.it th{background:#1a3a5c;color:#fff;padding:8px 10px;font-size:9pt;font-weight:700}'+
    '.sgrid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;margin-top:24px}'+
    '.sbox{border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 12px}'+
    '.stitle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;text-align:center;background:#e8edf4;margin:-8px -12px 8px;padding:6px;border-radius:4px 4px 0 0}'+
    '.sspace{height:45px;border-bottom:1.5px solid #e2e8f0;margin-bottom:6px}'+
    '.sname{font-size:9pt;font-weight:600;color:#1a3a5c;text-align:center}'

  const o = '<', c = '>'
  const stO = o+'style'+c, stC = o+'/style'+c
  const htO = o+'html'+c,  htC = o+'/html'+c
  const hdC = o+'/head'+c, bdC = o+'/body'+c

  const html =
    '<!DOCTYPE html>'+htO+
    o+'head'+c+o+'meta charset="UTF-8"/'+c+o+'title'+'>'+'MR-'+mr.mr_number+o+'/title'+c+
    stO+css+stC+hdC+
    o+'body'+c+
    '<div class="hdr"><h1>PT. CIPTA SARANA MAKMUR</h1></div>'+
    '<div class="hdr2">'+
      '<span style="font-size:11pt;font-weight:700">SURAT JALAN TRANSFER BARANG</span>'+
      '<span style="font-size:11pt;font-weight:800;background:#fff;color:#2563a8;padding:2px 12px;border-radius:4px">'+mr.mr_number+'</span>'+
    '</div>'+
    '<div class="arrow">'+
      '<span class="wbox">'+(mr.from_warehouse?.name||mr.fromWarehouse?.name||'-')+'</span>'+
      '<span style="font-size:14pt;color:#2563a8">&#8594;</span>'+
      '<span class="wbox">'+(mr.to_warehouse?.name||mr.toWarehouse?.name||'-')+'</span>'+
    '</div>'+
    '<div class="igrid">'+
      '<div class="isec">'+
        '<div class="ititle">Informasi Transfer</div>'+
        '<div class="irow"><span class="ilbl">No. MR</span><span class="ival">'+mr.mr_number+'</span></div>'+
        '<div class="irow"><span class="ilbl">Tanggal</span><span class="ival2">'+fmtD(mr.created_at)+'</span></div>'+
        '<div class="irow"><span class="ilbl">Diajukan Oleh</span><span class="ival">'+(mr.requester?.name||'-')+'</span></div>'+
        (mr.needed_date ? '<div class="irow"><span class="ilbl">Tgl. Dibutuhkan</span><span class="ival2">'+fmtD(mr.needed_date)+'</span></div>' : '')+
      '</div>'+
      '<div class="isec">'+
        '<div class="ititle">Persetujuan</div>'+
        '<div class="irow"><span class="ilbl">Disetujui Admin</span><span class="ival2">'+(mr.approver?.name||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Disetujui Atasan</span><span class="ival2">'+(mr.atasan_approver?.name||mr.atasanApprover?.name||'-')+'</span></div>'+
        (mr.notes ? '<div class="irow"><span class="ilbl">Catatan</span><span class="ival2">'+mr.notes+'</span></div>' : '')+
      '</div>'+
    '</div>'+
    '<table class="it">'+
      '<thead><tr>'+
        '<th style="text-align:center;width:36px">#</th>'+
        '<th style="text-align:center;width:110px">Part Number</th>'+
        '<th style="text-align:left">Nama Barang</th>'+
        '<th style="text-align:center;width:70px">Diminta</th>'+
        '<th style="text-align:center;width:70px">Disetujui</th>'+
        '<th style="text-align:center;width:65px">Satuan</th>'+
        '<th style="text-align:left">Keterangan</th>'+
      '</tr></thead>'+
      '<tbody>'+rows+'</tbody>'+
    '</table>'+
    '<div class="sgrid">'+
      '<div class="sbox"><div class="stitle">Dikirim Oleh</div><div class="sspace"></div><div class="sname"></div></div>'+
      '<div class="sbox"><div class="stitle">Driver</div><div class="sspace"></div><div class="sname"></div></div>'+
      '<div class="sbox"><div class="stitle">Diterima Oleh</div><div class="sspace"></div><div class="sname"></div></div>'+
      '<div class="sbox"><div class="stitle">Mengetahui</div><div class="sspace"></div><div class="sname"></div></div>'+
    '</div>'+
    bdC+htC

  const win = window.open('', '_blank', 'width=900,height=700')
  win.document.write(html)
  win.document.close()
  win.onload = () => { win.focus(); win.print() }
}
</script>