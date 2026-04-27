<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-1" style="color:#1a3a5c;">Stok Opname / Penyesuaian Stok</h5>
        <small class="text-muted">Kelola penyesuaian stok dengan jejak dan persetujuan</small>
      </div>
      <button class="btn btn-success btn-sm" @click="openBuat()">
        <i class="bi bi-plus-circle me-1"></i>Buat Dokumen
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari nomor dokumen..." @input="debouncedLoad" />
          </div>
          <div class="col-6 col-md-2">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="menunggu_approval">Menunggu Approval</option>
              <option value="disetujui">Disetujui</option>
              <option value="ditolak">Ditolak</option>
            </select>
          </div>
          <div class="col-6 col-md-2" v-if="isSuperuserOrAdmin">
            <select v-model="filters.warehouse_id" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Gudang</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-6 col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-select-sm" @change="loadData" />
          </div>
          <div class="col-auto">
            <button class="btn btn-outline-secondary btn-sm" @click="resetFilters">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center py-5"><span class="csm-spinner"></span></div>
        <div v-else-if="!data.length" class="text-center py-5 text-muted">
          <i class="bi bi-clipboard-x fs-2 d-block mb-2"></i>Belum ada dokumen stok opname
        </div>
        <div v-else class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>NO. DOKUMEN</th>
                <th>GUDANG</th>
                <th>TIPE</th>
                <th>NO. REFERENSI</th>
                <th>TGL OPNAME</th>
                <th>ITEM</th>
                <th>STATUS</th>
                <th>DIBUAT OLEH</th>
                <th>AKSI</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in data" :key="d.id">
                <td><a href="#" class="text-primary fw-semibold small" @click.prevent="openDetail(d)">{{ d.nomor }}</a></td>
                <td class="small">{{ d.warehouse?.name }}</td>
                <td class="small">{{ d.tipe }}</td>
                <td class="small">{{ d.no_referensi }}</td>
                <td class="small">{{ formatDate(d.tanggal_opname) }}</td>
                <td><span class="badge bg-secondary">{{ d.items_count }} item</span></td>
                <td><span :class="statusClass(d.status)">{{ statusLabel(d.status) }}</span></td>
                <td class="small">{{ d.dibuat_oleh?.name }}</td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-outline-primary btn-sm" title="Detail" @click="openDetail(d)"><i class="bi bi-eye"></i></button>
                    <button v-if="d.status === 'draft'" class="btn btn-outline-warning btn-sm" title="Edit" @click="openEdit(d)"><i class="bi bi-pencil"></i></button>
                    <button v-if="d.status === 'draft'" class="btn btn-outline-info btn-sm" title="Ajukan" @click="ajukan(d)"><i class="bi bi-send"></i></button>
                    <button v-if="d.status === 'menunggu_approval' && isSuperuserOrAdmin" class="btn btn-outline-success btn-sm" title="Setujui" @click="setujui(d)"><i class="bi bi-check-lg"></i></button>
                    <button v-if="d.status === 'menunggu_approval' && isSuperuserOrAdmin" class="btn btn-outline-danger btn-sm" title="Tolak" @click="openTolak(d)"><i class="bi bi-x-lg"></i></button>
                    <button v-if="d.status === 'draft'" class="btn btn-outline-danger btn-sm" title="Hapus" @click="hapus(d)"><i class="bi bi-trash"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
          <small class="text-muted">{{ meta.total }} dokumen</small>
          <div class="d-flex gap-1">
            <button class="btn btn-outline-secondary btn-sm" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
            <span class="btn btn-sm disabled">{{ meta.page }} / {{ meta.last_page }}</span>
            <button class="btn btn-outline-secondary btn-sm" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════ MODAL BUAT / EDIT ═══════════════ -->
    <div class="modal fade" id="modalBuat" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title fw-bold">{{ editId ? 'Edit Dokumen' : 'Buat Dokumen Stok Opname' }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4" v-if="isSuperuserOrAdmin">
                <label class="form-label small fw-semibold">Gudang <span class="text-danger">*</span></label>
                <select v-model="form.warehouse_id" class="form-select" @change="loadStockForWarehouse">
                  <option value="">-- Pilih Gudang --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Tipe Penyesuaian <span class="text-danger">*</span></label>
                <select v-model="form.tipe" class="form-select">
                  <option value="">-- Pilih Tipe --</option>
                  <option value="Koreksi Opname">Koreksi Opname</option>
                  <option value="Retur dari Site">Retur dari Site</option>
                  <option value="Temuan Stok">Temuan Stok</option>
                  <option value="Pembelian Langsung">Pembelian Langsung</option>
                  <option value="Koreksi Sistem">Koreksi Sistem</option>
                  <option value="Lainnya">Lainnya</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">No. Referensi Dokumen <span class="text-danger">*</span></label>
                <input v-model="form.no_referensi" type="text" class="form-control" placeholder="Cth: BA-OPNAME-001" />
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Tanggal Opname <span class="text-danger">*</span></label>
                <input v-model="form.tanggal_opname" type="date" class="form-control" />
              </div>
              <div class="col-md-8">
                <label class="form-label small fw-semibold">Keterangan</label>
                <input v-model="form.keterangan" type="text" class="form-control" placeholder="Keterangan tambahan..." />
              </div>
            </div>

            <!-- Tambah Barang -->
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-semibold small">Daftar Barang</span>
              <div class="d-flex gap-2 align-items-center" style="max-width:400px">
                <input v-model="itemSearch" class="form-control form-control-sm" placeholder="Cari barang..." @input="searchItems" />
              </div>
            </div>

            <!-- Dropdown hasil cari -->
            <div v-if="itemResults.length" class="border rounded mb-2" style="max-height:180px;overflow-y:auto">
              <div v-for="it in itemResults" :key="it.id"
                class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center"
                style="cursor:pointer" @click="addItem(it)"
                :class="{'bg-light': form.items.find(r=>r.item_id===it.id)}">
                <div>
                  <span class="fw-semibold small">{{ it.name }}</span>
                  <span class="text-muted small ms-2">{{ it.part_number }}</span>
                </div>
                <span class="badge bg-secondary">Stok: {{ getStokItem(it.id) }}</span>
              </div>
            </div>

            <!-- Tabel items -->
            <div class="table-responsive" v-if="form.items.length">
              <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th>BARANG</th>
                    <th class="text-center" style="width:110px">QTY SISTEM</th>
                    <th class="text-center" style="width:130px">QTY FISIK <span class="text-danger">*</span></th>
                    <th class="text-center" style="width:110px">SELISIH</th>
                    <th>KETERANGAN</th>
                    <th style="width:40px"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in form.items" :key="i">
                    <td>
                      <div class="fw-semibold small">{{ row.item_name }}</div>
                      <div class="text-muted" style="font-size:11px">{{ row.part_number }}</div>
                    </td>
                    <td class="text-center small">{{ row.qty_sistem }}</td>
                    <td>
                      <input v-model.number="row.qty_fisik" type="number" class="form-control form-control-sm text-center" min="0" step="0.01" />
                    </td>
                    <td class="text-center fw-semibold small" :class="selisihClass(row.qty_fisik - row.qty_sistem)">
                      {{ selisihLabel(row.qty_fisik - row.qty_sistem) }}
                    </td>
                    <td>
                      <input v-model="row.keterangan" type="text" class="form-control form-control-sm" placeholder="Keterangan..." />
                    </td>
                    <td class="text-center">
                      <button class="btn btn-outline-danger btn-sm" @click="removeItem(i)"><i class="bi bi-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-center py-3 text-muted small border rounded">Belum ada barang ditambahkan. Cari dan pilih barang di atas.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm" @click="simpan" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>Simpan Draft
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════ MODAL DETAIL ═══════════════ -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content" v-if="detail">
          <div class="modal-header">
            <div>
              <h6 class="modal-title fw-bold mb-0">{{ detail.nomor }}</h6>
              <small class="text-muted">{{ detail.tipe }} — Ref: {{ detail.no_referensi }}</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Info row -->
            <div class="row g-2 mb-3">
              <div class="col-md-3">
                <div class="csm-card h-100"><div class="csm-card-body py-2">
                  <div class="text-muted small">Gudang</div>
                  <div class="fw-semibold small">{{ detail.warehouse?.name }}</div>
                </div></div>
              </div>
              <div class="col-md-3">
                <div class="csm-card h-100"><div class="csm-card-body py-2">
                  <div class="text-muted small">Tanggal Opname</div>
                  <div class="fw-semibold small">{{ formatDate(detail.tanggal_opname) }}</div>
                </div></div>
              </div>
              <div class="col-md-3">
                <div class="csm-card h-100"><div class="csm-card-body py-2">
                  <div class="text-muted small">Dibuat Oleh</div>
                  <div class="fw-semibold small">{{ detail.dibuat_oleh?.name }}</div>
                </div></div>
              </div>
              <div class="col-md-3">
                <div class="csm-card h-100"><div class="csm-card-body py-2">
                  <div class="text-muted small">Status</div>
                  <span :class="statusClass(detail.status)">{{ statusLabel(detail.status) }}</span>
                </div></div>
              </div>
              <div class="col-12" v-if="detail.keterangan">
                <div class="alert alert-light py-2 mb-0 small"><i class="bi bi-info-circle me-1"></i>{{ detail.keterangan }}</div>
              </div>
              <div class="col-12" v-if="detail.alasan_penolakan">
                <div class="alert alert-danger py-2 mb-0 small"><i class="bi bi-x-circle me-1"></i><strong>Ditolak:</strong> {{ detail.alasan_penolakan }}</div>
              </div>
              <div class="col-12" v-if="detail.status === 'disetujui'">
                <div class="alert alert-success py-2 mb-0 small">
                  <i class="bi bi-check-circle me-1"></i>
                  Disetujui oleh <strong>{{ detail.disetujui_oleh?.name }}</strong> pada {{ formatDatetime(detail.disetujui_at) }}. Stok sudah disesuaikan.
                </div>
              </div>
            </div>

            <!-- Tabel items -->
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>BARANG</th>
                    <th class="text-center">QTY SISTEM</th>
                    <th class="text-center">QTY FISIK</th>
                    <th class="text-center">SELISIH</th>
                    <th>KETERANGAN</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(it, i) in detail.items" :key="it.id">
                    <td class="text-muted small">{{ i+1 }}</td>
                    <td>
                      <div class="fw-semibold small">{{ it.item?.name }}</div>
                      <div class="text-muted" style="font-size:11px">{{ it.item?.part_number }}</div>
                    </td>
                    <td class="text-center small">{{ it.qty_sistem }}</td>
                    <td class="text-center small fw-semibold">{{ it.qty_fisik }}</td>
                    <td class="text-center small fw-bold" :class="selisihClass(it.selisih)">
                      {{ selisihLabel(it.selisih) }}
                    </td>
                    <td class="small">{{ it.keterangan || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button v-if="detail.status === 'draft'" class="btn btn-info btn-sm" @click="ajukan(detail)">
              <i class="bi bi-send me-1"></i>Ajukan Persetujuan
            </button>
            <template v-if="detail.status === 'menunggu_approval' && isSuperuserOrAdmin">
              <button class="btn btn-danger btn-sm" @click="openTolak(detail)">
                <i class="bi bi-x-lg me-1"></i>Tolak
              </button>
              <button class="btn btn-success btn-sm" @click="setujui(detail)" :disabled="saving">
                <span v-if="saving" class="csm-spinner me-1"></span>
                <i v-else class="bi bi-check-lg me-1"></i>Setujui & Terapkan Stok
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════ MODAL TOLAK ═══════════════ -->
    <div class="modal fade" id="modalTolak" tabindex="-1">
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title fw-bold text-danger">Tolak Dokumen</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea v-model="alasanTolak" class="form-control" rows="3" placeholder="Tuliskan alasan penolakan..."></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger btn-sm" @click="tolak" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>Tolak Dokumen
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const auth  = useAuthStore()
const toast = useToast()

const isSuperuserOrAdmin = computed(() => auth.isSuperuser || auth.isAdminHO)

// ── State ──────────────────────────────────────────────────────────────────
const data      = ref([])
const meta      = ref({ total: 0, page: 1, last_page: 1 })
const loading   = ref(false)
const saving    = ref(false)
const warehouses = ref([])
const allItems  = ref([])
const warehouseStocks = ref([]) // stok per gudang yang dipilih

const filters = ref({ search: '', status: '', warehouse_id: '', date_from: '', date_to: '' })

// Form buat/edit
const editId    = ref(null)
const form      = ref(defaultForm())
const itemSearch = ref('')
const itemResults = ref([])
let itemTimer = null

// Detail
const detail = ref(null)

// Tolak
const tolakTarget = ref(null)
const alasanTolak = ref('')

// ── Helpers ────────────────────────────────────────────────────────────────
function defaultForm() {
  return {
    warehouse_id: auth.userWarehouseId || '',
    tipe: '',
    no_referensi: '',
    tanggal_opname: new Date().toISOString().split('T')[0],
    keterangan: '',
    items: [],
  }
}

function statusLabel(s) {
  return { draft: 'Draft', menunggu_approval: 'Menunggu Approval', disetujui: 'Disetujui', ditolak: 'Ditolak' }[s] || s
}

function statusClass(s) {
  return {
    draft: 'badge bg-secondary',
    menunggu_approval: 'badge bg-warning text-dark',
    disetujui: 'badge bg-success',
    ditolak: 'badge bg-danger',
  }[s] || 'badge bg-secondary'
}

function selisihClass(val) {
  const n = parseFloat(val) || 0
  if (n > 0) return 'text-success'
  if (n < 0) return 'text-danger'
  return 'text-muted'
}

function selisihLabel(val) {
  const n = parseFloat(val) || 0
  if (n === 0) return '0'
  return (n > 0 ? '+' : '') + n
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDatetime(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function getStokItem(itemId) {
  const s = warehouseStocks.value.find(s => s.item_id === itemId)
  return s ? s.qty : 0
}

// ── Load Data ──────────────────────────────────────────────────────────────
async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/stok-opname', { params: { ...filters.value, page: meta.value.page, per_page: 15 } })
    data.value = res.data.data
    meta.value = res.data.meta
  } catch { toast.error('Gagal memuat data') }
  finally { loading.value = false }
}

let searchTimer = null
function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadData, 400)
}

