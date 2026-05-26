<template>
  <!-- ══════════════════════════════════════════════════
       BACKDROP
  ══════════════════════════════════════════════════ -->
  <Teleport to="body">
    <Transition name="drawer-backdrop">
      <div v-if="modelValue" class="pm-backdrop" @click="onBackdropClick" />
    </Transition>

    <!-- ══════════════════════════════════════════════════
         DRAWER PANEL
    ══════════════════════════════════════════════════ -->
    <Transition name="drawer-slide">
      <div v-if="modelValue" class="pm-drawer" role="dialog" aria-modal="true" aria-label="Buat Permintaan Material">

        <!-- ── HEADER (sticky) ────────────────────────── -->
        <div class="pm-drawer__header">
          <div class="pm-drawer__header-left">
            <div class="pm-drawer__icon">
              <i class="bi bi-clipboard-plus"></i>
            </div>
            <div>
              <div class="pm-drawer__title">Buat Permintaan Material</div>
              <div class="pm-drawer__subtitle">
                <span v-if="form.type === 'part'">🔧 MR Part · Sparepart & Alat Berat</span>
                <span v-else-if="form.type === 'office'">🏢 MR Office · Perlengkapan Kantor</span>
                <span v-else class="text-muted">Pilih tipe permintaan di bawah</span>
              </div>
            </div>
          </div>
          <button class="pm-drawer__close" @click="close" title="Tutup">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <!-- ── PROGRESS BAR ───────────────────────────── -->
        <div class="pm-drawer__progress-wrap">
          <div class="pm-drawer__progress-step" :class="{ active: stepDone >= 1, done: stepDone > 1 }">
            <div class="pm-drawer__progress-dot">
              <i v-if="stepDone > 1" class="bi bi-check-lg"></i>
              <span v-else>1</span>
            </div>
            <span>Tipe</span>
          </div>
          <div class="pm-drawer__progress-line" :class="{ active: stepDone >= 2 }"></div>
          <div class="pm-drawer__progress-step" :class="{ active: stepDone >= 2, done: stepDone > 2 }">
            <div class="pm-drawer__progress-dot">
              <i v-if="stepDone > 2" class="bi bi-check-lg"></i>
              <span v-else>2</span>
            </div>
            <span>Gudang</span>
          </div>
          <div class="pm-drawer__progress-line" :class="{ active: stepDone >= 3 }"></div>
          <div class="pm-drawer__progress-step" :class="{ active: stepDone >= 3 }">
            <div class="pm-drawer__progress-dot"><span>3</span></div>
            <span>Barang ({{ form.items.length }})</span>
          </div>
        </div>

        <!-- ── SCROLL BODY ─────────────────────────────── -->
        <div class="pm-drawer__body" ref="bodyRef">

          <!-- SECTION 1 : TIPE -->
          <div class="pm-section">
            <div class="pm-section__label">
              <span class="pm-section__num">1</span>Tipe Permintaan <span class="text-danger">*</span>
            </div>
            <div class="pm-type-grid">
              <button type="button" class="pm-type-card"
                :class="form.type === 'part' ? 'pm-type-card--active-part' : ''"
                @click="form.type = 'part'">
                <i class="bi bi-tools pm-type-card__icon text-primary"></i>
                <div class="pm-type-card__name">MR Part</div>
                <div class="pm-type-card__desc">Sparepart & material alat berat</div>
                <div v-if="form.type === 'part'" class="pm-type-card__check"><i class="bi bi-check-circle-fill"></i></div>
              </button>
              <button type="button" class="pm-type-card"
                :class="form.type === 'office' ? 'pm-type-card--active-office' : ''"
                @click="form.type = 'office'">
                <i class="bi bi-building pm-type-card__icon text-info"></i>
                <div class="pm-type-card__name">MR Office</div>
                <div class="pm-type-card__desc">Perlengkapan & kebutuhan kantor</div>
                <div v-if="form.type === 'office'" class="pm-type-card__check text-info"><i class="bi bi-check-circle-fill"></i></div>
              </button>
            </div>
            <Transition name="fade">
              <div v-if="form.type" class="pm-flow-badge" :class="form.type === 'part' ? 'pm-flow-badge--part' : 'pm-flow-badge--office'">
                <i class="bi bi-info-circle me-1"></i>
                <span v-if="form.type === 'part'">Alur: <strong>Submit → Chief Mekanik → Manager → Admin HO → Bon / PO</strong></span>
                <span v-else>Alur: <strong>Submit → Admin HO → Bon Pengeluaran / Surat Jalan</strong></span>
              </div>
            </Transition>
          </div>

          <!-- SECTION 2 : GUDANG & INFO -->
          <div class="pm-section">
            <div class="pm-section__label">
              <span class="pm-section__num">2</span>Informasi Permintaan
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="pm-label">Gudang / Site <span class="text-danger">*</span></label>
                <select v-model="form.warehouse_id" class="form-select form-select-sm"
                  :class="form.warehouse_id ? 'border-success' : ''">
                  <option value="">-- Pilih Gudang --</option>
                  <optgroup label="Head Office">
                    <option v-for="w in warehouses.filter(w => w.type === 'ho')" :key="w.id" :value="w.id">{{ w.name }}</option>
                  </optgroup>
                  <optgroup label="Site">
                    <option v-for="w in warehouses.filter(w => w.type === 'site')" :key="w.id" :value="w.id">{{ w.name }}</option>
                  </optgroup>
                </select>
              </div>
              <div class="col-md-6">
                <label class="pm-label">Tanggal Dibutuhkan</label>
                <input v-model="form.needed_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="pm-label">Catatan Umum</label>
                <input v-model="form.notes" type="text" class="form-control form-control-sm" placeholder="Catatan tambahan (opsional)..." />
              </div>
            </div>
          </div>

          <!-- SECTION 3 : DAFTAR BARANG -->
          <div class="pm-section">
            <div class="pm-section__label">
              <span class="pm-section__num">3</span>Daftar Barang yang Diminta
              <span class="badge bg-primary ms-1 rounded-pill">{{ form.items.length }}</span>
            </div>

            <!-- Peringatan jika gudang belum dipilih -->
            <div v-if="!form.warehouse_id" class="pm-hint-box">
              <i class="bi bi-arrow-up-circle me-1"></i>Pilih gudang terlebih dahulu untuk bisa mencari barang dari stok.
            </div>

            <!-- Loading stok -->
            <div v-if="loadingStock" class="text-center py-3">
              <div class="csm-spinner"></div>
              <small class="text-muted ms-2">Memuat data sparepart...</small>
            </div>

            <!-- ITEM CARDS -->
            <TransitionGroup name="item-list" tag="div">
              <div v-for="(item, idx) in form.items" :key="item._uid"
                class="pm-item-card"
                :class="{ 'pm-item-card--new': item.is_new_item, 'pm-item-card--filled': isItemFilled(item) }"
                :id="`pm-item-${idx}`">

                <!-- Item header -->
                <div class="pm-item-card__header">
                  <div class="d-flex align-items-center gap-2">
                    <span class="pm-item-badge">{{ idx + 1 }}</span>
                    <span v-if="item.is_new_item" class="badge bg-warning text-dark">
                      <i class="bi bi-plus-circle me-1"></i>Barang Baru
                    </span>
                    <span v-if="isItemFilled(item)" class="badge bg-success">
                      <i class="bi bi-check-lg me-1"></i>Lengkap
                    </span>
                  </div>
                  <div class="d-flex gap-1">
                    <!-- Duplicate button -->
                    <button v-if="isItemFilled(item)" type="button"
                      class="btn btn-xs btn-outline-secondary"
                      title="Duplikat item ini"
                      @click="duplicateItem(idx)">
                      <i class="bi bi-copy"></i>
                    </button>
                    <!-- Remove button -->
                    <button v-if="form.items.length > 1" type="button"
                      class="btn btn-xs btn-outline-danger"
                      title="Hapus barang ini"
                      @click="removeItem(idx)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>

                <!-- Item body -->
                <div class="pm-item-card__body">
                  <!-- Nama Barang / Search -->
                  <div class="col-12 mb-2">
                    <label class="pm-label">Nama Barang / Deskripsi <span class="text-danger">*</span></label>

                    <!-- SEARCH (warehouse dipilih & bukan mode barang baru) -->
                    <div v-if="form.warehouse_id && !item.is_new_item" class="position-relative">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0">
                          <i class="bi bi-search text-muted"></i>
                        </span>
                        <input
                          v-model="item._searchStok"
                          type="text"
                          class="form-control form-control-sm border-start-0"
                          :class="item.item_id ? 'bg-light' : ''"
                          :placeholder="form.type === 'part' ? 'Cari nama part / part number...' : 'Cari nama barang...'"
                          :readonly="!!item.item_id"
                          @input="item._showDropdown = true"
                          @focus="item._showDropdown = true"
                          @blur="() => hideSearchDrop(item)"
                        />
                        <button v-if="item._searchStok" type="button"
                          class="btn btn-outline-secondary btn-sm"
                          @mousedown.prevent="clearItemSearch(item)">
                          <i class="bi bi-x"></i>
                        </button>
                      </div>

                      <!-- Dropdown hasil pencarian -->
                      <div v-if="item._showDropdown && item._searchStok && !item.item_id"
                        class="pm-search-dropdown">
                        <template v-if="filteredStocks(item).length">
                          <div v-for="stok in filteredStocks(item).slice(0, 8)" :key="stok.id"
                            class="pm-search-dropdown__item"
                            @mousedown.prevent="pilihBarangDariStok(item, stok)">
                            <div class="pm-search-dropdown__item-info">
                              <div class="pm-search-dropdown__item-name">{{ stok.item?.name }}</div>
                              <small class="text-muted">
                                <span v-if="stok.item?.part_number" class="text-primary fw-semibold me-1">{{ stok.item.part_number }}</span>
                                <span v-if="stok.item?.category?.name">· {{ stok.item.category.name }}</span>
                              </small>
                            </div>
                            <span class="badge flex-shrink-0" :class="stok.qty > 0 ? 'bg-success' : 'bg-warning text-dark'">
                              Stok: {{ stok.qty }} {{ stok.item?.unit }}
                            </span>
                          </div>
                        </template>
                        <div class="pm-search-dropdown__footer">
                          <div v-if="!filteredStocks(item).length" class="text-center mb-2">
                            <small class="text-muted">Tidak ditemukan: <strong>{{ item._searchStok }}</strong></small>
                          </div>
                          <button type="button" class="btn btn-warning btn-sm w-100"
                            @mousedown.prevent="aktivasiBarangBaru(item)">
                            <i class="bi bi-plus-circle me-1"></i>
                            Daftarkan "{{ item._searchStok }}" sebagai Barang Baru
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- Stok badge setelah pilih -->
                    <div v-if="!item.is_new_item && item._stok !== undefined" class="mt-1">
                      <span class="badge" :class="item._stok > 0 ? 'bg-success' : 'bg-warning text-dark'">
                        <i class="bi bi-box me-1"></i>
                        {{ item._stok > 0 ? `Stok tersedia: ${item._stok} ${item.satuan}` : 'Stok kosong di gudang ini' }}
                      </span>
                    </div>

                    <!-- Form barang baru -->
                    <div v-if="item.is_new_item" class="pm-new-item-box">
                      <div class="d-flex align-items-center justify-content-between mb-2">
                        <small class="fw-semibold text-warning">
                          <i class="bi bi-exclamation-triangle me-1"></i>Barang baru — akan didaftarkan ke Master Barang
                        </small>
                        <button type="button" class="btn btn-xs btn-outline-secondary" @click="batalBarangBaru(item)">
                          <i class="bi bi-arrow-left me-1"></i>Batal
                        </button>
                      </div>
                      <div class="row g-2">
                        <div class="col-md-6">
                          <label class="pm-label">Part Number <span class="text-danger">*</span></label>
                          <input v-model="item.new_part_number" type="text" class="form-control form-control-sm"
                            :class="item._partExistsWarning || item._partDupInForm ? 'border-danger' : ''"
                            placeholder="Contoh: FLT-OLI-320..."
                            @input="checkPartNumberExists(item); checkDupInForm(item)" />
                          <!-- Duplikat di master -->
                          <div v-if="item._partExistsWarning && !item._partDupInForm" class="mt-1 p-2 rounded border border-danger" style="background:#fff5f5;">
                            <small class="text-danger fw-semibold d-block mb-1">
                              <i class="bi bi-exclamation-circle me-1"></i>
                              Part Number sudah ada di Master Barang.
                            </small>
                            <button type="button" class="btn btn-sm btn-danger w-100" @click="pakaiBarangMaster(item)">
                              <i class="bi bi-box-seam me-1"></i>Gunakan Barang yang Sudah Ada
                            </button>
                          </div>
                          <!-- Duplikat dalam form ini -->
                          <div v-if="item._partDupInForm" class="mt-1 p-2 rounded border border-danger" style="background:#fff5f5;">
                            <small class="text-danger fw-semibold">
                              <i class="bi bi-exclamation-circle me-1"></i>
                              Part Number ini sudah dipakai di barang lain dalam daftar ini.
                            </small>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <label class="pm-label">Kategori <span class="text-danger">*</span></label>
                          <select v-model="item.new_category_id" class="form-select form-select-sm">
                            <option value="">-- Pilih Kategori --</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label class="pm-label">Brand / Merk</label>
                          <input v-model="item.new_brand" type="text" class="form-control form-control-sm" placeholder="CAT, Komatsu, Fleetguard..." />
                        </div>
                        <div class="col-md-6">
                          <label class="pm-label">Stok Minimum</label>
                          <input v-model="item.new_min_stock" type="number" class="form-control form-control-sm" min="0" placeholder="0" />
                        </div>
                      </div>
                    </div>

                    <!-- Input nama barang manual -->
                    <input v-model="item.nama_barang" type="text" class="form-control form-control-sm mt-1"
                      :class="item.is_new_item ? 'border-warning' : (item.item_id ? 'bg-light' : '')"
                      :placeholder="form.type === 'part' ? 'Contoh: Filter Oli Excavator CAT 320...' : 'Contoh: Kertas HVS A4, Tinta Printer...'"
                      :readonly="!item.is_new_item && !!item.item_id" />
                  </div>

                  <!-- Part-specific fields -->
                  <template v-if="form.type === 'part'">
                    <div class="col-md-6 mb-2">
                      <label class="pm-label">Part Number</label>
                      <input v-model="item.part_number" type="text" class="form-control form-control-sm"
                        :readonly="!item.is_new_item && !!item.item_id"
                        :class="item.item_id && !item.is_new_item ? 'bg-light' : ''"
                        placeholder="Contoh: FLT-OLI-320..." />
                    </div>
                    <div class="col-md-6 mb-2 position-relative">
                      <label class="pm-label">Kode Unit / Alat Berat</label>

                      <!-- Tag list unit yang sudah dipilih -->
                      <div v-if="item.kode_unit_list && item.kode_unit_list.length"
                        class="d-flex flex-wrap gap-1 mb-1">
                        <span v-for="(ku, ki) in item.kode_unit_list" :key="ki"
                          class="badge d-inline-flex align-items-center gap-1"
                          style="background:#1a3a5c;font-size:.75rem;padding:4px 8px;">
                          {{ ku.kode }} <span class="text-white-50 small">{{ ku.tipe }}</span>
                          <button type="button"
                            class="btn-close btn-close-white ms-1"
                            style="font-size:.55rem;opacity:.7;"
                            @click="removeUnitFromItem(item, ki)"></button>
                        </span>
                      </div>

                      <!-- Input pencarian unit baru -->
                      <input
                        v-model="item._unitSearch"
                        type="text"
                        class="form-control form-control-sm"
                        placeholder="Cari & tambah kode unit... (CSM 0038)"
                        autocomplete="off"
                        @input="filterUnitsForItem(item)"
                        @focus="item._showUnitDrop = true; filterUnitsForItem(item)"
                        @blur="hideUnitDrop(item)"
                      />
                      <ul v-if="item._showUnitDrop && item._unitDropResults.length"
                        class="list-group position-absolute w-100 shadow-sm"
                        style="z-index:9999;max-height:180px;overflow-y:auto;top:100%;left:0">
                        <li v-for="u in item._unitDropResults" :key="u.id"
                          class="list-group-item list-group-item-action py-1 px-2 small"
                          style="cursor:pointer"
                          @mousedown.prevent="addUnitToItem(item, u)">
                          <strong>{{ u.unit_code }}</strong>
                          <span class="text-muted ms-1">— {{ u.type_unit }} {{ u.brand }}</span>
                          <span v-if="isUnitAlreadyAdded(item, u)"
                            class="badge bg-success ms-1" style="font-size:.65rem;">✓ Sudah ditambah</span>
                        </li>
                      </ul>
                    </div>
                    <div class="col-md-6 mb-2">
                      <label class="pm-label">Tipe Unit</label>
                      <input
                        :value="item.kode_unit_list && item.kode_unit_list.length ? item.kode_unit_list.map(k => k.tipe).filter(Boolean).join(', ') : ''"
                        type="text" class="form-control form-control-sm bg-light"
                        placeholder="Otomatis terisi" readonly />
                    </div>
                  </template>

                  <!-- Qty, Satuan, Keterangan -->
                  <div class="row g-2">
                    <div class="col-4">
                      <label class="pm-label">Jumlah <span class="text-danger">*</span></label>
                      <input v-model="item.qty" type="number" class="form-control form-control-sm"
                        :class="item.qty ? 'border-success' : ''"
                        min="0.01" step="0.01" placeholder="0" />
                    </div>
                    <div class="col-4">
                      <label class="pm-label">Satuan <span class="text-danger">*</span></label>
                      <input v-model="item.satuan" type="text" class="form-control form-control-sm"
                        :class="item.satuan ? 'border-success' : ''"
                        list="satuanList" placeholder="Pcs, Liter..." />
                      <datalist id="satuanList">
                        <option value="Pcs" /><option value="Set" /><option value="Liter" />
                        <option value="Kg" /><option value="Meter" /><option value="Roll" />
                        <option value="Rim" /><option value="Botol" />
                      </datalist>
                    </div>
                    <div class="col-4">
                      <label class="pm-label">Keterangan</label>
                      <input v-model="item.keterangan" type="text" class="form-control form-control-sm" placeholder="Info tambahan..." />
                    </div>
                  </div>
                </div>
              </div>
            </TransitionGroup>

            <!-- ── TOMBOL TAMBAH BARANG — sticky di bawah daftar, selalu terlihat ── -->
            <div class="pm-add-area">
              <button type="button" class="pm-add-btn" @click="addItemAndScroll">
                <i class="bi bi-plus-circle-fill me-2"></i>
                Tambah Barang
                <span class="pm-add-btn__count">{{ form.items.length }} item</span>
              </button>
            </div>

          </div><!-- /section 3 -->

          <!-- Summary bar sebelum footer -->
          <div class="pm-summary" v-if="form.items.some(i => isItemFilled(i))">
            <div class="pm-summary__row">
              <span class="pm-summary__label">Total Barang Lengkap</span>
              <span class="pm-summary__val text-success">{{ form.items.filter(i => isItemFilled(i)).length }} / {{ form.items.length }}</span>
            </div>
            <div class="pm-summary__row" v-if="form.warehouse_id">
              <span class="pm-summary__label">Gudang</span>
              <span class="pm-summary__val">{{ warehouseName }}</span>
            </div>
          </div>

        </div><!-- /body -->

        <!-- ── FOOTER (sticky) ────────────────────────── -->
        <div class="pm-drawer__footer">
          <button type="button" class="btn btn-secondary btn-sm" @click="close">
            <i class="bi bi-x me-1"></i>Batal
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="pm-add-btn-footer" @click="addItemAndScroll">
              <i class="bi bi-plus me-1"></i>Tambah Barang
            </button>
            <button type="button" class="btn btn-csm-primary btn-sm px-3" @click="savePM" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i v-else class="bi bi-floppy me-1"></i>
              Simpan Draft
            </button>
          </div>
        </div>

      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import axios from 'axios'

