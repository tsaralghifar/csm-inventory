<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-1" style="color:#1a3a5c;">Stok Gudang HO</h5>
        <small class="text-muted">Gudang Head Office - {{ warehouseName }}</small>
      </div>
      <div class="d-flex gap-2">
        <button v-if="can('create-stock-in')" class="btn btn-success btn-sm" @click="openStockIn()">
          <i class="bi bi-plus-circle me-1"></i>Stok Masuk
        </button>
        <button v-if="can('create-stock-out')" class="btn btn-warning btn-sm" @click="openStockOut()">
          <i class="bi bi-dash-circle me-1"></i>Stok Keluar
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-4">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari nama barang / part number..." @input="debouncedLoad" />
          </div>
          <div class="col-6 col-md-3">
            <select v-model="filters.category_id" class="form-select form-select-sm" @change="loadStocks">
              <option value="">Semua Kategori</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <select v-model="filters.filter" class="form-select form-select-sm" @change="loadStocks">
              <option value="">Semua Stok</option>
              <option value="critical">🔴 Stok Kritis</option>
              <option value="minus">⚠️ Stok Minus</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <button class="btn btn-outline-secondary btn-sm w-100" @click="resetFilters">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary badges -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <span class="badge bg-primary rounded-pill">Total: {{ meta.total }} item</span>
      <span class="badge bg-danger rounded-pill">Minus: {{ summary.minus }}</span>
      <span class="badge bg-warning text-dark rounded-pill">Kritis: {{ summary.critical }}</span>
    </div>

    <!-- Table -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center p-5"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>Part Number</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th class="text-end">Stok</th>
                <th class="text-end">Min</th>
                <th>Satuan</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in stocks" :key="s.id">
                <td><code class="small">{{ s.item?.part_number }}</code></td>
                <td>
                  <div class="fw-semibold">{{ s.item?.name }}</div>
                  <small class="text-muted">{{ s.item?.brand }}</small>
                </td>
                <td><span class="badge bg-light text-dark border">{{ s.item?.category?.name }}</span></td>
                <td class="text-end">
                  <span :class="stockClass(s)">{{ $formatNumber(s.qty) }}</span>
                </td>
                <td class="text-end text-muted small">{{ s.item?.min_stock }}</td>
                <td class="text-muted small">{{ s.item?.unit }}</td>
                <td>
                  <span v-if="s.qty < 0" class="badge bg-danger">MINUS</span>
                  <span v-else-if="parseFloat(s.qty) <= parseFloat(s.item?.min_stock)" class="badge bg-warning text-dark">KRITIS</span>
                  <span v-else class="badge bg-success">OK</span>
                </td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <button v-if="can('create-stock-in')" class="btn btn-outline-success" title="Stok Masuk" @click="openStockIn(s)"><i class="bi bi-plus-circle"></i></button>
                    <button v-if="can('create-stock-out')" class="btn btn-outline-warning" title="Stok Keluar" @click="openStockOut(s)"><i class="bi bi-dash-circle"></i></button>
                    <button class="btn btn-outline-secondary" title="Histori" @click="showHistory(s)"><i class="bi bi-clock-history"></i></button>
                  </div>
                </td>
              </tr>
              <tr v-if="!stocks.length && !loading">
                <td colspan="8" class="text-center text-muted py-5">Tidak ada data stok</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top" v-if="meta.last_page > 1">
          <small class="text-muted">Halaman {{ meta.page }} dari {{ meta.last_page }}</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹ Prev</button>
            <button class="btn btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Stok Masuk -->
    <div class="modal fade" id="modalStockIn" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-plus-circle text-success me-2"></i>Stok Masuk</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Pilih barang dengan search (jika tidak dari baris tabel) -->
            <div class="mb-3 position-relative" v-if="!selectedItem">
              <label class="form-label small fw-semibold">Pilih Barang <span class="text-danger">*</span></label>
              <input
                v-model="stockInSearch"
                type="text"
                class="form-control"
                placeholder="🔍 Cari nama / part number..."
                autocomplete="off"
                @input="filterStockInItems"
                @focus="showStockInDrop = true; filterStockInItems()"
                @blur="hideStockInDrop"
              />
              <ul v-if="showStockInDrop && stockInDropResults.length"
                class="list-group position-absolute w-100 shadow"
                style="z-index:9999;max-height:220px;overflow-y:auto;top:100%;left:0">
                <li
                  v-for="item in stockInDropResults" :key="item.id"
                  class="list-group-item list-group-item-action py-2 px-3 small"
                  style="cursor:pointer"
                  @mousedown.prevent="selectStockInItem(item)"
                >
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <span class="fw-semibold">{{ item.name }}</span>
                      <span class="text-muted ms-2 small">{{ item.part_number }}</span>
                    </div>
                    <span class="badge bg-light text-dark border small">{{ item.unit }}</span>
                  </div>
                </li>
              </ul>
              <!-- Info barang terpilih -->
              <div v-if="form.item_id && stockInSelectedItem" class="alert alert-success py-2 small mt-2 mb-0">
                <i class="bi bi-check-circle me-1"></i>
                <strong>{{ stockInSelectedItem.name }}</strong>
                <span class="text-muted ms-1">· {{ stockInSelectedItem.part_number }}</span>
              </div>
            </div>
            <div v-else class="alert alert-info py-2 small mb-3">
              <strong>{{ selectedItem.item.name }}</strong> | Stok saat ini: {{ $formatNumber(selectedItem.qty) }} {{ selectedItem.item.unit }}
            </div>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small fw-semibold">Jumlah <span class="text-danger">*</span></label>
                <input v-model="form.qty" type="number" class="form-control" min="0.01" step="0.01" placeholder="0" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Tanggal <span class="text-danger">*</span></label>
                <input v-model="form.movement_date" type="date" class="form-control" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">No. PO</label>
                <input v-model="form.po_number" type="text" class="form-control" placeholder="PO-xxx" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">No. Invoice</label>
                <input v-model="form.invoice_number" type="text" class="form-control" placeholder="INV-xxx" />
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Keterangan</label>
                <input v-model="form.notes" type="text" class="form-control" placeholder="Keterangan..." />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm" @click="submitStockIn" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>Simpan Stok Masuk
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Stok Keluar -->
    <div class="modal fade" id="modalStockOut" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-dash-circle text-warning me-2"></i>Stok Keluar</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">

            <!-- Info barang jika dibuka dari tombol per-baris -->
            <div v-if="selectedItem" class="alert alert-warning py-2 small mb-3">
              <strong>{{ selectedItem.item.name }}</strong> | Stok tersedia: <strong>{{ $formatNumber(selectedItem.qty) }}</strong> {{ selectedItem.item.unit }}
            </div>

            <!-- Shared fields: Tanggal, PO, Unit, Mekanik, Keterangan -->
            <div class="row g-2 mb-3">
              <div class="col-4">
                <label class="form-label small fw-semibold">Tanggal <span class="text-danger">*</span></label>
                <input v-model="outForm.movement_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-4">
                <label class="form-label small fw-semibold">No. PO / WO</label>
                <input v-model="outForm.po_number" type="text" class="form-control form-control-sm" placeholder="PO-xxx" />
              </div>
              <div class="col-4">
                <label class="form-label small fw-semibold">HM / KM</label>
                <input v-model="outForm.hm_km" type="number" class="form-control form-control-sm" />
              </div>

              <!-- Kode Unit dengan search -->
              <div class="col-4 position-relative">
                <label class="form-label small fw-semibold">Kode Unit</label>
                <input
                  v-model="unitSearch"
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="Cari kode unit..."
                  @input="filterUnits"
                  @focus="showUnitDropdown = true"
                  @blur="hideUnitDropdown"
                  autocomplete="off"
                />
                <ul v-if="showUnitDropdown && filteredUnits.length" class="list-group position-absolute w-100 shadow-sm" style="z-index:9999;max-height:180px;overflow-y:auto;top:100%">
                  <li v-for="u in filteredUnits" :key="u.id" class="list-group-item list-group-item-action py-1 px-2 small" style="cursor:pointer" @mousedown.prevent="selectUnit(u)">
                    <strong>{{ u.unit_code }}</strong> — {{ u.type_unit }} <span class="text-muted">{{ u.brand }}</span>
                  </li>
                </ul>
              </div>
              <div class="col-4">
                <label class="form-label small fw-semibold">Tipe Unit</label>
                <input v-model="outForm.unit_type" type="text" class="form-control form-control-sm" placeholder="ZX350" readonly />
              </div>
              <div class="col-4">
                <label class="form-label small fw-semibold">Diterima Oleh / Mekanik <span class="text-danger">*</span></label>
                <input v-model="outForm.received_by" type="text" class="form-control form-control-sm" placeholder="Nama penerima / mekanik..." />
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Keterangan</label>
                <input v-model="outForm.notes" type="text" class="form-control form-control-sm" placeholder="Keterangan pemakaian..." />
              </div>
            </div>

            <!-- Tabel barang (multi-item) -->
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="small fw-semibold text-secondary">Daftar Barang yang Dikeluarkan</span>
              <button v-if="!selectedItem" class="btn btn-outline-warning btn-sm" @click="addOutItem">
                <i class="bi bi-plus me-1"></i>Tambah Barang
              </button>
            </div>

            <div>
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:38%">Barang</th>
                    <th style="width:15%">Stok Tersedia</th>
                    <th style="width:20%">Jumlah Keluar</th>
                    <th style="width:12%">Satuan</th>
                    <th style="width:10%">Keterangan</th>
                    <th style="width:5%"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in outItems" :key="idx" :class="row.needsPO ? 'table-warning' : ''">
                    <!-- Search barang per baris -->
                    <td class="p-1">
                      <input
                        :ref="el => { if (el) itemInputRefs[idx] = el }"
                        v-model="row.itemSearch"
                        type="text"
                        class="form-control form-control-sm"
                        :placeholder="selectedItem ? selectedItem.item.name : 'Cari nama / part number...'"
                        :disabled="!!selectedItem"
                        @input="filterItems(idx)"
                        @focus="onItemFocus(idx)"
                        @blur="hideItemDrop(idx)"
                        autocomplete="off"
                      />
                    </td>
                    <td class="text-center p-1">
                      <span v-if="row.needsPO" class="badge bg-danger small">
                        <i class="bi bi-exclamation-triangle me-1"></i>Kosong
                      </span>
                      <span v-else :class="row.available > 0 ? 'text-success' : 'text-danger'" class="fw-semibold small">
                        {{ row.available ?? '-' }}
                      </span>
                    </td>
                    <td class="p-1">
                      <div v-if="row.needsPO" class="text-center">
                        <input v-model="row.qty" type="number" class="form-control form-control-sm text-center" min="0.01" step="0.01" placeholder="0" />
                        <small class="text-danger d-block mt-1" style="font-size:0.65rem;">
                          <i class="bi bi-arrow-right-circle me-1"></i>Akan dibuat PO
                        </small>
                      </div>
                      <input v-else v-model="row.qty" type="number" class="form-control form-control-sm text-center" min="0.01" step="0.01" placeholder="0" />
                    </td>
                    <td class="text-center p-1 small text-muted">{{ row.unit || '-' }}</td>
                    <td class="text-center p-1">
                      <span v-if="row.needsPO" class="badge bg-warning text-dark" style="font-size:0.65rem;">
                        <i class="bi bi-file-earmark-text me-1"></i>PO
                      </span>
                    </td>
                    <td class="text-center p-1">
                      <button v-if="!selectedItem || outItems.length > 1" class="btn btn-outline-danger btn-sm p-0 px-1" @click="removeOutItem(idx)" :disabled="outItems.length === 1 && !selectedItem">
                        <i class="bi bi-x"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
          <div class="modal-footer">
            <!-- Info jika ada barang perlu PO -->
            <div v-if="outItems.some(r => r.needsPO && r.item_id)" class="me-auto">
              <small class="text-warning fw-semibold">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ outItems.filter(r => r.needsPO && r.item_id).length }} barang stok kosong akan dibuatkan PO
              </small>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-warning btn-sm" @click="submitStockOut" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <span v-if="outItems.some(r => r.needsPO && r.item_id) && outItems.some(r => !r.needsPO && r.item_id)">
                Simpan Stok Keluar + Buat PO
              </span>
              <span v-else-if="outItems.every(r => !r.item_id || r.needsPO)">
                Buat Purchase Order
              </span>
              <span v-else>Simpan Stok Keluar</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Dropdown teleport - render di body agar tidak terpotong overflow -->
    <Teleport to="body">
      <template v-for="(row, idx) in outItems" :key="'drop-'+idx">
        <ul
          v-if="row.showDrop && row.dropResults.length"
          class="list-group shadow"
          :style="row.dropStyle"
        >
          <li
            v-for="s in row.dropResults"
            :key="s.item_id"
            class="list-group-item list-group-item-action py-2 px-3 small"
            style="cursor:pointer"
            @mousedown.prevent="selectItem(idx, s)"
          >
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span class="text-muted me-1">{{ s.item?.part_number }}</span>
                <span class="fw-semibold">{{ s.item?.name }}</span>
              </div>
              <div class="ms-2">
                <span v-if="s.hasStock && parseFloat(s.qty) > 0" class="badge bg-success">
                  Stok: {{ s.qty }} {{ s.item?.unit }}
                </span>
                <span v-else class="badge bg-danger">
                  <i class="bi bi-exclamation-triangle me-1"></i>Stok Kosong → PO
                </span>
              </div>
            </div>
          </li>
        </ul>
      </template>
    </Teleport>


    <!-- Modal History -->
    <div class="modal fade" id="modalHistory" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title">Histori - {{ selectedItem?.item?.name }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0">
            <div class="table-responsive">
              <table class="table csm-table mb-0">
                <thead><tr><th>Ref No</th><th>Tipe</th><th class="text-end">Qty</th><th class="text-end">Sebelum</th><th class="text-end">Sesudah</th><th>Catatan</th><th>Tanggal</th><th>User</th></tr></thead>
                <tbody>
                  <tr v-for="m in history" :key="m.id">
                    <td><code class="small">{{ m.reference_no }}</code></td>
                    <td>
                      <span class="badge" :class="m.type === 'in' || m.type === 'transfer_in' ? 'bg-success' : 'bg-danger'">
                        {{ movLabel(m.type) }}
                      </span>
                    </td>
                    <td class="text-end">{{ $formatNumber(m.qty) }}</td>
                    <td class="text-end text-muted">{{ $formatNumber(m.qty_before) }}</td>
                    <td class="text-end">{{ $formatNumber(m.qty_after) }}</td>
                    <td><small>{{ m.notes || '-' }}</small></td>
                    <td><small>{{ $formatDate(m.movement_date) }}</small></td>
                    <td><small>{{ m.creator?.name }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/store/auth'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import { Modal } from 'bootstrap'
import { useRealtime } from '@/composables/useRealtime'

const auth = useAuthStore()
const toast = useToast()

const stocks = ref([])
const allItems = ref([])
const categories = ref([])
const loading = ref(true)
const saving = ref(false)
const selectedItem = ref(null)
const history = ref([])
const meta = ref({ total: 0, page: 1, last_page: 1 })
const summary = ref({ minus: 0, critical: 0 })

const filters = ref({ search: '', category_id: '', filter: '', page: 1 })
const form = ref(defaultForm())

// ── Stok Keluar multi-item ──────────────────────────────────────────
const outForm = ref(defaultOutForm())
const outItems = ref([defaultOutItem()])
const itemInputRefs = ref([])

// Unit search
const unitSearch = ref('')
const showUnitDropdown = ref(false)
const filteredUnits = ref([])
const allUnits = ref([])

// Stock In search
const stockInSearch = ref('')
const showStockInDrop = ref(false)
const stockInDropResults = ref([])
const stockInSelectedItem = ref(null)
let stockInTimer = null

function defaultOutForm() {
  return { movement_date: new Date().toISOString().split('T')[0], po_number: '', unit_code: '', unit_type: '', hm_km: '', mechanic: '', notes: '', received_by: '' }
}
function defaultOutItem() {
  return { item_id: '', itemSearch: '', dropResults: [], showDrop: false, qty: '', unit: '', available: null, dropStyle: {}, needsPO: false }
}

function filterUnits() {
  const q = unitSearch.value.toLowerCase()
  filteredUnits.value = q.length < 1 ? allUnits.value.slice(0, 10) : allUnits.value.filter(u =>
    u.unit_code?.toLowerCase().includes(q) || u.type_unit?.toLowerCase().includes(q)
  ).slice(0, 15)
}
function hideUnitDropdown() { setTimeout(() => { showUnitDropdown.value = false }, 150) }
function selectUnit(u) {
  unitSearch.value = u.unit_code
  outForm.value.unit_code = u.unit_code
  outForm.value.unit_type = u.type_unit || ''
  showUnitDropdown.value = false
}

// Stock In item search
function filterStockInItems() {
  const q = stockInSearch.value.trim()
  if (q.length < 1) {
    stockInDropResults.value = allItems.value.slice(0, 10)
    return
  }
  clearTimeout(stockInTimer)
  stockInTimer = setTimeout(() => {
    stockInDropResults.value = allItems.value
      .filter(item =>
        item.name?.toLowerCase().includes(q.toLowerCase()) ||
        item.part_number?.toLowerCase().includes(q.toLowerCase())
      )
      .slice(0, 15)
  }, 200)
}
function hideStockInDrop() { setTimeout(() => { showStockInDrop.value = false }, 150) }
function selectStockInItem(item) {
  form.value.item_id = item.id
  stockInSelectedItem.value = item
  stockInSearch.value = `${item.part_number} - ${item.name}`
  showStockInDrop.value = false
}

function onItemFocus(idx) {
  outItems.value[idx].showDrop = true
  const el = itemInputRefs.value[idx]
  if (el) {
    const rect = el.getBoundingClientRect()
    outItems.value[idx].dropStyle = {
      position: 'fixed',
      top: rect.bottom + 2 + 'px',
      left: rect.left + 'px',
      width: Math.max(rect.width, 340) + 'px',
      zIndex: 99999,
      maxHeight: '220px',
      overflowY: 'auto',
      borderRadius: '6px',
    }
  }
}
const itemSearchTimers = {}
function filterItems(idx) {
  onItemFocus(idx)
  const q = outItems.value[idx].itemSearch.trim()
  if (q.length < 1) { outItems.value[idx].dropResults = []; return }

  // Debounce per baris
  clearTimeout(itemSearchTimers[idx])
  itemSearchTimers[idx] = setTimeout(async () => {
    if (!hoWarehouseId.value) return
    try {
      // Cari dari semua master barang, lalu cross-reference stok di gudang ini
      const [itemsRes, stocksRes] = await Promise.all([
        axios.get('/items', { params: { search: q, per_page: 15 } }),
        axios.get(`/warehouses/${hoWarehouseId.value}/stocks`, { params: { search: q, per_page: 15 } }),
      ])
      const stockMap = {}
      for (const s of (stocksRes.data.data || [])) {
        stockMap[s.item_id] = s.qty
      }
      outItems.value[idx].dropResults = (itemsRes.data.data || []).map(item => ({
        item_id: item.id,
        item,
        qty: stockMap[item.id] ?? 0,
        hasStock: stockMap[item.id] !== undefined,
      }))
    } catch { outItems.value[idx].dropResults = [] }
  }, 300)
}
function hideItemDrop(idx) { setTimeout(() => { outItems.value[idx].showDrop = false }, 150) }
function selectItem(idx, s) {
  outItems.value[idx].item_id = s.item_id
  outItems.value[idx].itemSearch = `${s.item?.part_number} - ${s.item?.name}`
  outItems.value[idx].unit = s.item?.unit || ''
  outItems.value[idx].available = s.qty
  outItems.value[idx].needsPO = !s.hasStock || parseFloat(s.qty) <= 0
  outItems.value[idx].dropResults = []
  outItems.value[idx].showDrop = false
}
function addOutItem() { outItems.value.push(defaultOutItem()) }
function removeOutItem(idx) { if (outItems.value.length > 1) outItems.value.splice(idx, 1) }

// Get HO warehouse ID
const hoWarehouseId = ref(null)
const warehouseName = ref('')
const { listenStok, stopStok } = useRealtime()

function can(p) { return auth.hasPermission(p) }
function defaultForm() {
  return { item_id: '', qty: '', movement_date: new Date().toISOString().split('T')[0], po_number: '', invoice_number: '', notes: '', unit_code: '', unit_type: '', hm_km: '', mechanic: '' }
}

const movLabel = (type) => ({ in: 'Masuk', out: 'Keluar', transfer_in: 'Terima', transfer_out: 'Kirim', adjustment: 'Adj', opname: 'Opname' }[type] || type)

function stockClass(s) {
  if (parseFloat(s.qty) < 0) return 'stock-minus'
  if (parseFloat(s.qty) <= parseFloat(s.item?.min_stock)) return 'stock-critical'
  return 'stock-ok'
}

let searchTimer = null
function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadStocks, 400)
}

