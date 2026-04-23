<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Permintaan Material</h5>
        <small class="text-muted">Permintaan sparepart & perlengkapan office</small>
      </div>
      <button v-if="can('create-pm')" class="btn btn-csm-primary btn-sm" @click="openCreate">
        <i class="bi bi-plus-circle me-1"></i>Buat Permintaan
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-2">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. PM..." @input="debouncedLoad" />
          </div>
          <div class="col-md-2">
            <select v-model="filters.type" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Tipe</option>
              <option value="part">🔧 MR Part</option>
              <option value="office">🏢 MR Office</option>
            </select>
          </div>
          <div class="col-md-3">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="pending_chief">Menunggu Chief Mekanik</option>
              <option value="pending_manager">Menunggu Manager</option>
              <option value="pending_ho">Menunggu Admin HO</option>
              <option value="manager_approved">Disetujui Manager</option>
              <option value="approved">Disetujui HO</option>
              <option value="pending_purchasing">Menunggu Pengajuan PO</option>
              <option value="purchasing">Proses Purchasing</option>
              <option value="partial_ordered">Sebagian PO</option>
              <option value="bon_pengeluaran">Bon Pengeluaran</option>
              <option value="completed">Selesai</option>
              <option value="rejected">Ditolak</option>
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
                <th>No. PM</th>
                <th>Tipe</th>
                <th>Gudang</th>
                <th>Item</th>
                <th>Status</th>
                <th>Diajukan Oleh</th>
                <th>Tanggal</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="8" class="text-center text-muted py-5">Tidak ada data permintaan material</td>
              </tr>
              <tr v-for="pm in list" :key="pm.id">
                <td>
                  <router-link :to="`/permintaan-material/${pm.id}`" class="fw-semibold text-primary text-decoration-none">
                    {{ pm.nomor }}
                  </router-link>
                </td>
                <td>
                  <span class="badge" :class="pm.type === 'part' ? 'bg-primary' : 'bg-info text-dark'">
                    {{ pm.type === 'part' ? '🔧 Part' : '🏢 Office' }}
                  </span>
                </td>
                <td><small>{{ pm.warehouse?.name }}</small></td>
                <td><span class="badge bg-secondary rounded-pill">{{ pm.items_count }} item</span></td>
                <td><span class="badge" :class="statusClass(pm.status)">{{ statusLabel(pm.status) }}</span></td>
                <td><small>{{ pm.requester?.name }}</small></td>
                <td><small class="text-muted">{{ $formatDate(pm.created_at) }}</small></td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <router-link :to="`/permintaan-material/${pm.id}`" class="btn btn-outline-primary" title="Detail">
                      <i class="bi bi-eye"></i>
                    </router-link>
                    <button class="btn btn-outline-danger" title="Print / PDF" @click="printPMDirect(pm)">
                      <i class="bi bi-printer"></i>
                    </button>
                    <button v-if="pm.status === 'draft' && can('create-pm')"
                      class="btn btn-outline-info" title="Submit" @click="doSubmit(pm)">
                      <i class="bi bi-send"></i>
                    </button>
                    <button v-if="pm.status === 'pending_chief' && can('authorize-mr-chief')"
                      class="btn btn-outline-warning" title="Otorisasi Chief Mekanik" @click="doAuthorizeChief(pm)">
                      <i class="bi bi-person-check"></i>
                    </button>
                    <button v-if="pm.status === 'pending_manager' && can('approve-mr-manager')"
                      class="btn btn-outline-success" title="Approve Manager" @click="doApproveManager(pm)">
                      <i class="bi bi-check-circle"></i>
                    </button>
                    <button v-if="pm.status === 'pending_ho' && can('approve-pm-ho')"
                      class="btn btn-outline-success" title="Approve Admin HO" @click="doApproveHO(pm)">
                      <i class="bi bi-check-all"></i>
                    </button>
                    <button v-if="canReject(pm)"
                      class="btn btn-outline-danger" title="Tolak" @click="openReject(pm)">
                      <i class="bi bi-x-circle"></i>
                    </button>
                    <button v-if="pm.status === 'draft' && can('create-pm')"
                      class="btn btn-outline-danger" title="Hapus" @click="doDelete(pm)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">Total {{ meta.total }} permintaan</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Buat Permintaan -->
    <div class="modal fade" id="modalCreatePM" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-clipboard-plus me-2"></i>Buat Permintaan Material</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Pilihan Tipe -->
            <div class="mb-3">
              <label class="form-label small fw-semibold">Tipe Permintaan <span class="text-danger">*</span></label>
              <div class="d-flex gap-2">
                <div class="flex-grow-1 border rounded p-3 text-center"
                  :class="form.type === 'part' ? 'border-primary bg-primary bg-opacity-10' : ''"
                  style="cursor:pointer;" @click="form.type = 'part'">
                  <i class="bi bi-tools fs-4 d-block mb-1 text-primary"></i>
                  <div class="fw-semibold small">MR Part</div>
                  <small class="text-muted">Sparepart & material alat berat</small>
                </div>
                <div class="flex-grow-1 border rounded p-3 text-center"
                  :class="form.type === 'office' ? 'border-info bg-info bg-opacity-10' : ''"
                  style="cursor:pointer;" @click="form.type = 'office'">
                  <i class="bi bi-building fs-4 d-block mb-1 text-info"></i>
                  <div class="fw-semibold small">MR Office</div>
                  <small class="text-muted">Perlengkapan & kebutuhan kantor</small>
                </div>
              </div>
              <div v-if="form.type === 'part'" class="alert alert-primary py-2 small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Alur: <strong>Submit → Chief Mekanik → Manager → Admin HO → Bon Pengeluaran / PO</strong>
              </div>
              <div v-if="form.type === 'office'" class="alert alert-info py-2 small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Alur: <strong>Submit → Admin HO → Bon Pengeluaran / Surat Jalan</strong>
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Gudang / Site <span class="text-danger">*</span></label>
                <select v-model="form.warehouse_id" class="form-select form-select-sm">
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
                <label class="form-label small fw-semibold">Tanggal Dibutuhkan</label>
                <input v-model="form.needed_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Catatan Umum</label>
                <input v-model="form.notes" type="text" class="form-control form-control-sm" placeholder="Catatan tambahan..." />
              </div>
            </div>

            <hr class="my-2" />

            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-semibold small">Daftar Barang yang Diminta</span>
              <button class="btn btn-outline-primary btn-sm" @click="addItem">
                <i class="bi bi-plus me-1"></i>Tambah Barang
              </button>
            </div>

            <div v-for="(item, idx) in form.items" :key="idx" class="csm-card mb-2 border"
              :class="item.is_new_item ? 'border-warning' : ''">
              <div class="csm-card-body py-2 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary">Barang {{ idx + 1 }}</span>
                    <span v-if="item.is_new_item" class="badge bg-warning text-dark">
                      <i class="bi bi-plus-circle me-1"></i>Barang Baru
                    </span>
                  </div>
                  <button v-if="form.items.length > 1" class="btn btn-xs btn-outline-danger" @click="removeItem(idx)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
                <div class="row g-2">
                  <div class="col-12">
                    <label class="form-label small fw-semibold">Nama Barang / Deskripsi <span class="text-danger">*</span></label>

                    <!-- Search sparepart jika gudang dipilih & bukan mode barang baru -->
                    <div v-if="form.warehouse_id && !item.is_new_item" class="mb-2 position-relative">
                      <div v-if="loadingStock" class="text-center py-2">
                        <div class="csm-spinner"></div><small class="text-muted ms-2">Memuat data sparepart...</small>
                      </div>
                      <template v-else>
                        <div class="input-group input-group-sm">
                          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                          <input
                            v-model="item._searchStok"
                            type="text"
                            class="form-control form-control-sm border-start-0"
                            placeholder="Cari nama part / part number..."
                            @input="item._showDropdown = true"
                            @focus="item._showDropdown = true"
                            @blur="() => setTimeout(() => { item._showDropdown = false }, 200)"
                            @click.stop
                          />
                          <button v-if="item._searchStok" type="button" class="btn btn-outline-secondary btn-sm"
                            @mousedown.prevent="clearItemSearch(item)">
                            <i class="bi bi-x"></i>
                          </button>
                        </div>
                        <!-- Dropdown hasil pencarian -->
                        <div v-if="item._showDropdown && item._searchStok"
                          class="border rounded mt-1 shadow-sm"
                          style="background:white; max-height:230px; overflow-y:auto; position:absolute; z-index:1050; width:100%;">
                          <div v-if="filteredStocks(item).length">
                            <div v-for="stok in filteredStocks(item)" :key="stok.id"
                              class="d-flex align-items-center justify-content-between py-2 px-2 border-bottom"
                              :style="stok.qty > 0 ? 'cursor:pointer;' : 'cursor:pointer;background:#fffbf0;'"
                              @mousedown.prevent="pilihBarangDariStok(item, stok)">
                              <div>
                                <div class="fw-semibold small">{{ stok.item?.name }}</div>
                                <small class="text-muted">
                                  <span v-if="stok.item?.part_number" class="me-1 text-primary fw-semibold">{{ stok.item.part_number }}</span>
                                  <span v-if="stok.item?.category?.name">· {{ stok.item.category.name }}</span>
                                </small>
                              </div>
                              <div class="text-end ms-2 flex-shrink-0">
                                <span class="badge" :class="stok.qty > 0 ? 'bg-success' : 'bg-warning text-dark'">
                                  Stok: {{ stok.qty }} {{ stok.item?.unit }}
                                </span>
                              </div>
                            </div>
                          </div>
                          <!-- Jika tidak ketemu, tawarkan tambah baru -->
                          <div class="px-2 py-2 border-top bg-light">
                            <div v-if="!filteredStocks(item).length" class="mb-2 text-center">
                              <small class="text-muted">Tidak ditemukan: <strong>{{ item._searchStok }}</strong></small>
                            </div>
                            <button type="button" class="btn btn-warning btn-sm w-100"
                              @mousedown.prevent="aktivasiBarangBaru(item)">
                              <i class="bi bi-plus-circle me-1"></i>
                              Barang "<strong>{{ item._searchStok }}</strong>" belum ada — Daftarkan sebagai Barang Baru
                            </button>
                          </div>
                        </div>
                      </template>
                    </div>

                    <!-- Badge stok jika barang dipilih dari master -->
                    <div v-if="!item.is_new_item && item._stok !== undefined" class="mb-1">
                      <span class="badge" :class="item._stok > 0 ? 'bg-success' : 'bg-warning text-dark'">
                        <i class="bi bi-box me-1"></i>
                        {{ item._stok > 0 ? `Stok tersedia: ${item._stok} ${item.satuan}` : 'Stok kosong di gudang ini' }}
                      </span>
                    </div>

                    <!-- Form barang baru (jika is_new_item = true) -->
                    <div v-if="item.is_new_item" class="border border-warning rounded p-2 mb-2" style="background:#fffdf0;">
                      <div class="d-flex align-items-center justify-content-between mb-2">
                        <small class="fw-semibold text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Data Barang Baru — akan otomatis terdaftar ke Master Barang</small>
                        <button type="button" class="btn btn-xs btn-outline-secondary" @click="batalBarangBaru(item)">
                          <i class="bi bi-arrow-left me-1"></i>Kembali ke Pencarian
                        </button>
                      </div>
                      <div class="row g-2">
                        <div class="col-md-6">
                          <label class="form-label small fw-semibold">Part Number <span class="text-danger">*</span></label>
                          <input v-model="item.new_part_number" type="text" class="form-control form-control-sm"
                            :class="item._partExistsWarning ? 'border-danger' : ''"
                            placeholder="Contoh: FLT-OLI-320, 1234567890..."
                            @input="checkPartNumberExists(item)" />
                          <!-- Warning: part number sudah ada di master, tawarkan pilih dari master -->
                          <div v-if="item._partExistsWarning" class="mt-1 p-2 rounded border border-danger" style="background:#fff5f5;">
                            <small class="text-danger fw-semibold d-block mb-1">
                              <i class="bi bi-exclamation-circle me-1"></i>
                              Part Number "{{ item.new_part_number }}" sudah ada di Master Barang.
                            </small>
                            <button type="button" class="btn btn-sm btn-danger w-100" @click="pakaiBarangMaster(item)">
                              <i class="bi bi-box-seam me-1"></i>Gunakan Barang yang Sudah Ada
                            </button>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
                          <select v-model="item.new_category_id" class="form-select form-select-sm">
                            <option value="">-- Pilih Kategori --</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label small fw-semibold">Brand / Merk</label>
                          <input v-model="item.new_brand" type="text" class="form-control form-control-sm" placeholder="Contoh: CAT, Komatsu, Fleetguard..." />
                        </div>
                        <div class="col-md-6">
                          <label class="form-label small fw-semibold">Stok Minimum</label>
                          <input v-model="item.new_min_stock" type="number" class="form-control form-control-sm" min="0" placeholder="0" />
                        </div>
                      </div>
                    </div>

                    <!-- Nama barang text input -->
                    <input v-model="item.nama_barang" type="text" class="form-control form-control-sm"
                      :class="item.is_new_item ? 'border-warning' : ''"
                      :placeholder="form.type === 'part' ? 'Contoh: Filter Oli Excavator CAT 320...' : 'Contoh: Kertas HVS A4, Tinta Printer...'"
                      :readonly="!item.is_new_item && item.item_id" />
                  </div>
                  <template v-if="form.type === 'part'">
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Part Number</label>
                      <input v-model="item.part_number" type="text" class="form-control form-control-sm"
                        :readonly="!item.is_new_item && item.item_id"
                        placeholder="Contoh: FLT-OLI-320, XAE-001..."
                        :class="item.item_id && !item.is_new_item ? 'bg-light' : ''" />
                      <div v-if="item.item_id && !item.is_new_item" class="form-text text-muted" style="font-size:0.7rem;">
                        <i class="bi bi-lock me-1"></i>Dari master barang
                      </div>
                    </div>
                    <div class="col-md-6 position-relative">
                      <label class="form-label small fw-semibold">Kode Unit / Alat Berat</label>
                      <input
                        v-model="item._unitSearch"
                        type="text"
                        class="form-control form-control-sm"
                        placeholder="Cari kode unit... (CSM 0038)"
                        autocomplete="off"
                        @input="filterUnitsForItem(item)"
                        @focus="item._showUnitDrop = true; filterUnitsForItem(item)"
                        @blur="hideUnitDrop(item)"
                      />
                      <ul v-if="item._showUnitDrop && item._unitDropResults.length"
                        class="list-group position-absolute w-100 shadow-sm"
                        style="z-index:9999;max-height:180px;overflow-y:auto;top:100%;left:0">
                        <li
                          v-for="u in item._unitDropResults" :key="u.id"
                          class="list-group-item list-group-item-action py-1 px-2 small"
                          style="cursor:pointer"
                          @mousedown.prevent="selectUnitForItem(item, u)"
                        >
                          <strong>{{ u.unit_code }}</strong>
                          <span class="text-muted ms-1">— {{ u.type_unit }} {{ u.brand }}</span>
                        </li>
                      </ul>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Tipe Unit</label>
                      <input v-model="item.tipe_unit" type="text" class="form-control form-control-sm" placeholder="Contoh: CAT 320D, ZX350" readonly :class="item.kode_unit ? 'bg-light' : ''" />
                    </div>
                  </template>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">Jumlah <span class="text-danger">*</span></label>
                    <input v-model="item.qty" type="number" class="form-control form-control-sm" min="0.01" step="0.01" placeholder="0" />
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">Satuan <span class="text-danger">*</span></label>
                    <input v-model="item.satuan" type="text" class="form-control form-control-sm" list="satuanList" placeholder="Pcs, Liter, Set..." />
                    <datalist id="satuanList">
                      <option value="Pcs" /><option value="Set" /><option value="Liter" /><option value="Kg" />
                      <option value="Meter" /><option value="Roll" /><option value="Rim" /><option value="Botol" />
                    </datalist>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">Keterangan</label>
                    <input v-model="item.keterangan" type="text" class="form-control form-control-sm" placeholder="Info tambahan..." />
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-csm-primary btn-sm" @click="savePM" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>Simpan sebagai Draft
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Tolak -->
    <div class="modal fade" id="modalRejectPM" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Tolak Permintaan</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted mb-2">Permintaan: <strong>{{ selectedPM?.nomor }}</strong></p>
            <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea v-model="rejectReason" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..."></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger btn-sm" @click="doReject" :disabled="acting || !rejectReason">
              <span v-if="acting" class="csm-spinner me-1"></span>Tolak Permintaan
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'

