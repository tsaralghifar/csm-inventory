<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Tanda Terima Pembelian</h5>
        <small class="text-muted">Penerimaan barang dari vendor — mendukung pengiriman sebagian (partial delivery)</small>
      </div>
      <button v-if="can('receive-sj')" class="btn btn-primary btn-sm" @click="openFormTTB">
        <i class="bi bi-plus-circle me-1"></i> Terima Barang Baru
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. TTP / PO..." @input="debouncedLoad" />
          </div>
          <div class="col-md-2">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="received">Diterima</option>
              <option value="draft">Draft</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-3">
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
                <th>No. TTP</th>
                <th>No. PO</th>
                <th>Vendor</th>
                <th>Gudang</th>
                <th>Item</th>
                <th>Status PO</th>
                <th>Driver</th>
                <th>Penerima</th>
                <th>Tgl. Terima</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="10" class="text-center text-muted py-5">Belum ada Tanda Terima Pembelian</td>
              </tr>
              <tr v-for="sj in list" :key="sj.id">
                <td class="fw-semibold" style="color:#1a3a5c;">{{ sj.sj_number }}</td>
                <td><small class="text-muted">{{ sj.purchase_order?.po_number || '-' }}</small></td>
                <td><small>{{ sj.vendor_name || '-' }}</small></td>
                <td><small>{{ sj.warehouse?.name }}</small></td>
                <td><span class="badge bg-secondary rounded-pill">{{ sj.items_count }} item</span></td>
                <td>
                  <span class="badge" :class="deliveryStatusClass(sj.purchase_order?.delivery_status)">
                    {{ deliveryStatusLabel(sj.purchase_order?.delivery_status) }}
                  </span>
                </td>
                <td><small>{{ sj.driver_name || '-' }}</small></td>
                <td>
                  <small v-if="sj.received_by_name" class="fw-semibold text-dark">
                    <i class="bi bi-person-check text-success me-1"></i>{{ sj.received_by_name }}
                  </small>
                  <small v-else class="text-muted">-</small>
                </td>
                <td><small class="text-muted">{{ sj.received_date ? $formatDate(sj.received_date) : '-' }}</small></td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="Detail" @click="openDetail(sj)">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button v-if="sj.status === 'received'" class="btn btn-outline-danger" title="Print PDF" @click="printSJDirect(sj)">
                      <i class="bi bi-printer"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">Total {{ meta.total }} tanda terima pembelian</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ Modal: Form Terima Barang Baru (Partial Delivery) ══════════════════ -->
    <div class="modal fade" id="modalFormTTB" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header bg-primary bg-opacity-10">
            <h6 class="modal-title text-primary">
              <i class="bi bi-box-arrow-in-down me-2"></i>Terima Barang dari PO
              <span v-if="selectedPO" class="badge bg-primary ms-2">{{ selectedPO.po_number }}</span>
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetFormTTB"></button>
          </div>
          <div class="modal-body">

            <!-- Step 1: Pilih PO -->
            <div v-if="!selectedPO" class="mb-3">
              <label class="form-label fw-semibold small">Pilih Purchase Order <span class="text-danger">*</span></label>
              <div class="input-group">
                <input
                  v-model="poSearch"
                  class="form-control"
                  placeholder="Ketik nomor PO atau nama vendor..."
                  @input="debouncedSearchPO"
                />
                <span class="input-group-text"><i class="bi bi-search"></i></span>
              </div>
              <div v-if="poList.length" class="border rounded mt-1" style="max-height:220px;overflow-y:auto;">
                <div
                  v-for="po in poList"
                  :key="po.id"
                  class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center"
                  style="cursor:pointer;"
                  :class="{'bg-light': po.delivery_status === 'completed'}"
                  @click="po.delivery_status !== 'completed' && selectPO(po)"
                >
                  <div>
                    <div class="fw-semibold small" style="color:#1a3a5c;">{{ po.po_number }}</div>
                    <div class="text-muted" style="font-size:0.75rem;">{{ po.vendor_name }} · {{ po.warehouse?.name }}</div>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge" :class="deliveryStatusClass(po.delivery_status)">
                      {{ deliveryStatusLabel(po.delivery_status) }}
                    </span>
                    <span v-if="po.delivery_status !== 'completed'" class="badge bg-outline-primary text-primary border border-primary" style="font-size:0.7rem;">
                      Pilih →
                    </span>
                    <span v-else class="text-muted small">Sudah lengkap</span>
                  </div>
                </div>
              </div>
              <div v-if="poSearching" class="text-center py-2 text-muted small"><div class="csm-spinner sm me-1"></div> Mencari PO...</div>
              <div class="form-text">Hanya PO dengan status "Dikirim ke Vendor" atau "Sebagian Diterima" yang dapat dipilih.</div>
            </div>

            <!-- Step 2: Form penerimaan setelah PO dipilih -->
            <div v-if="selectedPO">
              <!-- Info PO -->
              <div class="alert alert-info py-2 small mb-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <i class="bi bi-info-circle me-1"></i>
                    PO <strong>{{ selectedPO.po_number }}</strong> · Vendor: <strong>{{ selectedPO.vendor_name }}</strong>
                    · Gudang: <strong>{{ selectedPO.warehouse?.name }}</strong>
                  </div>
                  <button class="btn btn-sm btn-outline-secondary" @click="selectedPO = null; remainingItems = []">
                    Ganti PO
                  </button>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Tgl. Penerimaan <span class="text-danger">*</span></label>
                  <input v-model="form.received_date" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Nama Driver</label>
                  <input v-model="form.driver_name" type="text" class="form-control form-control-sm" placeholder="Nama driver..." />
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">No. Kendaraan</label>
                  <input v-model="form.vehicle_plate" type="text" class="form-control form-control-sm" placeholder="e.g. DA 1234 AB" />
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold small">Catatan</label>
                  <input v-model="form.notes" type="text" class="form-control form-control-sm" placeholder="Catatan penerimaan (opsional)..." />
                </div>
              </div>

              <!-- Tabel item partial delivery -->
              <div v-if="loadingRemaining" class="text-center py-4"><div class="csm-spinner"></div></div>
              <div v-else-if="remainingItems.length">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <label class="form-label fw-semibold small mb-0">Detail Penerimaan Barang</label>
                  <div class="d-flex gap-2">
                    <button class="btn btn-xs btn-outline-success" @click="fillAllRemaining">
                      <i class="bi bi-check-all me-1"></i>Terima Semua Sisa
                    </button>
                    <button class="btn btn-xs btn-outline-secondary" @click="clearAllQty">
                      <i class="bi bi-x me-1"></i>Kosongkan
                    </button>
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-sm csm-table mb-0">
                    <thead>
                      <tr>
                        <th style="width:30px;">
                          <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="form-check-input" />
                        </th>
                        <th>Nama Barang</th>
                        <th class="text-end">Qty PO</th>
                        <th class="text-end">Sudah Diterima</th>
                        <th class="text-end">Sisa</th>
                        <th class="text-center" style="width:160px;">Qty Diterima Kini</th>
                        <th>Satuan</th>
                        <th class="text-center">Masuk Stok</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(item, idx) in remainingItems"
                        :key="item.purchase_order_item_id"
                        :class="{
                          'table-success bg-opacity-25': item.selected && item.qty_input > 0,
                          'text-muted': item.is_fully_received,
                        }"
                      >
                        <td>
                          <input
                            type="checkbox"
                            v-model="item.selected"
                            class="form-check-input"
                            :disabled="item.is_fully_received"
                          />
                        </td>
                        <td>
                          <div class="fw-semibold small">{{ item.nama_barang }}</div>
                          <div v-if="item.is_fully_received" class="badge bg-success" style="font-size:0.65rem;">✓ Sudah Lengkap</div>
                        </td>
                        <td class="text-end small text-muted">{{ item.qty_ordered }}</td>
                        <td class="text-end small">
                          <span :class="item.qty_received > 0 ? 'text-warning fw-semibold' : 'text-muted'">
                            {{ item.qty_received }}
                          </span>
                        </td>
                        <td class="text-end small">
                          <span :class="item.qty_remaining > 0 ? 'text-danger fw-semibold' : 'text-success'">
                            {{ item.qty_remaining }}
                          </span>
                        </td>
                        <td class="text-center">
                          <input
                            v-if="!item.is_fully_received"
                            v-model.number="item.qty_input"
                            type="number"
                            class="form-control form-control-sm text-center"
                            :min="0"
                            :max="item.qty_remaining"
                            :step="1"
                            :disabled="!item.selected"
                            style="width:110px; margin:0 auto;"
                            @input="validateQty(item)"
                          />
                          <span v-else class="text-success small fw-bold">✓</span>
                        </td>
                        <td class="small">{{ item.satuan }}</td>
                        <td class="text-center">
                          <div class="form-check form-switch d-flex justify-content-center mb-0">
                            <input
                              class="form-check-input"
                              type="checkbox"
                              v-model="item.masuk_stok"
                              :disabled="!item.selected || item.is_fully_received"
                            />
                          </div>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="table-light fw-semibold small">
                        <td colspan="5" class="text-end">Total qty yang akan diterima:</td>
                        <td class="text-center text-primary fw-bold">{{ totalQtyInput }}</td>
                        <td colspan="2"></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Warning partial -->
                <div v-if="isPartialInput" class="alert alert-warning py-2 small mt-2 mb-0">
                  <i class="bi bi-exclamation-triangle me-1"></i>
                  Kamu memasukkan <strong>sebagian</strong> dari sisa barang. Status PO akan menjadi
                  <strong>"Sebagian Diterima"</strong> dan bisa diterima lagi di TTB berikutnya.
                </div>
                <div v-if="isFullInput" class="alert alert-success py-2 small mt-2 mb-0">
                  <i class="bi bi-check-circle me-1"></i>
                  Semua sisa barang akan diterima. Status PO akan menjadi <strong>"Selesai"</strong>.
                </div>
              </div>
              <div v-else class="alert alert-success py-2 small">
                <i class="bi bi-check-circle me-1"></i>
                Semua barang dari PO ini sudah diterima seluruhnya.
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" @click="resetFormTTB">Batal</button>
            <button
              v-if="selectedPO && remainingItems.length && totalQtyInput > 0"
              type="button"
              class="btn btn-primary btn-sm"
              @click="submitTTB"
              :disabled="acting"
            >
              <span v-if="acting" class="csm-spinner sm me-1"></span>
              <i class="bi bi-box-arrow-in-down me-1"></i>
              Simpan Penerimaan ({{ totalQtyInput }} item)
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ Modal Detail TTP ═══════════════════════════════════════════════════ -->
    <div class="modal fade" id="modalDetailSJ" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedSJ">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-file-earmark-check me-2"></i>{{ selectedSJ.sj_number }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Info PO delivery status -->
            <div v-if="selectedSJ.purchase_order?.delivery_status" class="alert py-2 small mb-3"
              :class="selectedSJ.purchase_order.delivery_status === 'completed' ? 'alert-success' : 'alert-warning'">
              <i class="bi me-1" :class="selectedSJ.purchase_order.delivery_status === 'completed' ? 'bi-check-circle' : 'bi-clock'"></i>
              Status pengiriman PO <strong>{{ selectedSJ.purchase_order?.po_number }}</strong>:
              <strong>{{ deliveryStatusLabel(selectedSJ.purchase_order.delivery_status) }}</strong>
              <span v-if="selectedSJ.purchase_order.delivery_status === 'partial'">
                — masih ada sisa barang yang belum diterima.
              </span>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                    <tr><td class="text-muted w-40">No. TTP</td><td class="fw-semibold">{{ selectedSJ.sj_number }}</td></tr>
                    <tr><td class="text-muted">No. PO</td><td>{{ selectedSJ.purchase_order?.po_number || '-' }}</td></tr>
                    <tr><td class="text-muted">Vendor</td><td>{{ selectedSJ.vendor_name || '-' }}</td></tr>
                    <tr><td class="text-muted">Driver</td><td>{{ selectedSJ.driver_name || '-' }}</td></tr>
                    <tr><td class="text-muted">No. Kendaraan</td><td>{{ selectedSJ.vehicle_plate || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                    <tr><td class="text-muted w-40">Gudang</td><td>{{ selectedSJ.warehouse?.name }}</td></tr>
                    <tr><td class="text-muted">Tgl. Terima</td><td>{{ selectedSJ.received_date ? $formatDate(selectedSJ.received_date) : '-' }}</td></tr>
                    <tr>
                      <td class="text-muted">Penerima</td>
                      <td>
                        <span v-if="selectedSJ.received_by_name" class="fw-semibold">
                          <i class="bi bi-person-check text-success me-1"></i>{{ selectedSJ.received_by_name }}
                        </span>
                        <span v-else class="text-muted">-</span>
                      </td>
                    </tr>
                    <tr><td class="text-muted">Catatan</td><td>{{ selectedSJ.notes || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm csm-table mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Part Number</th>
                    <th>Nama Barang</th>
                    <th class="text-end">Qty PO</th>
                    <th class="text-end">Qty Diterima TTB ini</th>
                    <th>Satuan</th>
                    <th class="text-center">Masuk Stok</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in selectedSJ.items" :key="item.id">
                    <td class="text-muted">{{ idx+1 }}</td>
                    <td>
                      <code v-if="item.item?.part_number" class="small text-primary fw-semibold">{{ item.item.part_number }}</code>
                      <span v-else class="text-muted small">-</span>
                    </td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td class="text-end text-muted">{{ item.qty_ordered }}</td>
                    <td class="text-end fw-bold text-success">{{ item.qty_received }}</td>
                    <td>{{ item.satuan }}</td>
                    <td class="text-center">
                      <span class="badge" :class="item.masuk_stok ? 'bg-success' : 'bg-secondary'">
                        {{ item.masuk_stok ? '✓ Ya' : 'Tidak' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button v-if="selectedSJ.status === 'received'" type="button" class="btn btn-outline-danger btn-sm" @click="printSJ(selectedSJ)">
              <i class="bi bi-printer me-1"></i>Print PDF
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'

const toast  = useToast()
const auth   = useAuthStore()
const { listenSJ, stopSJ } = useRealtime()
const can    = (p) => auth.hasPermission(p)

// ── List state ─────────────────────────────────────────────────────────────
const list    = ref([])
const loading = ref(false)
const acting  = ref(false)
const meta    = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ search: '', status: '', date_from: '', date_to: '' })
const selectedSJ = ref(null)
let timer = null

// ── Form state ─────────────────────────────────────────────────────────────
const selectedPO      = ref(null)
const poSearch        = ref('')
const poList          = ref([])
const poSearching     = ref(false)
const remainingItems  = ref([])
const loadingRemaining = ref(false)
const selectAll       = ref(false)

const form = ref({
  received_date: new Date().toISOString().slice(0, 10),
  driver_name:   '',
  vehicle_plate: '',
  notes:         '',
})

// ── Computed ────────────────────────────────────────────────────────────────
const totalQtyInput = computed(() =>
  remainingItems.value.filter(i => i.selected && !i.is_fully_received)
    .reduce((s, i) => s + (parseFloat(i.qty_input) || 0), 0)
)

const isPartialInput = computed(() => {
  const selected = remainingItems.value.filter(i => i.selected && !i.is_fully_received)
  return totalQtyInput.value > 0 && selected.some(i => (parseFloat(i.qty_input) || 0) < i.qty_remaining)
})

const isFullInput = computed(() => {
  const selectable = remainingItems.value.filter(i => !i.is_fully_received)
  return selectable.length > 0 && selectable.every(i => i.selected && (parseFloat(i.qty_input) || 0) >= i.qty_remaining)
})

// ── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(() => { loadData(); listenSJ(() => loadData()) })
onUnmounted(() => stopSJ())

// ── List functions ──────────────────────────────────────────────────────────
async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/surat-jalan', {
      params: { ...filters.value, page: meta.value.page, per_page: 15 },
    })
    list.value = res.data.data
    meta.value = res.data.meta
  } catch {
    toast.error('Gagal memuat data')
  } finally {
    loading.value = false
    window.clearModalBackdrop?.()
  }
}

function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400) }
function changePage(p)   { meta.value.page = p; loadData() }
function resetFilters()  { filters.value = { search: '', status: '', date_from: '', date_to: '' }; meta.value.page = 1; loadData() }