// ── Props & emits ─────────────────────────────────────────
const props = defineProps({
  modelValue: Boolean,          // v-model: drawer open/close
  warehouses: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  allUnits:   { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue', 'saved'])

// ── Composables ───────────────────────────────────────────
const toast = useToast()
const auth  = useAuthStore()
const setTimeout = window.setTimeout
const clearTimeout = window.clearTimeout

// ── State ─────────────────────────────────────────────────
const saving       = ref(false)
const loadingStock = ref(false)
const warehouseStocks = ref([])
const bodyRef = ref(null)

// ── Helpers ───────────────────────────────────────────────
let _uid = 0
function uid() { return ++_uid }

function defaultItem() {
  return {
    _uid: uid(),
    item_id: null, part_number: '', nama_barang: '', kode_unit: '', tipe_unit: '',
    kode_unit_list: [],   // array of { kode, tipe }
    qty: '', satuan: '', keterangan: '',
    is_new_item: false, new_part_number: '', new_category_id: '', new_brand: '', new_min_stock: 0,
    _searchStok: '', _showDropdown: false, _stok: undefined,
    _unitSearch: '', _showUnitDrop: false, _unitDropResults: [],
    _partExistsWarning: false, _partExistsItem: null, _partDupInForm: false,
  }
}

function defaultForm() {
  return { type: 'part', warehouse_id: '', needed_date: '', notes: '', items: [defaultItem()] }
}

const form = ref(defaultForm())

function isItemFilled(item) {
  return !!(item.nama_barang && item.qty && item.satuan)
}

const stepDone = computed(() => {
  let s = 0
  if (form.value.type) s = 1
  if (form.value.warehouse_id) s = 2
  if (form.value.items.some(i => isItemFilled(i))) s = 3
  return s
})

const warehouseName = computed(() => {
  const w = props.warehouses.find(w => w.id === form.value.warehouse_id)
  return w?.name || ''
})

// ── Reset form when drawer opens ──────────────────────────
watch(() => props.modelValue, (val) => {
  if (val) {
    form.value = defaultForm()
    if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) {
      form.value.warehouse_id = auth.userWarehouseId
    }
  }
})

// ── Load stocks when warehouse changes ────────────────────
watch(() => form.value.warehouse_id, async (warehouseId) => {
  warehouseStocks.value = []
  if (!warehouseId) return
  loadingStock.value = true
  try {
    const res = await axios.get(`/warehouses/${warehouseId}/stocks`, { params: { per_page: 999 } })
    warehouseStocks.value = res.data.data || []
  } catch { warehouseStocks.value = [] }
  finally { loadingStock.value = false }
})

// ── Item management ───────────────────────────────────────
function addItem() {
  form.value.items.push(defaultItem())
}

async function addItemAndScroll() {
  addItem()
  await nextTick()
  // Scroll ke item baru (last item)
  const idx = form.value.items.length - 1
  const el = document.getElementById(`pm-item-${idx}`)
  if (el && bodyRef.value) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    // Focus pada input pertama item baru
    setTimeout(() => {
      const firstInput = el.querySelector('input:not([readonly]), select')
      if (firstInput) firstInput.focus()
    }, 350)
  }
}

function removeItem(idx) {
  form.value.items.splice(idx, 1)
}

function duplicateItem(idx) {
  const src = form.value.items[idx]
  const copy = {
    ...defaultItem(),
    // Salin field data (bukan UI helpers)
    item_id: src.item_id,
    part_number: src.part_number,
    nama_barang: src.nama_barang,
    kode_unit: src.kode_unit,
    tipe_unit: src.tipe_unit,
    kode_unit_list: JSON.parse(JSON.stringify(src.kode_unit_list || [])),
    satuan: src.satuan,
    is_new_item: src.is_new_item,
    new_part_number: src.new_part_number,
    new_category_id: src.new_category_id,
    new_brand: src.new_brand,
    new_min_stock: src.new_min_stock,
    _searchStok: src._searchStok,
    _stok: src._stok,
    _unitSearch: src._unitSearch,
    qty: '', // Kosongkan qty agar user isi sendiri
  }
  form.value.items.splice(idx + 1, 0, copy)
  nextTick(() => {
    const el = document.getElementById(`pm-item-${idx + 1}`)
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    setTimeout(() => {
      const qtyInput = el?.querySelector('input[type="number"]')
      if (qtyInput) qtyInput.focus()
    }, 350)
  })
}

// ── Search / stock helpers ────────────────────────────────
function filteredStocks(item) {
  const q = (item._searchStok || '').toLowerCase().trim()
  if (!q) return warehouseStocks.value.slice(0, 10)
  return warehouseStocks.value.filter(s =>
    s.item?.name?.toLowerCase().includes(q) ||
    s.item?.part_number?.toLowerCase().includes(q) ||
    s.item?.category?.name?.toLowerCase().includes(q)
  )
}

function pilihBarangDariStok(item, stok) {
  item.item_id      = stok.item?.id || null
  item.part_number  = stok.item?.part_number || ''
  item.nama_barang  = stok.item?.name || ''
  item.satuan       = stok.item?.unit || ''
  item._stok        = stok.qty
  item._searchStok  = stok.item?.name || ''
  item._showDropdown = false
  item.is_new_item  = false
}

function clearItemSearch(item) {
  item._searchStok = ''
  item._showDropdown = false
  item.nama_barang = ''
  item.part_number = ''
  item.satuan = ''
  item._stok = undefined
  item.item_id = null
}

function aktivasiBarangBaru(item) {
  item.is_new_item = true
  item.nama_barang = item._searchStok || ''
  item.part_number = ''
  item.new_part_number = ''
  item.new_category_id = ''
  item.new_brand = ''
  item.new_min_stock = 0
  item._showDropdown = false
  item._stok = undefined
  item.item_id = null
  item._partDupInForm = false
  item._partExistsWarning = false
}

function batalBarangBaru(item) {
  item.is_new_item = false
  item.nama_barang = ''
  item.part_number = ''
  item._searchStok = ''
  item._showDropdown = false
  item.item_id = null
  item._stok = undefined
  item._partExistsWarning = false
  item._partExistsItem = null
}

let partCheckTimer = null
async function checkDupInForm(item) {
  const pn = (item.new_part_number || '').trim().toLowerCase()
  if (!pn) { item._partDupInForm = false; return }
  item._partDupInForm = form.value.items.some(
    other => other !== item && (other.new_part_number || '').trim().toLowerCase() === pn
  )
}

function checkPartNumberExists(item) {
  item._partExistsWarning = false
  item._partExistsItem = null
  const pn = (item.new_part_number || '').trim()
  if (pn.length < 2) return
  clearTimeout(partCheckTimer)
  partCheckTimer = setTimeout(async () => {
    try {
      const res = await axios.get('/items', { params: { search: pn, per_page: 5 } })
      const found = (res.data.data || []).find(i => i.part_number?.toLowerCase() === pn.toLowerCase())
      if (found) { item._partExistsWarning = true; item._partExistsItem = found }
    } catch {}
  }, 400)
}

function pakaiBarangMaster(item) {
  const master = item._partExistsItem
  if (!master) return
  item.is_new_item = false
  item._partExistsWarning = false
  item._partExistsItem = null
  item.item_id = master.id
  item.part_number = master.part_number || ''
  item.nama_barang = master.name || ''
  item.satuan = master.unit || ''
  item._searchStok = master.name || ''
  item._showDropdown = false
  const stokData = warehouseStocks.value.find(s => s.item_id === master.id)
  item._stok = stokData?.qty ?? 0
}

// ── Unit search ───────────────────────────────────────────
function filterUnitsForItem(item) {
  const q = (item._unitSearch || '').toLowerCase()
  item._unitDropResults = q.length < 1
    ? props.allUnits.slice(0, 10)
    : props.allUnits.filter(u =>
        u.unit_code?.toLowerCase().includes(q) ||
        u.type_unit?.toLowerCase().includes(q) ||
        u.brand?.toLowerCase().includes(q)
      ).slice(0, 15)
}
function hideSearchDrop(item) { setTimeout(() => { item._showDropdown = false }, 200) }
function hideUnitDrop(item) { setTimeout(() => { item._showUnitDrop = false }, 150) }

function isUnitAlreadyAdded(item, u) {
  return (item.kode_unit_list || []).some(k => k.kode === u.unit_code)
}

function addUnitToItem(item, u) {
  if (!item.kode_unit_list) item.kode_unit_list = []
  // Cegah duplikat
  if (isUnitAlreadyAdded(item, u)) {
    item._unitSearch   = ''
    item._showUnitDrop = false
    return
  }
  item.kode_unit_list.push({ kode: u.unit_code || '', tipe: u.type_unit || '' })
  // Sync field kode_unit & tipe_unit (string, untuk backward compat)
  item.kode_unit = item.kode_unit_list.map(k => k.kode).join(', ')
  item.tipe_unit = item.kode_unit_list.map(k => k.tipe).filter(Boolean).join(', ')
  item._unitSearch   = ''
  item._showUnitDrop = false
}

function removeUnitFromItem(item, idx) {
  item.kode_unit_list.splice(idx, 1)
  item.kode_unit = item.kode_unit_list.map(k => k.kode).join(', ')
  item.tipe_unit = item.kode_unit_list.map(k => k.tipe).filter(Boolean).join(', ')
}

// ── Save ─────────────────────────────────────────────────
async function savePM() {
  if (!form.value.type)          return toast.error('Pilih tipe permintaan')
  if (!form.value.warehouse_id)  return toast.error('Pilih gudang terlebih dahulu')

  const seenParts = []
  const seenItemIds = []
  for (const i of form.value.items) {
    if (!i.nama_barang || !i.qty || !i.satuan)
      return toast.error('Lengkapi semua field wajib pada setiap barang')
    if (i.is_new_item) {
      if (!i.new_part_number)
        return toast.error(`Part Number wajib diisi untuk barang baru: "${i.nama_barang}"`)
      if (!i.new_category_id)
        return toast.error(`Kategori wajib dipilih untuk barang baru: "${i.nama_barang}"`)
      if (i._partExistsWarning)
        return toast.error(`Part Number "${i.new_part_number}" sudah ada di master. Gunakan barang yang sudah ada atau ganti Part Number.`)
      if (i._partDupInForm)
        return toast.error(`Part Number "${i.new_part_number}" sudah dipakai oleh barang lain dalam daftar ini.`)
      const pn = i.new_part_number.toLowerCase()
      if (seenParts.includes(pn))
        return toast.error(`Duplikat: Part Number "${i.new_part_number}" sudah ada di daftar barang ini.`)
      seenParts.push(pn)
    } else if (i.item_id) {
      if (seenItemIds.includes(i.item_id))
        return toast.error(`Duplikat: "${i.nama_barang}" sudah ada di daftar barang ini.`)
      seenItemIds.push(i.item_id)
      if (i.part_number) {
        const pn = i.part_number.toLowerCase()
        if (seenParts.includes(pn))
          return toast.error(`Duplikat: Part Number "${i.part_number}" sudah ada di daftar barang ini.`)
        seenParts.push(pn)
      }
    } else {
      if (!i.part_number)
        return toast.error(`"${i.nama_barang}" belum dipilih dari master atau Part Number belum diisi. Cari barang dari daftar atau klik "Barang Baru".`)
      const pn = i.part_number.toLowerCase()
      if (seenParts.includes(pn))
        return toast.error(`Duplikat: Part Number "${i.part_number}" sudah ada di daftar barang ini.`)
      seenParts.push(pn)
    }
  }
  saving.value = true
  try {
    await axios.post('/permintaan-material', form.value)
    toast.success('Permintaan material berhasil dibuat')
    emit('saved')
    close()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan')
  } finally { saving.value = false }
}

// ── Open / Close ──────────────────────────────────────────
function close() { emit('update:modelValue', false) }

function onBackdropClick() {
  if (confirm('Tutup form? Data yang belum disimpan akan hilang.')) close()
}

// expose untuk parent yg masih butuh ref
defineExpose({ addItem, close })
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════
   BACKDROP
══════════════════════════════════════════════════════════ */
.pm-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 1040;
  backdrop-filter: blur(2px);
}
.drawer-backdrop-enter-active,
.drawer-backdrop-leave-active { transition: opacity .25s ease; }
.drawer-backdrop-enter-from,
.drawer-backdrop-leave-to   { opacity: 0; }