const toast = useToast()
const auth = useAuthStore()
const { listenPM, stopPM } = useRealtime()
const can = (p) => auth.hasPermission(p)
const setTimeout = window.setTimeout

const list = ref([])
const warehouses = ref([])
const categories = ref([])
const allUnits = ref([])
const loading = ref(false)
const saving = ref(false)
const acting = ref(false)
const loadingStock = ref(false)
const warehouseStocks = ref([])
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ search: '', type: '', status: '', date_from: '', date_to: '' })
const selectedPM = ref(null)
const rejectReason = ref('')
const form = ref(defaultForm())
let timer = null

function defaultForm() {
  return { type: 'part', warehouse_id: '', needed_date: '', notes: '', items: [defaultItem()] }
}
function defaultItem() {
  return {
    item_id: null, part_number: '', nama_barang: '', kode_unit: '', tipe_unit: '', qty: '', satuan: '', keterangan: '',
    // Barang baru
    is_new_item: false, new_part_number: '', new_category_id: '', new_brand: '', new_min_stock: 0,
    // UI helpers
    _searchStok: '', _showDropdown: false, _stok: undefined,
    // Unit search helpers
    _unitSearch: '', _showUnitDrop: false, _unitDropResults: [],
    // Part number duplicate check
    _partExistsWarning: false, _partExistsItem: null,
  }
}