// ── Detail ──────────────────────────────────────────────────────────────────
async function openDetail(sj) {
  try {
    const res = await axios.get(`/surat-jalan/${sj.id}`)
    selectedSJ.value = res.data.data
    new Modal('#modalDetailSJ').show()
  } catch { toast.error('Gagal memuat detail') }
}

// ── Form TTB ────────────────────────────────────────────────────────────────
function openFormTTB() {
  resetFormTTB()
  new Modal('#modalFormTTB').show()
}

function resetFormTTB() {
  selectedPO.value    = null
  poSearch.value      = ''
  poList.value        = []
  remainingItems.value = []
  selectAll.value     = false
  form.value = {
    received_date: new Date().toISOString().slice(0, 10),
    driver_name:   '',
    vehicle_plate: '',
    notes:         '',
  }
}

// ── PO Search ───────────────────────────────────────────────────────────────
let poTimer = null
function debouncedSearchPO() {
  clearTimeout(poTimer)
  poTimer = setTimeout(searchPO, 400)
}

async function searchPO() {
  if (!poSearch.value.trim()) { poList.value = []; return }
  poSearching.value = true
  try {
    const res = await axios.get('/purchase-orders', {
      params: {
        search: poSearch.value,
        exclude_delivery_completed: 1,
        per_page: 10,
      },
    })
    poList.value = res.data.data || []
  } catch {
    toast.error('Gagal mencari PO')
  } finally {
    poSearching.value = false
  }
}