/* ══════════════════════════════════════════════════════════
   DRAWER PANEL
══════════════════════════════════════════════════════════ */
.pm-drawer {
  position: fixed;
  top: 0; right: 0; bottom: 0;
  width: min(680px, 100vw);
  z-index: 1050;
  display: flex;
  flex-direction: column;
  background: #f8fafc;
  box-shadow: -6px 0 40px rgba(0,0,0,.18);
}

/* Slide-in transition */
.drawer-slide-enter-active,
.drawer-slide-leave-active { transition: transform .3s cubic-bezier(.4,0,.2,1); }
.drawer-slide-enter-from,
.drawer-slide-leave-to    { transform: translateX(100%); }

/* ── HEADER ────────────────────────────────────────────── */
.pm-drawer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  background: #1a3a5c;
  color: #fff;
  flex-shrink: 0;
}
.pm-drawer__header-left { display: flex; align-items: center; gap: 12px; }
.pm-drawer__icon {
  width: 36px; height: 36px;
  background: rgba(255,255,255,.15);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
}
.pm-drawer__title   { font-size: .95rem; font-weight: 700; line-height: 1.2; }
.pm-drawer__subtitle{ font-size: .75rem; opacity: .8; margin-top: 1px; }
.pm-drawer__close {
  background: transparent; border: none; color: rgba(255,255,255,.7);
  font-size: 1rem; cursor: pointer; padding: 6px 8px; border-radius: 6px;
  transition: all .15s;
}
.pm-drawer__close:hover { background: rgba(255,255,255,.15); color: #fff; }

/* ── PROGRESS ──────────────────────────────────────────── */
.pm-drawer__progress-wrap {
  display: flex;
  align-items: center;
  padding: 10px 24px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.pm-drawer__progress-step {
  display: flex; align-items: center; gap: 6px;
  font-size: .75rem; color: #94a3b8; font-weight: 500;
  transition: color .2s;
}
.pm-drawer__progress-step.active  { color: #1a3a5c; }
.pm-drawer__progress-step.done    { color: #16a34a; }
.pm-drawer__progress-dot {
  width: 22px; height: 22px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .7rem; font-weight: 700;
  background: #e2e8f0; color: #94a3b8;
  transition: all .2s;
}
.pm-drawer__progress-step.active .pm-drawer__progress-dot  { background: #1a3a5c; color: #fff; }
.pm-drawer__progress-step.done   .pm-drawer__progress-dot  { background: #16a34a; color: #fff; }
.pm-drawer__progress-line {
  flex: 1; height: 2px; background: #e2e8f0; margin: 0 8px;
  transition: background .2s;
}
.pm-drawer__progress-line.active { background: #1a3a5c; }

/* ── SCROLL BODY ───────────────────────────────────────── */
.pm-drawer__body {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px 8px;
  scroll-behavior: smooth;
}
.pm-drawer__body::-webkit-scrollbar { width: 5px; }
.pm-drawer__body::-webkit-scrollbar-track { background: transparent; }
.pm-drawer__body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* ── SECTIONS ──────────────────────────────────────────── */
.pm-section {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
}
.pm-section__label {
  font-size: .8rem; font-weight: 700; color: #475569;
  text-transform: uppercase; letter-spacing: .5px;
  margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
}
.pm-section__num {
  width: 20px; height: 20px; border-radius: 50%;
  background: #1a3a5c; color: #fff;
  font-size: .7rem; font-weight: 800;
  display: inline-flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.pm-label {
  display: block; font-size: .76rem; font-weight: 600; color: #475569; margin-bottom: 3px;
}

/* ── TYPE CARDS ────────────────────────────────────────── */
.pm-type-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.pm-type-card {
  position: relative;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px 12px;
  text-align: center;
  background: #fff;
  cursor: pointer;
  transition: all .18s;
}
.pm-type-card:hover { border-color: #94a3b8; transform: translateY(-1px); }
.pm-type-card--active-part   { border-color: #1a3a5c; background: #eff6ff; }
.pm-type-card--active-office { border-color: #0891b2; background: #ecfeff; }
.pm-type-card__icon  { font-size: 1.5rem; display: block; margin-bottom: 6px; }
.pm-type-card__name  { font-size: .85rem; font-weight: 700; color: #1e293b; }
.pm-type-card__desc  { font-size: .72rem; color: #64748b; margin-top: 2px; }
.pm-type-card__check { position: absolute; top: 8px; right: 8px; font-size: .9rem; color: #1a3a5c; }

/* ── FLOW BADGE ────────────────────────────────────────── */
.pm-flow-badge {
  margin-top: 10px; padding: 8px 12px; border-radius: 8px;
  font-size: .77rem;
}
.pm-flow-badge--part   { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
.pm-flow-badge--office { background: #ecfeff; color: #0e7490; border: 1px solid #a5f3fc; }

/* ── ITEM CARDS ────────────────────────────────────────── */
.pm-item-card {
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  margin-bottom: 10px;
  transition: border-color .2s, box-shadow .2s;
  overflow: visible;
}
.pm-item-card--new    { border-color: #fbbf24; }
.pm-item-card--filled { border-color: #86efac; }
.pm-item-card:focus-within {
  box-shadow: 0 0 0 3px rgba(26,58,92,.12);
  border-color: #1a3a5c;
}

.pm-item-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 9px 12px;
  background: #f8fafc;
  border-bottom: 1px solid #f1f5f9;
  border-radius: 9px 9px 0 0;
}
.pm-item-badge {
  display: inline-flex; align-items: center; justify-content: center;
  width: 22px; height: 22px;
  background: #1a3a5c; color: #fff;
  border-radius: 50%;
  font-size: .72rem; font-weight: 700;
}
.pm-item-card__body {
  padding: 12px;
}

/* ── SEARCH DROPDOWN ───────────────────────────────────── */
.pm-search-dropdown {
  position: absolute;
  top: calc(100% + 2px); left: 0; right: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,.12);
  z-index: 1060;
  max-height: 280px;
  overflow-y: auto;
}
.pm-search-dropdown__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 9px 12px;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  transition: background .12s;
}
.pm-search-dropdown__item:hover { background: #f0f9ff; }
.pm-search-dropdown__item-name  { font-size: .83rem; font-weight: 600; color: #1e293b; }
.pm-search-dropdown__footer {
  padding: 8px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  position: sticky; bottom: 0;
}

/* ── NEW ITEM BOX ──────────────────────────────────────── */
.pm-new-item-box {
  background: #fffdf0;
  border: 1.5px solid #fbbf24;
  border-radius: 8px;
  padding: 10px;
  margin-top: 6px; margin-bottom: 6px;
}

/* ── HINT BOX ──────────────────────────────────────────── */
.pm-hint-box {
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: .8rem;
  color: #0369a1;
  margin-bottom: 12px;
}

/* ── ADD AREA ──────────────────────────────────────────── */
.pm-add-area {
  padding: 4px 0 8px;
}
.pm-add-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px;
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  background: #f8fafc;
  color: #1a3a5c;
  font-size: .85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .18s;
}
.pm-add-btn:hover {
  border-color: #1a3a5c;
  background: #eff6ff;
  transform: translateY(-1px);
}
.pm-add-btn__count {
  margin-left: auto;
  font-size: .72rem;
  background: #1a3a5c;
  color: #fff;
  padding: 2px 8px;
  border-radius: 20px;
}

/* ── SUMMARY ───────────────────────────────────────────── */
.pm-summary {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 8px;
}
.pm-summary__row {
  display: flex; justify-content: space-between;
  font-size: .8rem; padding: 3px 0;
}
.pm-summary__label { color: #64748b; }
.pm-summary__val   { font-weight: 600; color: #1e293b; }

/* ── FOOTER ────────────────────────────────────────────── */
.pm-drawer__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  background: #fff;
  border-top: 1px solid #e2e8f0;
  flex-shrink: 0;
  box-shadow: 0 -4px 16px rgba(0,0,0,.06);
}
.pm-add-btn-footer {
  display: inline-flex; align-items: center;
  padding: 6px 14px;
  border: 1.5px solid #1a3a5c;
  border-radius: 6px;
  background: #fff;
  color: #1a3a5c;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
}
.pm-add-btn-footer:hover { background: #eff6ff; }

/* ── TRANSITIONS ───────────────────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

.item-list-enter-active { transition: all .25s ease; }
.item-list-leave-active { transition: all .2s ease; }
.item-list-enter-from   { opacity: 0; transform: translateY(-10px); }
.item-list-leave-to     { opacity: 0; transform: translateX(20px); }

/* ── RESPONSIVE (mobile) ───────────────────────────────── */
@media (max-width: 576px) {
  .pm-drawer { width: 100vw; }
  .pm-type-grid { grid-template-columns: 1fr 1fr; }
  .pm-drawer__body { padding: 12px; }
}
</style>