function filterUnitsForItem(item) {
  const q = (item._unitSearch || '').toLowerCase()
  item._unitDropResults = q.length < 1
    ? allUnits.value.slice(0, 10)
    : allUnits.value.filter(u =>
        u.unit_code?.toLowerCase().includes(q) ||
        u.type_unit?.toLowerCase().includes(q) ||
        u.brand?.toLowerCase().includes(q)
      ).slice(0, 15)
}
function hideUnitDrop(item) { setTimeout(() => { item._showUnitDrop = false }, 150) }
function selectUnitForItem(item, u) {
  item.kode_unit    = u.unit_code  || ''
  item.tipe_unit    = u.type_unit  || ''
  item._unitSearch  = u.unit_code  || ''
  item._showUnitDrop = false
}

const statusLabel = (s) => ({
  draft: 'Draft',
  pending_chief: 'Menunggu Chief Mekanik',
  pending_manager: 'Menunggu Manager',
  pending_ho: 'Menunggu Admin HO',
  manager_approved: 'Disetujui Manager',
  approved: 'Disetujui HO',
  pending_purchasing: 'Menunggu Pengajuan PO',
  purchasing: 'Proses Purchasing',
  partial_ordered: 'Sebagian PO',
  bon_pengeluaran: 'Bon Pengeluaran',
  completed: 'Selesai',
  rejected: 'Ditolak',
}[s] || s)