async function selectPO(po) {
  selectedPO.value = po
  poList.value     = []
  loadingRemaining.value = true
  try {
    const res = await axios.get(`/surat-jalan/po/${po.id}/remaining`)
    remainingItems.value = res.data.data.map(item => ({
      ...item,
      qty_input:  item.is_fully_received ? 0 : item.qty_remaining,
      masuk_stok: true,
      selected:   !item.is_fully_received,
    }))
  } catch {
    toast.error('Gagal memuat sisa barang')
  } finally {
    loadingRemaining.value = false
  }
}

// ── Item helpers ────────────────────────────────────────────────────────────
function validateQty(item) {
  const val = parseFloat(item.qty_input) || 0
  if (val > item.qty_remaining) item.qty_input = item.qty_remaining
  if (val < 0) item.qty_input = 0
}

function fillAllRemaining() {
  remainingItems.value.forEach(item => {
    if (!item.is_fully_received) {
      item.selected  = true
      item.qty_input = item.qty_remaining
    }
  })
  selectAll.value = true
}

function clearAllQty() {
  remainingItems.value.forEach(item => { item.qty_input = 0; item.selected = false })
  selectAll.value = false
}

function toggleSelectAll() {
  remainingItems.value.forEach(item => {
    if (!item.is_fully_received) {
      item.selected  = selectAll.value
      item.qty_input = selectAll.value ? item.qty_remaining : 0
    }
  })
}

