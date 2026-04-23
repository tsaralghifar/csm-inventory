<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;"><i class="bi bi-arrow-return-left me-2"></i>Retur Barang ke Vendor</h5>
        <small class="text-muted">Barang dikembalikan ke vendor atau ditandai sebagai salah beli</small>
      </div>
      <button class="btn btn-danger btn-sm" @click="openCreateModal">
        <i class="bi bi-plus-circle me-1"></i>Buat Retur Baru
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <input v-model="filters.search" @input="loadData" type="text" class="form-control form-control-sm"
              placeholder="🔍 Cari No. Retur..." />
          </div>
          <div class="col-md-2">
            <select v-model="filters.status" @change="loadData" class="form-select form-select-sm">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="confirmed">Dikonfirmasi</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_from" @change="loadData" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_to" @change="loadData" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary btn-sm w-100" @click="resetFilters">
              <i class="bi bi-x-circle me-1"></i>Reset
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center py-5"><div class="csm-spinner"></div></div>
        <div v-else-if="!returs.length" class="text-center py-5 text-muted">
          <i class="bi bi-arrow-return-left fs-1 opacity-25 d-block mb-2"></i>
          Belum ada data retur barang
        </div>
        <div v-else class="table-responsive">
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. Retur</th>
                <th>No. PO Asal</th>
                <th>Vendor</th>
                <th>Gudang</th>
                <th class="text-center">Jml Item</th>
                <th>Tgl Retur</th>
                <th>Status</th>
                <th>Dibuat Oleh</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in returs" :key="r.id">
                <td class="fw-semibold text-danger">{{ r.retur_number }}</td>
                <td><span class="badge bg-light text-dark border">{{ r.purchase_order?.po_number || '-' }}</span></td>
                <td><small>{{ r.vendor_name }}</small></td>
                <td><small>{{ r.warehouse?.name }}</small></td>
                <td class="text-center">
                  <span class="badge bg-secondary">{{ r.items_count }} item</span>
                </td>
                <td><small class="text-muted">{{ $formatDate(r.retur_date) }}</small></td>
                <td>
                  <span class="badge" :class="r.status === 'confirmed' ? 'bg-success' : 'bg-warning text-dark'">
                    {{ r.status === 'confirmed' ? '✓ Dikonfirmasi' : '⏳ Draft' }}
                  </span>
                </td>
                <td><small>{{ r.creator?.name }}</small></td>
                <td class="text-center">
                  <button class="btn btn-xs btn-outline-primary me-1" @click="viewDetail(r)">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button v-if="r.status === 'draft'" class="btn btn-xs btn-success" @click="confirmRetur(r)"
                    title="Konfirmasi: kurangi stok & tandai barang">
                    <i class="bi bi-check2-circle"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
          <small class="text-muted">Total {{ meta.total }} data</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹</button>
            <span class="btn btn-xs btn-primary disabled">{{ meta.page }}</span>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Buat Retur ===== -->
    <div class="modal fade" id="modalCreateRetur" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header" style="background:#991b1b;">
            <h6 class="modal-title text-white"><i class="bi bi-arrow-return-left me-2"></i>Buat Retur Barang</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">

            <!-- Step 1: Pilih PO -->
            <div class="mb-3">
              <label class="form-label small fw-semibold">1. Pilih PO Asal Pembelian <span class="text-danger">*</span></label>
              <div class="input-group input-group-sm">
                <input v-model="poSearch" @input="searchPO" type="text" class="form-control"
                  placeholder="Ketik No. PO atau nama vendor..." />
                <span class="input-group-text"><i class="bi bi-search"></i></span>
              </div>
              <!-- Hasil pencarian PO -->
              <div v-if="poResults.length" class="border rounded mt-1" style="max-height:180px;overflow-y:auto;">
                <div v-for="po in poResults" :key="po.id"
                  class="px-3 py-2 border-bottom cursor-pointer d-flex align-items-center justify-content-between"
                  style="cursor:pointer;"
                  :class="form.purchase_order_id === po.id ? 'bg-danger bg-opacity-10' : 'hover-light'"
                  @click="selectPO(po)">
                  <div>
                    <span class="fw-semibold text-primary">{{ po.po_number }}</span>
                    <span class="mx-2 text-muted">—</span>
                    <small>{{ po.vendor_name }}</small>
                    <span class="badge bg-light text-dark border ms-2 small">{{ po.warehouse?.name }}</span>
                  </div>
                  <small class="text-muted">{{ $formatDate(po.created_at) }}</small>
                </div>
              </div>
              <!-- PO terpilih -->
              <div v-if="selectedPO" class="alert alert-danger py-2 mt-2 small d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill"></i>
                <span>PO dipilih: <strong>{{ selectedPO.po_number }}</strong> — {{ selectedPO.vendor_name }} | {{ selectedPO.warehouse?.name }}</span>
                <button class="btn btn-xs btn-outline-danger ms-auto" @click="clearPO">Ganti</button>
              </div>
            </div>

            <!-- Form info retur -->
            <div v-if="selectedPO" class="row g-2 mb-3">
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Vendor (dari PO)</label>
                <input v-model="form.vendor_name" type="text" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Kontak Vendor</label>
                <input v-model="form.vendor_contact" type="text" class="form-control form-control-sm" placeholder="Opsional" />
              </div>
              <div class="col-md-2">
                <label class="form-label small fw-semibold">Tanggal Retur <span class="text-danger">*</span></label>
                <input v-model="form.retur_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-2">
                <label class="form-label small fw-semibold">Gudang Sumber <span class="text-danger">*</span></label>
                <select v-model="form.warehouse_id" class="form-select form-select-sm">
                  <option value="">-- Pilih --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Alasan Umum Retur</label>
                <input v-model="form.alasan" type="text" class="form-control form-control-sm" placeholder="Contoh: Salah spesifikasi, barang tidak sesuai PO..." />
              </div>
            </div>

            <!-- Tabel item dari PO -->
            <div v-if="selectedPO && form.items.length">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label small fw-semibold mb-0">2. Pilih Item yang Diretur</label>
                <small class="text-muted">Centang item yang akan diretur, isi qty dan pilih jenis retur</small>
              </div>
              <div class="table-responsive">
                <table class="table table-sm csm-table mb-0">
                  <thead>
                    <tr>
                      <th style="width:36px" class="text-center">
                        <input type="checkbox" class="form-check-input"
                          :checked="form.items.every(i=>i.selected)"
                          @change="e => form.items.forEach(i => i.selected = e.target.checked)" />
                      </th>
                      <th>Part Number</th>
                      <th>Nama Barang</th>
                      <th class="text-center">Qty PO</th>
                      <th class="text-center" style="width:110px">Qty Retur</th>
                      <th style="width:60px">Satuan</th>
                      <th style="width:200px">Jenis Retur</th>
                      <th>Alasan per Item</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, idx) in form.items" :key="idx"
                      :class="item.selected ? '' : 'table-light opacity-50'">
                      <td class="text-center">
                        <input type="checkbox" class="form-check-input" v-model="item.selected" />
                      </td>
                      <td>
                        <code v-if="item.part_number" class="small text-primary fw-semibold">{{ item.part_number }}</code>
                        <span v-else class="text-muted small">-</span>
                      </td>
                      <td class="fw-semibold small">{{ item.nama_barang }}</td>
                      <td class="text-center small text-muted">{{ item.qty_po }}</td>
                      <td>
                        <input v-model="item.qty" type="number"
                          class="form-control form-control-sm text-center"
                          :disabled="!item.selected"
                          min="0.01" :max="item.qty_po" step="0.01" />
                      </td>
                      <td class="text-center small">{{ item.satuan }}</td>
                      <td>
                        <select v-model="item.jenis" class="form-select form-select-sm"
                          :disabled="!item.selected"
                          :class="item.jenis === 'returnable' ? 'border-success text-success' : 'border-warning text-warning'">
                          <option value="returnable">↩ Retur ke Vendor</option>
                          <option value="non_returnable">⚠ Salah Beli (tdk bisa retur)</option>
                        </select>
                      </td>
                      <td>
                        <input v-model="item.alasan_item" type="text"
                          class="form-control form-control-sm"
                          :disabled="!item.selected"
                          :placeholder="item.jenis === 'returnable' ? 'Alasan retur...' : 'Alasan salah beli...'" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Legenda -->
              <div class="d-flex gap-3 mt-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-success">↩ Retur ke Vendor</span>
                  <small class="text-muted">Stok akan dikurangi saat dikonfirmasi</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-warning text-dark">⚠ Salah Beli</span>
                  <small class="text-muted">Barang ditandai "salah beli" di master data, stok tetap</small>
                </div>
              </div>
            </div>

            <div v-if="selectedPO && !form.items.length" class="alert alert-warning small py-2 mt-2">
              <i class="bi bi-exclamation-triangle me-1"></i>
              PO ini tidak memiliki item yang terdaftar.
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger btn-sm" @click="saveRetur" :disabled="saving || !selectedPO">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-arrow-return-left me-1"></i>Buat Retur (Draft)
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Detail Retur ===== -->
    <div class="modal fade" id="modalDetailRetur" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="detailRetur">
          <div class="modal-header" style="background:#1a3a5c;">
            <h6 class="modal-title text-white">
              <i class="bi bi-arrow-return-left me-2"></i>{{ detailRetur.retur_number }}
              <span class="badge ms-2" :class="detailRetur.status === 'confirmed' ? 'bg-success' : 'bg-warning text-dark'">
                {{ detailRetur.status === 'confirmed' ? 'Dikonfirmasi' : 'Draft' }}
              </span>
            </h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Info -->
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted" width="40%">No. PO Asal</td><td class="fw-semibold text-primary">{{ detailRetur.purchase_order?.po_number }}</td></tr>
                  <tr><td class="text-muted">Vendor</td><td>{{ detailRetur.vendor_name }}</td></tr>
                  <tr><td class="text-muted">Gudang</td><td>{{ detailRetur.warehouse?.name }}</td></tr>
                  <tr><td class="text-muted">Tanggal Retur</td><td>{{ $formatDate(detailRetur.retur_date) }}</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted" width="40%">Dibuat Oleh</td><td>{{ detailRetur.creator?.name }}</td></tr>
                  <tr v-if="detailRetur.confirmed_by"><td class="text-muted">Dikonfirmasi</td><td>{{ detailRetur.confirmer?.name }} — {{ $formatDate(detailRetur.confirmed_at) }}</td></tr>
                  <tr><td class="text-muted">Alasan</td><td>{{ detailRetur.alasan || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Item -->
            <table class="table table-sm csm-table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nama Barang</th>
                  <th class="text-center">Qty</th>
                  <th>Satuan</th>
                  <th>Jenis</th>
                  <th>Alasan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in detailRetur.items" :key="item.id">
                  <td class="text-muted small">{{ idx + 1 }}</td>
                  <td>
                    <div class="fw-semibold small">{{ item.nama_barang }}</div>
                    <code v-if="item.part_number" class="small text-muted">{{ item.part_number }}</code>
                  </td>
                  <td class="text-center fw-bold">{{ item.qty }}</td>
                  <td><span class="badge bg-light text-dark border">{{ item.satuan }}</span></td>
                  <td>
                    <span v-if="item.jenis === 'returnable'" class="badge bg-success">↩ Retur ke Vendor</span>
                    <span v-else class="badge bg-warning text-dark">⚠ Salah Beli</span>
                  </td>
                  <td><small class="text-muted">{{ item.alasan_item || '-' }}</small></td>
                </tr>
              </tbody>
            </table>

            <!-- Info akibat konfirmasi -->
            <div v-if="detailRetur.status === 'draft'" class="alert alert-info small py-2 mt-3">
              <i class="bi bi-info-circle me-1"></i>
              Klik <strong>Konfirmasi Retur</strong> untuk memproses:
              item <em>Retur ke Vendor</em> akan dikurangi dari stok,
              item <em>Salah Beli</em> akan ditandai di master barang.
            </div>
            <div v-else class="alert alert-success small py-2 mt-3">
              <i class="bi bi-check-circle me-1"></i>
              Retur sudah dikonfirmasi. Stok dan data master telah diperbarui.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button v-if="detailRetur.status === 'draft'" type="button" class="btn btn-success btn-sm"
              @click="confirmRetur(detailRetur)" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-check2-circle me-1"></i>Konfirmasi Retur
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
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'

const toast = useToast()
const { listenRetur, stopRetur } = useRealtime()

const returs    = ref([])
const loading   = ref(false)
const saving    = ref(false)
const acting    = ref(false)
const warehouses = ref([])
const meta      = ref({ total: 0, page: 1, last_page: 1 })

const filters = ref({ search: '', status: '', date_from: '', date_to: '' })

// PO search
const poSearch  = ref('')
const poResults = ref([])
const selectedPO = ref(null)

// Detail modal
const detailRetur = ref(null)

// Form
const form = ref({
  purchase_order_id: null,
  warehouse_id: '',
  vendor_name: '',
  vendor_contact: '',
  retur_date: new Date().toISOString().split('T')[0],
  alasan: '',
  notes: '',
  items: [],
})

onMounted(async () => {
  const res = await axios.get('/warehouses')
  warehouses.value = res.data.data
  loadData()
  listenRetur(() => loadData())
})

onUnmounted(() => stopRetur())

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/retur-barang', { params: { ...filters.value, page: meta.value.page } })
    returs.value = res.data.data
    meta.value   = res.data.meta
  } catch { toast.error('Gagal memuat data') } finally { loading.value = false }
}