const statusClass = (s) => ({
  draft: 'bg-secondary',
  pending_chief: 'bg-warning text-dark',
  pending_manager: 'bg-warning text-dark',
  pending_ho: 'bg-info text-dark',
  manager_approved: 'bg-primary',
  approved: 'bg-primary',
  pending_purchasing: 'bg-warning text-dark',
  purchasing: 'bg-info text-dark',
  partial_ordered: 'bg-warning text-dark',
  bon_pengeluaran: 'bg-info text-dark',
  completed: 'bg-success',
  rejected: 'bg-danger',
}[s] || 'bg-secondary')

function canReject(pm) {
  if (pm.status === 'pending_chief' && can('authorize-mr-chief')) return true
  if (pm.status === 'pending_manager' && can('approve-mr-manager')) return true
  if (['pending_ho', 'manager_approved'].includes(pm.status) && can('approve-pm-ho')) return true
  return false
}

onMounted(async () => {
  const [warehousesRes, categoriesRes] = await Promise.all([
    axios.get('/warehouses'),
    axios.get('/categories'),
  ])
  warehouses.value = warehousesRes.data.data
  categories.value = categoriesRes.data.data || categoriesRes.data
  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) {
    form.value.warehouse_id = auth.userWarehouseId
  }
  // Load units untuk search kode unit
  try {
    const unitsRes = await axios.get('/units', { params: { per_page: 999 } })
    allUnits.value = unitsRes.data.data || []
  } catch {}
  loadData()
  listenPM(() => loadData())
})
onUnmounted(() => stopPM())

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/permintaan-material', { params: { ...filters.value, page: meta.value.page, per_page: 15 } })
    list.value = res.data.data
    meta.value = res.data.meta
  } finally { loading.value = false }
}