function changePage(p) { meta.value.page = p; loadData() }

function resetFilters() {
  filters.value = { search: '', status: '', warehouse_id: '', date_from: '', date_to: '' }
  meta.value.page = 1
  loadData()
}

async function loadWarehouses() {
  const res = await axios.get('/warehouses')
  warehouses.value = res.data.data
}

async function loadAllItems() {
  const res = await axios.get('/items', { params: { per_page: 999 } })
  allItems.value = res.data.data
}

async function loadStockForWarehouse() {
  if (!form.value.warehouse_id) { warehouseStocks.value = []; return }
  try {
    const res = await axios.get(`/warehouses/${form.value.warehouse_id}/stocks`, { params: { per_page: 999 } })
    warehouseStocks.value = res.data.data
  } catch { warehouseStocks.value = [] }
}

// ── Search Items ───────────────────────────────────────────────────────────
function searchItems() {
  clearTimeout(itemTimer)
  const q = itemSearch.value.trim().toLowerCase()
  if (!q) { itemResults.value = []; return }
  itemTimer = setTimeout(() => {
    itemResults.value = allItems.value
      .filter(it => it.name?.toLowerCase().includes(q) || it.part_number?.toLowerCase().includes(q))
      .slice(0, 10)
  }, 200)
}

function addItem(it) {
  if (form.value.items.find(r => r.item_id === it.id)) {
    toast.warning('Barang sudah ada di daftar')
    return
  }
  const stokSkg = getStokItem(it.id)
  form.value.items.push({
    item_id:     it.id,
    item_name:   it.name,
    part_number: it.part_number,
    qty_sistem:  stokSkg,
    qty_fisik:   stokSkg,
    keterangan:  '',
  })
  itemSearch.value = ''
  itemResults.value = []
}