async function loadStocks() {
  if (!hoWarehouseId.value) return
  loading.value = true
  try {
    const res = await axios.get(`/warehouses/${hoWarehouseId.value}/stocks`, { params: { ...filters.value, per_page: 25 } })
    stocks.value = res.data.data
    meta.value = res.data.meta
    summary.value = {
      minus: stocks.value.filter(s => parseFloat(s.qty) < 0).length,
      critical: stocks.value.filter(s => parseFloat(s.qty) <= parseFloat(s.item?.min_stock) && parseFloat(s.qty) >= 0).length,
    }
  } catch (e) { toast.error('Gagal memuat data stok') }
  finally { loading.value = false }
}

async function loadCategories() {
  const res = await axios.get('/categories')
  categories.value = res.data.data
}

async function loadAllItems() {
  const res = await axios.get('/items', { params: { per_page: 999 } })
  allItems.value = res.data.data
}

async function loadHOWarehouse() {
  const res = await axios.get('/warehouses', { params: { type: 'ho' } })
  hoWarehouseId.value = res.data.data?.[0]?.id
  warehouseName.value = res.data.data?.[0]?.name ?? ''
}

function openStockIn(stock = null) {
  selectedItem.value = stock
  form.value = defaultForm()
  stockInSearch.value = ''
  stockInSelectedItem.value = null
  stockInDropResults.value = allItems.value.slice(0, 10)
  showStockInDrop.value = false
  if (stock) form.value.item_id = stock.item_id
  new Modal('#modalStockIn').show()
}