function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400) }
function changePage(p) { meta.value.page = p; loadData() }
function resetFilters() { filters.value = { search: '', type: '', status: '', date_from: '', date_to: '' }; meta.value.page = 1; loadData() }

function openCreate() {
  form.value = defaultForm()
  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) {
    form.value.warehouse_id = auth.userWarehouseId
  }
  new Modal('#modalCreatePM').show()
}

function addItem() { form.value.items.push(defaultItem()) }
function removeItem(idx) { form.value.items.splice(idx, 1) }

// Fetch semua sparepart + stok saat warehouse berubah (termasuk stok 0)
watch(() => form.value.warehouse_id, async (warehouseId) => {
  warehouseStocks.value = []
  if (!warehouseId) return
  loadingStock.value = true
  try {
    const res = await axios.get(`/warehouses/${warehouseId}/stocks`, { params: { per_page: 999 } })
    warehouseStocks.value = (res.data.data || [])
  } catch { warehouseStocks.value = [] }
  finally { loadingStock.value = false }
})

// Aktifkan mode barang baru — isi nama dari teks pencarian
function aktivasiBarangBaru(item) {
  item.is_new_item = true
  item.nama_barang = item._searchStok || ''
  item.new_part_number = ''
  item.new_category_id = ''
  item.new_brand = ''
  item.new_min_stock = 0
  item._showDropdown = false
  item._stok = undefined
  item.item_id = null
}