function resetFilters() {
  filters.value = { search: '', status: '', date_from: '', date_to: '' }
  meta.value.page = 1
  loadData()
}

function changePage(p) {
  meta.value.page = p
  loadData()
}

// Cari PO
let poTimer = null
async function searchPO() {
  clearTimeout(poTimer)
  if (poSearch.value.length < 2) { poResults.value = []; return }
  poTimer = setTimeout(async () => {
    try {
      const res = await axios.get('/purchase-orders', {
        params: { search: poSearch.value, per_page: 8 }
      })
      poResults.value = res.data.data
    } catch {}
  }, 300)
}

async function selectPO(po) {
  // Load detail PO untuk ambil items
  try {
    const res = await axios.get(`/purchase-orders/${po.id}`)
    const detail = res.data.data
    selectedPO.value = detail
    form.value.purchase_order_id = detail.id
    form.value.vendor_name   = detail.vendor_name
    form.value.vendor_contact = detail.vendor_contact || ''
    form.value.warehouse_id  = detail.warehouse?.id || ''
    poResults.value = []
    poSearch.value  = ''

    // Populate items dari PO
    form.value.items = (detail.items || []).map(i => ({
      selected: false,
      item_id:                i.item_id ?? null,
      purchase_order_item_id: i.id,
      part_number:            i.part_number ?? i.item?.part_number ?? null,
      nama_barang:            i.nama_barang,
      kode_unit:              i.kode_unit ?? null,
      tipe_unit:              i.tipe_unit ?? null,
      qty_po:                 parseFloat(i.qty),
      qty:                    parseFloat(i.qty),
      satuan:                 i.satuan,
      harga_satuan:           parseFloat(i.harga_satuan || 0),
      jenis:                  'returnable',
      alasan_item:            '',
    }))
  } catch { toast.error('Gagal memuat data PO') }
}