function removeItem(i) { form.value.items.splice(i, 1) }

// ── CRUD ───────────────────────────────────────────────────────────────────
function openBuat() {
  editId.value = null
  form.value   = defaultForm()
  itemSearch.value = ''
  itemResults.value = []
  if (!isSuperuserOrAdmin.value) {
    form.value.warehouse_id = auth.userWarehouseId
    loadStockForWarehouse()
  }
  new Modal('#modalBuat').show()
}

async function openEdit(d) {
  editId.value = d.id
  const res = await axios.get(`/stok-opname/${d.id}`)
  const doc = res.data.data
  form.value = {
    warehouse_id:    doc.warehouse_id,
    tipe:            doc.tipe,
    no_referensi:    doc.no_referensi,
    tanggal_opname:  doc.tanggal_opname?.split('T')[0] || doc.tanggal_opname,
    keterangan:      doc.keterangan || '',
    items: doc.items.map(it => ({
      item_id:     it.item_id,
      item_name:   it.item?.name,
      part_number: it.item?.part_number,
      qty_sistem:  parseFloat(it.qty_sistem),
      qty_fisik:   parseFloat(it.qty_fisik),
      keterangan:  it.keterangan || '',
    })),
  }
  await loadStockForWarehouse()
  itemSearch.value = ''
  itemResults.value = []
  new Modal('#modalBuat').show()
}