// Batal mode barang baru — kembali ke pencarian
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

// Cek real-time apakah part number yang diketik sudah ada di master
let partCheckTimer = null
async function checkPartNumberExists(item) {
  item._partExistsWarning = false
  item._partExistsItem = null
  const pn = (item.new_part_number || '').trim()
  if (pn.length < 2) return
  clearTimeout(partCheckTimer)
  partCheckTimer = setTimeout(async () => {
    try {
      const res = await axios.get('/items', { params: { search: pn, per_page: 5 } })
      const found = (res.data.data || []).find(i =>
        i.part_number?.toLowerCase() === pn.toLowerCase()
      )
      if (found) {
        item._partExistsWarning = true
        item._partExistsItem = found
      }
    } catch {}
  }, 400)
}

// Pakai barang dari master (bukan buat baru)
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
  // Cek stok di gudang yang dipilih
  const stokData = warehouseStocks.value.find(s => s.item_id === master.id)
  item._stok = stokData?.qty ?? 0
}

// Clear search dan reset item
function clearItemSearch(item) {
  item._searchStok = ''
  item._showDropdown = false
  item.nama_barang = ''
  item.part_number = ''
  item.satuan = ''
  item._stok = undefined
  item.item_id = null
}

// Toggle stok picker (legacy - tidak digunakan lagi)
function toggleStok(item) {
  item._showStock = !item._showStock
  if (item._showStock) item._searchStok = ''
}

// Filter stok berdasarkan pencarian
function filteredStocks(item) {
  const q = (item._searchStok || '').toLowerCase().trim()
  if (!q) return warehouseStocks.value
  return warehouseStocks.value.filter(s =>
    s.item?.name?.toLowerCase().includes(q) ||
    s.item?.part_number?.toLowerCase().includes(q) ||
    s.item?.category?.name?.toLowerCase().includes(q)
  )
}

// Pilih barang dari dropdown pencarian — auto-fill form item
function pilihBarangDariStok(item, stok) {
  item.item_id = stok.item?.id || null
  item.part_number = stok.item?.part_number || ''
  item.nama_barang = stok.item?.name || ''
  item.satuan = stok.item?.unit || ''
  item._stok = stok.qty
  item._searchStok = stok.item?.name || ''
  item._showDropdown = false
  item.is_new_item = false
}

async function savePM() {
  if (!form.value.type) return toast.error('Pilih tipe permintaan')
  if (!form.value.warehouse_id) return toast.error('Pilih gudang terlebih dahulu')
  for (const i of form.value.items) {
    if (!i.nama_barang || !i.qty || !i.satuan) return toast.error('Lengkapi semua field barang yang wajib diisi')
    if (i.is_new_item) {
      if (!i.new_part_number) return toast.error(`Part Number wajib diisi untuk barang baru: "${i.nama_barang}"`)
      if (!i.new_category_id) return toast.error(`Kategori wajib dipilih untuk barang baru: "${i.nama_barang}"`)
    }
  }
  saving.value = true
  try {
    await axios.post('/permintaan-material', form.value)
    toast.success('Permintaan material berhasil dibuat')
    Modal.getInstance('#modalCreatePM')?.hide()
    loadData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan')
  } finally { saving.value = false }
}