function openStockOut(stock = null) {
  selectedItem.value = stock
  outForm.value = defaultOutForm()
  unitSearch.value = ''
  filteredUnits.value = allUnits.value.slice(0, 10)
  if (stock) {
    outItems.value = [{ ...defaultOutItem(), item_id: stock.item_id, itemSearch: `${stock.item?.part_number} - ${stock.item?.name}`, unit: stock.item?.unit || '', available: stock.qty }]
  } else {
    outItems.value = [defaultOutItem()]
  }
  new Modal('#modalStockOut').show()
}

async function showHistory(stock) {
  selectedItem.value = stock
  const res = await axios.get(`/items/${stock.item_id}/movements`, { params: { warehouse_id: hoWarehouseId.value } })
  history.value = res.data.data.data || res.data.data
  new Modal('#modalHistory').show()
}

async function submitStockIn() {
  if (!form.value.item_id || !form.value.qty) return toast.error('Lengkapi data')
  saving.value = true
  try {
    await axios.post(`/items/${form.value.item_id}/stock-in`, { ...form.value, warehouse_id: hoWarehouseId.value })
    toast.success('Stok masuk berhasil dicatat')
    Modal.getInstance('#modalStockIn')?.hide()
    loadStocks()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan')
  } finally { saving.value = false }
}