// ── Submit TTB ──────────────────────────────────────────────────────────────
async function submitTTB() {
  const itemsToSubmit = remainingItems.value
    .filter(i => i.selected && !i.is_fully_received && (parseFloat(i.qty_input) || 0) > 0)
    .map(i => ({
      purchase_order_item_id: i.purchase_order_item_id,
      item_id:                i.item_id,
      qty_received:           parseFloat(i.qty_input),
      masuk_stok:             i.masuk_stok,
    }))

  if (!itemsToSubmit.length) return toast.warning('Tidak ada item yang dipilih')
  if (!form.value.received_date) return toast.error('Tanggal penerimaan wajib diisi')

  acting.value = true
  try {
    await axios.post('/surat-jalan', {
      purchase_order_id: selectedPO.value.id,
      warehouse_id:      selectedPO.value.warehouse_id,
      vendor_name:       selectedPO.value.vendor_name,
      received_date:     form.value.received_date,
      driver_name:       form.value.driver_name,
      vehicle_plate:     form.value.vehicle_plate,
      notes:             form.value.notes,
      items:             itemsToSubmit,
    })

    toast.success('Tanda Terima Barang berhasil disimpan. Stok gudang telah diperbarui.')
    const modalEl = document.getElementById('modalFormTTB')
    modalEl?.addEventListener('hidden.bs.modal', () => { resetFormTTB(); loadData() }, { once: true })
    Modal.getInstance(modalEl)?.hide()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menyimpan penerimaan')
  } finally {
    acting.value = false
  }
}