function clearPO() {
  selectedPO.value = null
  form.value.purchase_order_id = null
  form.value.items = []
  poSearch.value = ''
}

function openCreateModal() {
  clearPO()
  form.value = {
    purchase_order_id: null, warehouse_id: '', vendor_name: '',
    vendor_contact: '', retur_date: new Date().toISOString().split('T')[0],
    alasan: '', notes: '', items: [],
  }
  new Modal('#modalCreateRetur').show()
}

async function saveRetur() {
  if (!form.value.purchase_order_id) return toast.error('Pilih PO asal pembelian')
  if (!form.value.warehouse_id)      return toast.error('Pilih gudang sumber')
  if (!form.value.retur_date)        return toast.error('Isi tanggal retur')
  const selectedItems = form.value.items.filter(i => i.selected)
  if (!selectedItems.length)         return toast.error('Pilih minimal satu item yang diretur')

  // Validasi qty
  for (const item of selectedItems) {
    if (!item.qty || item.qty <= 0) return toast.error(`Isi qty retur untuk "${item.nama_barang}"`)
    if (item.qty > item.qty_po)     return toast.error(`Qty retur "${item.nama_barang}" melebihi qty PO (${item.qty_po})`)
  }

  saving.value = true
  try {
    await axios.post('/retur-barang', {
      purchase_order_id: form.value.purchase_order_id,
      warehouse_id:      form.value.warehouse_id,
      vendor_name:       form.value.vendor_name,
      vendor_contact:    form.value.vendor_contact,
      retur_date:        form.value.retur_date,
      alasan:            form.value.alasan,
      notes:             form.value.notes,
      items: selectedItems.map(i => ({
        item_id:                i.item_id,
        purchase_order_item_id: i.purchase_order_item_id,
        nama_barang:            i.nama_barang,
        part_number:            i.part_number,
        kode_unit:              i.kode_unit,
        tipe_unit:              i.tipe_unit,
        qty:                    i.qty,
        satuan:                 i.satuan,
        harga_satuan:           i.harga_satuan,
        jenis:                  i.jenis,
        alasan_item:            i.alasan_item,
      })),
    })
    toast.success('Retur berhasil dibuat (Draft). Konfirmasi untuk memproses stok.')
    Modal.getInstance('#modalCreateRetur')?.hide()
    loadData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal membuat retur')
  } finally { saving.value = false }
}

async function viewDetail(r) {
  try {
    const res = await axios.get(`/retur-barang/${r.id}`)
    detailRetur.value = res.data.data
    new Modal('#modalDetailRetur').show()
  } catch { toast.error('Gagal memuat detail') }
}

async function confirmRetur(r) {
  const msg = 'Konfirmasi retur ini?\n\n• Item "Retur ke Vendor" → stok dikurangi\n• Item "Salah Beli" → ditandai di master barang'
  if (!confirm(msg)) return
  acting.value = true
  try {
    await axios.post(`/retur-barang/${r.id}/confirm`)
    toast.success('Retur dikonfirmasi. Stok dan data master telah diperbarui.')
    Modal.getInstance('#modalDetailRetur')?.hide()
    loadData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal konfirmasi retur')
  } finally { acting.value = false }
}
</script>