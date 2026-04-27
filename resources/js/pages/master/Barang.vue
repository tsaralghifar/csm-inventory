<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Master Barang / Sparepart</h5>
        <small class="text-muted">Kelola semua data barang dan sparepart</small>
      </div>
      <button v-if="can('manage-items')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Tambah Barang
      </button>
    </div>

    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-4">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari nama / part number..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="filters.category_id" class="form-select form-select-sm" @change="load">
              <option value="">Semua Kategori</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary btn-sm" @click="resetFilters">Reset</button>
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
                <th>#</th>
                <th>Part Number</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Brand</th>
                <th>Satuan</th>
                <th class="text-end">Stok Min</th>
                <th class="text-end">Harga</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!items.length"><td colspan="10" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="(item,i) in items" :key="item.id">
                <td class="text-muted small">{{ (meta.page-1)*20+i+1 }}</td>
                <td><code class="text-primary small">{{ item.part_number }}</code></td>
                <td>
                  <div class="fw-semibold">{{ item.name }}</div>
                  <small class="text-muted" v-if="item.description">{{ item.description?.substring(0,40) }}</small>
                </td>
                <td><span class="badge bg-light text-dark border">{{ item.category?.name }}</span></td>
                <td><small>{{ item.brand || '-' }}</small></td>
                <td><small>{{ item.unit }}</small></td>
                <td class="text-end"><small>{{ $formatNumber(item.min_stock) }}</small></td>
                <td class="text-end"><small>{{ item.price > 0 ? $formatCurrency(item.price) : '-' }}</small></td>
                <td>
                  <span :class="item.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td class="text-center">
                  <div class="d-flex gap-1 justify-content-center">
                    <button class="btn btn-xs btn-outline-warning" title="Riwayat Harga" @click="openPriceHistory(item)"><i class="bi bi-clock-history"></i></button>
                    <button v-if="can('manage-items')" class="btn btn-xs btn-outline-primary" title="Edit" @click="openModal(item)"><i class="bi bi-pencil"></i></button>
                    <button v-if="can('manage-items')" class="btn btn-xs btn-outline-danger" title="Hapus" @click="deleteItem(item)"><i class="bi bi-trash"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">{{ meta.total }} data, halaman {{ meta.page }} dari {{ meta.last_page }}</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="barangModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.id ? 'Edit Barang' : 'Tambah Barang Baru' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Part Number <span class="text-danger">*</span></label>
                <input v-model="form.part_number" class="form-control form-control-sm" :disabled="!!form.id" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Nama Barang <span class="text-danger">*</span></label>
                <input v-model="form.name" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Kategori <span class="text-danger">*</span></label>
                <select v-model="form.category_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Kategori --</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Brand</label>
                <input v-model="form.brand" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Satuan <span class="text-danger">*</span></label>
                <select v-model="form.unit" class="form-select form-select-sm">
                  <option v-for="u in units" :key="u" :value="u">{{ u }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Stok Minimum</label>
                <input v-model.number="form.min_stock" type="number" min="0" step="0.01" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Harga (Rp)</label>
                <input v-model.number="form.price" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Deskripsi</label>
                <textarea v-model="form.description" class="form-control form-control-sm" rows="2"></textarea>
              </div>
              <div class="col-12" v-if="form.id">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" v-model="form.is_active" id="isActive">
                  <label class="form-check-label small" for="isActive">Barang Aktif</label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="save" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span>Menyimpan...</span>
              <span v-else>{{ form.id ? 'Perbarui' : 'Simpan' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
    <!-- Modal Riwayat Harga -->
    <div class="modal fade" id="modalPriceHistory" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h6 class="modal-title mb-0"><i class="bi bi-clock-history text-warning me-2"></i>Riwayat Harga — {{ selectedItem?.name }}</h6>
              <small class="text-muted">Part Number: {{ selectedItem?.part_number }}</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0">
            <!-- Summary -->
            <div class="d-flex gap-3 p-3 border-bottom bg-light flex-wrap">
              <div class="text-center px-3">
                <div class="fw-bold text-primary fs-6">{{ priceHistory.length > 0 ? $formatCurrency(priceHistory.reduce((sum,h) => sum + parseFloat(h.purchase_price), 0) / priceHistory.length) : '-' }}</div>
                <small class="text-muted">Harga Rata-rata Saat Ini</small>
              </div>
              <div class="text-center px-3 border-start">
                <div class="fw-bold text-success fs-6">{{ priceHistory.length > 0 ? $formatCurrency(Math.min(...priceHistory.map(h => parseFloat(h.purchase_price)))) : '-' }}</div>
                <small class="text-muted">Harga Terendah</small>
              </div>
              <div class="text-center px-3 border-start">
                <div class="fw-bold text-danger fs-6">{{ priceHistory.length > 0 ? $formatCurrency(Math.max(...priceHistory.map(h => parseFloat(h.purchase_price)))) : '-' }}</div>
                <small class="text-muted">Harga Tertinggi</small>
              </div>
              <div class="text-center px-3 border-start">
                <div class="fw-bold text-secondary fs-6">{{ priceHistory.length }}</div>
                <small class="text-muted">Total Transaksi</small>
              </div>
            </div>
            <!-- Table -->
            <div class="table-responsive">
              <table class="table csm-table mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Tanggal</th>
                    <th>Referensi</th>
                    <th>Sumber</th>
                    <th>Gudang</th>
                    <th class="text-end">Qty Diterima</th>
                    <th class="text-end">Harga Beli</th>
                    <th class="text-end">Avg Sebelum</th>
                    <th class="text-end">Avg Sesudah</th>
                    <th>Selisih</th>
                    <th>Oleh</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loadingHistory" >
                    <td colspan="11" class="text-center py-4"><div class="csm-spinner"></div></td>
                  </tr>
                  <tr v-else-if="!priceHistory.length">
                    <td colspan="11" class="text-center text-muted py-5">
                      <i class="bi bi-inbox fs-3 d-block mb-2 text-muted"></i>
                      Belum ada riwayat harga pembelian
                    </td>
                  </tr>
                  <tr v-for="(h, idx) in priceHistory" :key="h.id">
                    <td class="text-muted small">{{ idx + 1 }}</td>
                    <td><small>{{ $formatDate(h.transaction_date) }}</small></td>
                    <td><code class="small text-primary">{{ h.reference_no || '-' }}</code></td>
                    <td>
                      <span class="badge" :class="h.source_type === 'surat_jalan' ? 'bg-success' : 'bg-info text-dark'">
                        {{ h.source_type === 'surat_jalan' ? 'Surat Jalan' : 'Stok Masuk' }}
                      </span>
                    </td>
                    <td><small class="text-muted">{{ h.warehouse?.name }}</small></td>
                    <td class="text-end fw-semibold">{{ $formatNumber(h.qty_received) }}</td>
                    <td class="text-end fw-bold text-primary">{{ $formatCurrency(h.purchase_price) }}</td>
                    <td class="text-end text-muted small">{{ $formatCurrency(h.avg_price_before) }}</td>
                    <td class="text-end fw-semibold">{{ $formatCurrency(h.avg_price_after) }}</td>
                    <td>
                      <span v-if="parseFloat(h.purchase_price) > parseFloat(h.avg_price_before) && parseFloat(h.avg_price_before) > 0" class="badge bg-danger">
                        <i class="bi bi-arrow-up me-1"></i>Naik
                      </span>
                      <span v-else-if="parseFloat(h.purchase_price) < parseFloat(h.avg_price_before) && parseFloat(h.avg_price_before) > 0" class="badge bg-success">
                        <i class="bi bi-arrow-down me-1"></i>Turun
                      </span>
                      <span v-else class="badge bg-secondary">Pertama</span>
                    </td>
                    <td><small class="text-muted">{{ h.creator?.name }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer justify-content-between">
            <small class="text-muted">{{ priceHistory.length }} record riwayat harga</small>
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
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
import Swal from 'sweetalert2'
import { useRealtime } from '@/composables/useRealtime'

const auth = useAuthStore()
const toast = useToast()
const can = (p) => auth.hasPermission(p)

const items = ref([])
const categories = ref([])
const loading = ref(false)
const saving = ref(false)
const priceHistory = ref([])
const loadingHistory = ref(false)
const selectedItem = ref(null)
const filters = ref({ search: '', category_id: '' })
const meta = ref({ total: 0, page: 1, last_page: 1 })
const units = ['PCS', 'SET', 'LTR', 'KG', 'MTR', 'BOX', 'ROLL', 'BTL', 'DUS', 'UNIT', 'PASANG']

const form = ref({ id: null, part_number: '', name: '', category_id: '', brand: '', unit: 'PCS', min_stock: 0, price: 0, description: '', is_active: true })

let modal = null
let debounceTimer = null
const { listenMaster, stopMaster } = useRealtime()

onMounted(async () => {
  const [cats] = await Promise.all([axios.get('/categories'), load()])
  categories.value = cats.data.data
  modal = new Modal(document.getElementById('barangModal'))
  listenMaster(() => load())
})
onUnmounted(() => stopMaster())

async function load() {
  loading.value = true
  try {
    const { data: res } = await axios.get('/items', { params: { ...filters.value, page: meta.value.page, per_page: 20 } })
    items.value = res.data
    meta.value = res.meta
  } finally { loading.value = false }
}

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { meta.value.page = 1; load() }, 400)
}
function changePage(p) { meta.value.page = p; load() }
function resetFilters() { filters.value = { search: '', category_id: '' }; meta.value.page = 1; load() }

function openModal(item = null) {
  if (item) {
    form.value = { id: item.id, part_number: item.part_number, name: item.name, category_id: item.category_id, brand: item.brand||'', unit: item.unit, min_stock: parseFloat(item.min_stock)||0, price: parseFloat(item.price)||0, description: item.description||'', is_active: item.is_active }
  } else {
    form.value = { id: null, part_number: '', name: '', category_id: '', brand: '', unit: 'PCS', min_stock: 0, price: 0, description: '', is_active: true }
  }
  modal.show()
}

async function openPriceHistory(item) {
  selectedItem.value = item
  priceHistory.value = []
  loadingHistory.value = true
  new Modal(document.getElementById('modalPriceHistory')).show()
  try {
    const { data: res } = await axios.get(`/items/${item.id}/price-history`, { params: { per_page: 100 } })
    priceHistory.value = res.data
  } catch (e) {
    priceHistory.value = []
  } finally {
    loadingHistory.value = false
  }
}

async function save() {
  saving.value = true
  try {
    if (form.value.id) {
      await axios.put(`/items/${form.value.id}`, form.value)
      toast.success('Barang berhasil diperbarui')
    } else {
      await axios.post('/items', form.value)
      toast.success('Barang berhasil ditambahkan')
    }
    modal.hide()
    meta.value.page = 1
    load()
  } catch(e) {
    const msg = e.response?.data?.message || Object.values(e.response?.data?.errors||{})[0]?.[0] || 'Gagal menyimpan'
    toast.error(msg)
  } finally { saving.value = false }
}

async function deleteItem(item) {
  const result = await Swal.fire({ title: 'Hapus Barang?', text: `"${item.name}" akan dihapus`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#e74c3c', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
  if (!result.isConfirmed) return
  try {
    await axios.delete(`/items/${item.id}`)
    toast.success('Barang dihapus')
    load()
  } catch(e) { toast.error('Gagal menghapus') }
}
</script>