async function submitStockOut() {
  const validItems = outItems.value.filter(r => r.item_id && r.qty > 0)
  if (!validItems.length) return toast.error('Tambahkan minimal 1 barang dengan jumlah yang valid')
  if (!outForm.value.movement_date) return toast.error('Tanggal wajib diisi')
  if (!outForm.value.received_by?.trim()) return toast.error('Nama penerima wajib diisi')

  const stockItems = validItems.filter(r => !r.needsPO)
  const poItems    = validItems.filter(r => r.needsPO)

  saving.value = true
  try {
    const basePayload = {
      warehouse_id: hoWarehouseId.value,
      received_by:  outForm.value.received_by,
      issue_date:   outForm.value.movement_date,
      po_number:    outForm.value.po_number || null,
      unit_code:    outForm.value.unit_code || null,
      unit_type:    outForm.value.unit_type || null,
      hm_km:        outForm.value.hm_km || null,
      mechanic:     outForm.value.received_by || null,
      notes:        outForm.value.notes || null,
    }

    const messages = []

    // 1. Proses Stok Keluar untuk barang yang ada stoknya
    if (stockItems.length) {
      const res = await axios.post('/bon-pengeluaran', {
        ...basePayload,
        auto_issue: true,
        items: stockItems.map(r => ({
          item_id:     r.item_id,
          nama_barang: r.itemSearch.split(' - ').slice(1).join(' - ') || r.itemSearch,
          qty:         r.qty,
          satuan:      r.unit || 'PCS',
          keterangan:  null,
        })),
      })
      messages.push(`✅ Bon ${res.data.data?.bon_number} — ${stockItems.length} barang dikeluarkan`)
    }

    // 2. Buat Permintaan Material (PM) draft untuk barang stok kosong
    if (poItems.length) {
      const pmRes = await axios.post('/permintaan-material', {
        warehouse_id:  hoWarehouseId.value,
        type:          'part',
        needed_date:   outForm.value.movement_date,
        notes:         `Auto PM dari Stok Keluar — Unit: ${outForm.value.unit_code || '-'} ${outForm.value.notes ? '| ' + outForm.value.notes : ''}`.trim(),
        items: poItems.map(r => ({
          nama_barang:  r.itemSearch.split(' - ').slice(1).join(' - ') || r.itemSearch,
          item_id:      r.item_id || null,
          part_number:  r.itemSearch.split(' - ')[0] || '',
          kode_unit:    outForm.value.unit_code || '',
          tipe_unit:    outForm.value.unit_type || '',
          qty:          r.qty,
          satuan:       r.unit || 'PCS',
          keterangan:   'Stok kosong saat pengajuan Stok Keluar',
        })),
      })
      const pmNumber = pmRes.data.data?.pm_number || ''
      messages.push(`📋 PM ${pmNumber} dibuat — ${poItems.length} barang stok kosong menunggu proses PO`)
    }

    messages.forEach(m => toast.success(m, { timeout: 5000 }))
    Modal.getInstance('#modalStockOut')?.hide()
    loadStocks()
  } catch (e) {
    toast.error(e.response?.data?.message || e.response?.data?.errors?.stock || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Gagal menyimpan')
  } finally { saving.value = false }
}

function changePage(p) {
  filters.value.page = p
  loadStocks()
}

function resetFilters() {
  filters.value = { search: '', category_id: '', filter: '', page: 1 }
  loadStocks()
}

onMounted(async () => {
  await loadHOWarehouse()
  await Promise.all([loadStocks(), loadCategories(), loadAllItems()])
  listenStok(() => loadStocks())
  // Muat data unit untuk search kode unit
  try {
    const res = await axios.get('/units', { params: { per_page: 999 } })
    allUnits.value = res.data.data || []
    filteredUnits.value = allUnits.value.slice(0, 10)
  } catch {}
})
onUnmounted(() => stopStok())
</script>