// ── Status helpers ──────────────────────────────────────────────────────────
function deliveryStatusLabel(status) {
  const map = { partial: 'Sebagian Diterima', completed: 'Selesai', null: 'Belum Diterima' }
  return map[status] ?? 'Belum Diterima'
}

function deliveryStatusClass(status) {
  const map = { partial: 'bg-warning text-dark', completed: 'bg-success', null: 'bg-secondary' }
  return map[status] ?? 'bg-secondary'
}

// ── Print PDF ───────────────────────────────────────────────────────────────
async function printSJDirect(sj) {
  try {
    const res = await axios.get(`/surat-jalan/${sj.id}`)
    printSJ(res.data.data)
  } catch { toast.error('Gagal memuat data') }
}

function printSJ(sj) {
  const fmtD  = (v) => v ? new Date(v).toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' }) : '-'
  const poNum = sj.purchase_order?.po_number || '-'

  // ── Status PO ──────────────────────────────────────────────────────────────
  const delivStatus = sj.purchase_order?.delivery_status
  const poStatusMap = {
    completed : { label: '✓ Selesai — Semua barang sudah diterima',    bg: '#16a34a', color: '#fff' },
    partial   : { label: '⏳ Sebagian Diterima — Masih ada sisa barang', bg: '#f59e0b', color: '#fff' },
    null      : { label: '○ Belum Diterima',                            bg: '#e2e8f0', color: '#64748b' },
  }
  const poSt = poStatusMap[delivStatus] ?? poStatusMap[null]

  // ── Baris item tabel ───────────────────────────────────────────────────────
  const rows = (sj.items || []).map((item, i) => {
    const qtyPO       = parseFloat(item.qty_ordered  || 0)
    const qtyTerima   = parseFloat(item.qty_received || 0)
    // qty_remaining di item TTB ini saja (bukan sisa global PO)
    // Tampilkan status item: Sudah Lengkap jika qtyTerima >= qtyPO
    const isLengkap   = qtyTerima >= qtyPO
    const itemStatus  = isLengkap
      ? '<span style="background:#16a34a;color:#fff;padding:2px 8px;border-radius:12px;font-size:7.5pt;font-weight:700">✓ Lengkap</span>'
      : '<span style="background:#f59e0b;color:#fff;padding:2px 8px;border-radius:12px;font-size:7.5pt;font-weight:700">Sebagian</span>'
    const sisaQty     = Math.max(0, qtyPO - qtyTerima)
    const sisaColor   = sisaQty > 0 ? '#f59e0b' : '#16a34a'

    return `<tr style="background:${i % 2 ? '#f8fafc' : '#fff'}">
      <td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;color:#64748b">${i+1}</td>
      <td style="border:1px solid #e2e8f0;padding:6px 8px;font-family:monospace;font-weight:700;color:#1a3a5c;font-size:9pt">${item.item?.part_number || '-'}</td>
      <td style="border:1px solid #e2e8f0;padding:6px 10px;font-weight:600;color:#1f2937">${item.nama_barang || '-'}</td>
      <td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;color:#64748b">${qtyPO}</td>
      <td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;font-weight:700;color:#16a34a">${qtyTerima}</td>
      <td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;font-weight:600;color:${sisaColor}">${sisaQty}</td>
      <td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px">${item.satuan || '-'}</td>
      <td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px">${itemStatus}</td>
      <td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px">${item.masuk_stok
        ? '<span style="background:#16a34a;color:#fff;padding:2px 8px;border-radius:12px;font-size:8pt;font-weight:700">✓ Ya</span>'
        : '<span style="background:#6b7280;color:#fff;padding:2px 8px;border-radius:12px;font-size:8pt">Tidak</span>'}</td>
    </tr>`
  }).join('')

  const html = `<!DOCTYPE html><html>
  <head><meta charset="UTF-8"/><title>TTP-${sj.sj_number}</title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:Arial,sans-serif;font-size:10pt;color:#1f2937;padding:20px}
    @media print{body{padding:0}@page{margin:15mm 12mm;size:A4}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}}
    .hdr{background:#1a3a5c;color:#fff;padding:14px 20px;border-radius:8px 8px 0 0}
    .hdr2{background:#16a34a;color:#fff;padding:7px 20px;display:flex;align-items:center;gap:12px}
    .igrid{display:grid;grid-template-columns:1fr 1fr;border:1px solid #e2e8f0;border-top:none}
    .isec{padding:12px 16px}.isec:first-child{border-right:1px solid #e2e8f0}
    .ititle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #e8edf4}
    .irow{display:flex;margin-bottom:5px;font-size:9pt}
    .ilbl{color:#64748b;width:140px;flex-shrink:0}.ival{font-weight:600;color:#1a3a5c}.ival2{color:#374151}
    .po-status-bar{margin-top:10px;padding:8px 16px;border-radius:0 0 6px 6px;font-size:9pt;font-weight:700;display:flex;align-items:center;gap:10px}
    table.it{width:100%;border-collapse:collapse;margin-top:14px}
    table.it th{background:#1a3a5c;color:#fff;padding:8px 10px;font-size:9pt;font-weight:700}
    .sgrid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:24px}
    .sbox{border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 12px}
    .stitle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;text-align:center;background:#e8edf4;margin:-8px -12px 8px;padding:6px;border-radius:4px 4px 0 0}
    .sspace{height:45px;border-bottom:1.5px solid #e2e8f0;margin-bottom:6px}
    .sname{font-size:9pt;font-weight:600;color:#1a3a5c;text-align:center}
  </style></head>
  <body>
    <div class="hdr"><h1 style="font-size:15pt;font-weight:800">PT. CIPTA SARANA MAKMUR</h1></div>
    <div class="hdr2">
      <span style="font-size:11pt;font-weight:700">TANDA TERIMA PEMBELIAN</span>
      <span style="font-size:11pt;font-weight:800;background:#fff;color:#16a34a;padding:2px 12px;border-radius:4px">${sj.sj_number}</span>
      <span style="background:#fff;color:#16a34a;padding:2px 10px;border-radius:12px;font-size:8pt;font-weight:700">✓ DITERIMA</span>
    </div>

    <!-- Status PO bar -->
    <div class="po-status-bar" style="background:${poSt.bg};color:${poSt.color}">
      <span>Status PO ${poNum}:</span>
      <span>${poSt.label}</span>
    </div>

    <div class="igrid" style="border-top:none;margin-top:10px">
      <div class="isec">
        <div class="ititle">Informasi Pengiriman</div>
        <div class="irow"><span class="ilbl">No. TTP</span><span class="ival">${sj.sj_number}</span></div>
        <div class="irow"><span class="ilbl">No. PO</span><span class="ival2">${poNum}</span></div>
        <div class="irow"><span class="ilbl">Vendor</span><span class="ival">${sj.vendor_name || '-'}</span></div>
        <div class="irow"><span class="ilbl">Driver</span><span class="ival2">${sj.driver_name || '-'}</span></div>
        <div class="irow"><span class="ilbl">No. Kendaraan</span><span class="ival2">${sj.vehicle_plate || '-'}</span></div>
      </div>
      <div class="isec">
        <div class="ititle">Penerimaan</div>
        <div class="irow"><span class="ilbl">Gudang Tujuan</span><span class="ival">${sj.warehouse?.name || '-'}</span></div>
        <div class="irow"><span class="ilbl">Tgl. Terima</span><span class="ival2">${fmtD(sj.received_date)}</span></div>
        <div class="irow"><span class="ilbl">Penerima Barang</span><span class="ival" style="color:#16a34a">${sj.received_by_name || '-'}</span></div>
        <div class="irow"><span class="ilbl">Dibuat Oleh</span><span class="ival2">${sj.creator?.name || '-'}</span></div>
        ${sj.notes ? `<div class="irow"><span class="ilbl">Catatan</span><span class="ival2">${sj.notes}</span></div>` : ''}
      </div>
    </div>

    <table class="it">
      <thead><tr>
        <th style="text-align:center;width:30px">#</th>
        <th style="text-align:center;width:100px">Part Number</th>
        <th style="text-align:left">Nama Barang</th>
        <th style="text-align:center;width:60px">Qty PO</th>
        <th style="text-align:center;width:70px">Qty Terima</th>
        <th style="text-align:center;width:55px">Sisa</th>
        <th style="text-align:center;width:55px">Satuan</th>
        <th style="text-align:center;width:80px">Status Item</th>
        <th style="text-align:center;width:70px">Masuk Stok</th>
      </tr></thead>
      <tbody>${rows}</tbody>
    </table>

    <div class="sgrid">
      <div class="sbox"><div class="stitle">Dibuat Oleh</div><div class="sspace"></div><div class="sname">${sj.creator?.name || ''}</div></div>
      <div class="sbox"><div class="stitle">Penerima Barang</div><div class="sspace"></div><div class="sname">${sj.received_by_name || ''}</div></div>
      <div class="sbox"><div class="stitle">Mengetahui</div><div class="sspace"></div><div class="sname"></div></div>
    </div>
  </body></html>`

  const win = window.open('', '_blank', 'width=900,height=700')
  win.document.write(html)
  win.document.close()
  win.onload = () => { win.focus(); win.print() }
}
</script>