async function simpan() {
  if (!form.value.warehouse_id) return toast.error('Pilih gudang')
  if (!form.value.tipe) return toast.error('Pilih tipe penyesuaian')
  if (!form.value.no_referensi?.trim()) return toast.error('No. Referensi wajib diisi')
  if (!form.value.tanggal_opname) return toast.error('Tanggal wajib diisi')
  if (!form.value.items.length) return toast.error('Tambahkan minimal 1 barang')

  saving.value = true
  try {
    const payload = {
      ...form.value,
      items: form.value.items.map(r => ({
        item_id:    r.item_id,
        qty_fisik:  r.qty_fisik,
        keterangan: r.keterangan,
      })),
    }
    if (editId.value) {
      await axios.put(`/stok-opname/${editId.value}`, payload)
      toast.success('Dokumen berhasil diperbarui')
    } else {
      await axios.post('/stok-opname', payload)
      toast.success('Dokumen berhasil dibuat')
    }
    Modal.getInstance('#modalBuat')?.hide()
    loadData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan')
  } finally { saving.value = false }
}

async function openDetail(d) {
  const res = await axios.get(`/stok-opname/${d.id}`)
  detail.value = res.data.data
  new Modal('#modalDetail').show()
}

async function ajukan(d) {
  if (!confirm(`Ajukan dokumen ${d.nomor} untuk persetujuan?`)) return
  try {
    await axios.post(`/stok-opname/${d.id}/ajukan`)
    toast.success('Dokumen diajukan')
    loadData()
    if (detail.value?.id === d.id) {
      const res = await axios.get(`/stok-opname/${d.id}`)
      detail.value = res.data.data
    }
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal mengajukan') }
}

async function setujui(d) {
  if (!confirm(`Setujui dokumen ${d.nomor}? Stok akan langsung disesuaikan.`)) return
  saving.value = true
  try {
    await axios.post(`/stok-opname/${d.id}/setujui`)
    toast.success('Dokumen disetujui, stok sudah disesuaikan')
    Modal.getInstance('#modalDetail')?.hide()
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyetujui') }
  finally { saving.value = false }
}

function openTolak(d) {
  tolakTarget.value = d
  alasanTolak.value = ''
  new Modal('#modalTolak').show()
}

async function tolak() {
  if (!alasanTolak.value.trim()) return toast.error('Alasan penolakan wajib diisi')
  saving.value = true
  try {
    await axios.post(`/stok-opname/${tolakTarget.value.id}/tolak`, { alasan_penolakan: alasanTolak.value })
    toast.success('Dokumen ditolak')
    Modal.getInstance('#modalTolak')?.hide()
    Modal.getInstance('#modalDetail')?.hide()
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menolak') }
  finally { saving.value = false }
}

async function hapus(d) {
  if (!confirm(`Hapus dokumen ${d.nomor}?`)) return
  try {
    await axios.delete(`/stok-opname/${d.id}`)
    toast.success('Dokumen dihapus')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menghapus') }
}

// ── Init ───────────────────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([loadWarehouses(), loadAllItems()])
  if (!isSuperuserOrAdmin.value) {
    filters.value.warehouse_id = auth.userWarehouseId
  }
  loadData()
})
</script>