async function doSubmit(pm) {
  const msg = pm.type === 'part' ? `Submit MR Part ${pm.nomor} ke Chief Mekanik?` : `Submit MR Office ${pm.nomor} ke Admin HO?`
  if (!confirm(msg)) return
  try {
    await axios.post(`/permintaan-material/${pm.id}/submit`)
    toast.success('Permintaan berhasil disubmit')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal submit') }
}

async function doAuthorizeChief(pm) {
  if (!confirm(`Otorisasi MR ${pm.nomor} sebagai Chief Mekanik?`)) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.id}/authorize-chief`)
    toast.success('MR diotorisasi, diteruskan ke Manager')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doApproveManager(pm) {
  if (!confirm(`Setujui MR ${pm.nomor} sebagai Manager?`)) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.id}/approve-manager`)
    toast.success('MR disetujui Manager, diteruskan ke Admin HO')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doApproveHO(pm) {
  if (!confirm(`Setujui MR ${pm.nomor} sebagai Admin HO?`)) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.id}/approve-ho`)
    toast.success('MR disetujui Admin HO')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

function openReject(pm) {
  selectedPM.value = pm
  rejectReason.value = ''
  new Modal('#modalRejectPM').show()
}

async function doReject() {
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${selectedPM.value.id}/reject`, { reason: rejectReason.value })
    toast.success('Permintaan ditolak')
    Modal.getInstance('#modalRejectPM')?.hide()
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doDelete(pm) {
  if (!confirm(`Hapus permintaan ${pm.nomor}?`)) return
  try {
    await axios.delete(`/permintaan-material/${pm.id}`)
    toast.success('Permintaan dihapus')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menghapus') }
}

// ── Print helpers ────────────────────────────────────────
function fmtRpPM(val) { return 'Rp ' + Number(val || 0).toLocaleString('id-ID') }
function fmtDatePM(val) {
  if (!val) return '-'
  return new Date(val).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

function buildPMHtml(pm) {
  const isPart = pm.type === 'part'
  const statusMap = {
    draft: 'DRAFT', pending_chief: 'MENUNGGU CHIEF MEKANIK',
    pending_manager: 'MENUNGGU MANAGER', pending_ho: 'MENUNGGU ADMIN HO',
    manager_approved: 'DISETUJUI MANAGER', approved: 'DISETUJUI HO',
    pending_purchasing: 'MENUNGGU PENGAJUAN PO',
    purchasing: 'PROSES PURCHASING', partial_ordered: 'SEBAGIAN PO', completed: 'SELESAI', rejected: 'DITOLAK',
  }
  const statusColor = {
    draft: '#6c757d', pending_chief: '#f59e0b', pending_manager: '#f59e0b',
    pending_ho: '#0ea5e9', manager_approved: '#3b82f6', approved: '#3b82f6',
    pending_purchasing: '#f59e0b',
    purchasing: '#0ea5e9', partial_ordered: '#f59e0b', completed: '#16a34a', rejected: '#dc2626',
  }
  const sBg   = statusColor[pm.status] || '#6c757d'
  const sTxt  = statusMap[pm.status] || (pm.status || '').toUpperCase()

  const partHeaders = isPart
    ? `<th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Part Number</th>
       <th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Kode Unit</th>
       <th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Tipe Unit</th>` : ''

  const itemRows = (pm.items || []).map((item, i) => {
    const partCells = isPart
      ? `<td style="font-family:Courier New,monospace;color:#1a3a5c;font-weight:600;text-align:center;border:1px solid #dee2e6;">${item.part_number || item.item?.part_number || '-'}</td>
         <td style="text-align:center;border:1px solid #dee2e6;">${item.kode_unit || '-'}</td>
         <td style="text-align:center;border:1px solid #dee2e6;">${item.tipe_unit || '-'}</td>` : ''
    return `<tr style="${i % 2 === 1 ? 'background:#f8fafc;' : ''}">
      <td style="text-align:center;border:1px solid #dee2e6;">${i + 1}</td>
      ${partCells}
      <td style="font-weight:600;border:1px solid #dee2e6;">${item.nama_barang || '-'}</td>
      <td style="text-align:center;border:1px solid #dee2e6;">${item.qty}</td>
      <td style="text-align:center;border:1px solid #dee2e6;">${item.satuan}</td>
      <td style="border:1px solid #dee2e6;color:#64748b;font-size:9.5pt;">${item.keterangan || '-'}</td>
    </tr>`
  }).join('')

  return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<title>Permintaan Material — ${pm.nomor}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 11px; color: #1a1a2e; background: #fff; }
  .page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm 16mm; }

  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 3px solid #1a3a5c; }
  .company-name { font-size: 20px; font-weight: 800; color: #1a3a5c; letter-spacing: -0.5px; }
  .company-sub  { font-size: 10px; color: #6c757d; margin-top: 3px; font-weight: 500; }
  .doc-block    { text-align: right; }
  .doc-label    { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #6c757d; }
  .doc-number   { font-size: 20px; font-weight: 800; color: #1a3a5c; letter-spacing: -0.5px; }
  .status-pill  { display: inline-block; margin-top: 4px; padding: 3px 10px; border-radius: 20px; font-size: 9px; font-weight: 700; letter-spacing: 1px; color: #fff; background: ${sBg}; }
  .type-pill    { display: inline-block; margin-top: 4px; margin-right: 4px; padding: 3px 10px; border-radius: 20px; font-size: 9px; font-weight: 700; color: #fff; background: ${isPart ? '#2563a8' : '#0891b2'}; }

  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 18px; border: 1.5px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
  .info-section { padding: 11px 14px; }
  .info-section:first-child { border-right: 1.5px solid #e2e8f0; }
  .info-title { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; margin-bottom: 8px; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 10.5px; }
  .info-label { color: #64748b; font-weight: 500; min-width: 100px; }
  .info-value { font-weight: 600; color: #1a1a2e; text-align: right; }
  .info-value.hi { color: #1a3a5c; }

  table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
  thead th { padding: 8px; color: #fff; background: #1a3a5c; font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; border: 1px solid #1a3a5c; }
  td { padding: 7px 8px; vertical-align: middle; }

  .sign-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; margin-top: 28px; }
  .sign-box { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
  .sign-label { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 40px; }
  .sign-line { border-top: 1.5px solid #cbd5e1; padding-top: 6px; font-size: 10px; font-weight: 600; color: #475569; min-height: 22px; }

  .notes-box { margin-top: 16px; padding: 9px 12px; background: #f8fafc; border-left: 3px solid #1a3a5c; border-radius: 0 6px 6px 0; font-size: 9.5px; color: #64748b; }
  @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div>
      <div class="company-name">PT. Cipta Sarana Makmur</div>
      <div class="company-sub">CSM Inventory Management System</div>
    </div>
    <div class="doc-block">
      <div class="doc-label">${isPart ? 'Material Request Part' : 'Material Request Office'}</div>
      <div class="doc-number">${pm.nomor}</div>
      <div>
        <span class="type-pill">${isPart ? '🔧 MR Part' : '🏢 MR Office'}</span>
        <span class="status-pill">${sTxt}</span>
      </div>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-section">
      <div class="info-title">Informasi Permintaan</div>
      <div class="info-row"><span class="info-label">No. PM</span><span class="info-value hi">${pm.nomor}</span></div>
      <div class="info-row"><span class="info-label">Gudang / Site</span><span class="info-value hi">${pm.warehouse?.name || '-'}</span></div>
      <div class="info-row"><span class="info-label">Diajukan Oleh</span><span class="info-value">${pm.requester?.name || '-'}</span></div>
      <div class="info-row"><span class="info-label">Tanggal Dibuat</span><span class="info-value">${fmtDatePM(pm.created_at)}</span></div>
    </div>
    <div class="info-section">
      <div class="info-title">Persetujuan</div>
      <div class="info-row"><span class="info-label">Tgl. Dibutuhkan</span><span class="info-value">${pm.needed_date ? fmtDatePM(pm.needed_date) : '-'}</span></div>
      <div class="info-row"><span class="info-label">Chief Mekanik</span><span class="info-value">${pm.chiefAuthorizer?.name || (pm.chief_authorized_at ? '✓' : '-')}</span></div>
      <div class="info-row"><span class="info-label">Manager</span><span class="info-value">${pm.managerApprover?.name || (pm.manager_approved_at ? '✓' : '-')}</span></div>
      <div class="info-row"><span class="info-label">Admin HO</span><span class="info-value">${pm.approver?.name || (pm.approved_at ? '✓' : '-')}</span></div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:30px;text-align:center;">#</th>
        ${partHeaders}
        <th>Nama Barang / Deskripsi</th>
        <th style="width:45px;text-align:center;">Qty</th>
        <th style="width:50px;text-align:center;">Satuan</th>
        <th style="width:120px;">Keterangan</th>
      </tr>
    </thead>
    <tbody>${itemRows}</tbody>
  </table>

  ${pm.notes ? `<div class="notes-box"><strong>Catatan:</strong> ${pm.notes}</div>` : ''}

  <div class="sign-grid">
    <div class="sign-box">
      <div class="sign-label">Ordered by Logistic</div>
      <div class="sign-line"></div>
    </div>
    <div class="sign-box">
      <div class="sign-label">Received by Purchasing</div>
      <div class="sign-line"></div>
    </div>
    <div class="sign-box">
      <div class="sign-label">Authorized by</div>
      <div class="sign-line">${pm.chiefAuthorizer?.name || ''}</div>
    </div>
    <div class="sign-box">
      <div class="sign-label">Approved by</div>
      <div class="sign-line">${pm.approver?.name || pm.managerApprover?.name || ''}</div>
    </div>
  </div>

</div>
</body>
</html>`
}

async function printPMDirect(pm) {
  try {
    const res = await axios.get(`/permintaan-material/${pm.id}`)
    const data = res.data.data
    const html = buildPMHtml(data)
    const win  = window.open('', '_blank', 'width=900,height=700')
    win.document.write(html)
    win.document.close()
    win.onload = () => { win.focus(); win.print() }
  } catch { toast.error('Gagal memuat data PM') }
}

</script>