<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Unit Alat Berat</h5>
        <small class="text-muted">Kelola data unit/alat berat operasional</small>
      </div>
      <button v-if="can('manage-units')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Tambah Unit
      </button>
    </div>

    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-4">
            <input v-model="search" class="form-control form-control-sm" placeholder="🔍 Cari kode unit..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="whFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Gudang/Site</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead><tr><th>Kode Unit</th><th>Tipe</th><th>Brand</th><th>HM/KM</th><th>Site/Lokasi</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              <tr v-if="!units.length"><td colspan="7" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="u in units" :key="u.id">
                <td class="fw-bold text-primary">{{ u.unit_code }}</td>
                <td>{{ u.type_unit }}</td>
                <td class="text-muted small">{{ u.brand || '-' }}</td>
                <td>{{ $formatNumber(u.hm_current) }}</td>
                <td><small>{{ u.warehouse?.name || '-' }}</small></td>
                <td>
                  <span :class="{
                    'badge bg-success': u.status==='active',
                    'badge bg-warning text-dark': u.status==='standby',
                    'badge bg-danger': u.status==='maintenance',
                    'badge bg-secondary': u.status==='retired'
                  }">{{ u.status }}</span>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-info" title="Riwayat Part" @click="openHistory(u)">
                      <i class="bi bi-clock-history"></i>
                    </button>
                    <button v-if="can('manage-units')" class="btn btn-xs btn-outline-primary" @click="openModal(u)"><i class="bi bi-pencil"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="modal fade" id="unitModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.id ? 'Edit Unit' : 'Tambah Unit' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Kode Unit <span class="text-danger">*</span></label>
                <input v-model="form.unit_code" class="form-control form-control-sm" :disabled="!!form.id" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tipe Unit <span class="text-danger">*</span></label>
                <input v-model="form.type_unit" class="form-control form-control-sm" placeholder="ZX350, PC200, dll" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Brand</label>
                <input v-model="form.brand" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">HM/KM Saat Ini</label>
                <input v-model.number="form.hm_current" type="number" min="0" step="0.1" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Site / Gudang</label>
                <select v-model="form.warehouse_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Site --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Status</label>
                <select v-model="form.status" class="form-select form-select-sm">
                  <option value="active">Active</option>
                  <option value="standby">Standby</option>
                  <option value="maintenance">Maintenance</option>
                  <option value="retired">Retired</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="save" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>{{ form.id ? 'Perbarui' : 'Simpan' }}
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Riwayat Part per Unit -->
    <div class="modal fade" id="unitHistoryModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content" v-if="selectedUnit">
          <div class="modal-header">
            <div>
              <h6 class="modal-title mb-0">
                <i class="bi bi-clock-history text-info me-2"></i>
                Riwayat Pemakaian Part — <span class="text-primary fw-bold">{{ selectedUnit.unit_code }}</span>
              </h6>
              <small class="text-muted">{{ selectedUnit.type_unit }} · {{ selectedUnit.brand }}</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Filter Kalender -->
            <div class="row g-2 mb-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Dari Tanggal</label>
                <input v-model="historyFilter.date_from" type="date" class="form-control form-control-sm" @change="loadHistory" />
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Sampai Tanggal</label>
                <input v-model="historyFilter.date_to" type="date" class="form-control form-control-sm" @change="loadHistory" />
              </div>
              <div class="col-md-3">
                <button class="btn btn-outline-secondary btn-sm" @click="resetHistoryFilter">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                </button>
              </div>
              <div class="col-md-3 text-end">
                <span class="badge bg-info text-dark">Total: {{ historyMeta.total }} bon pengeluaran</span>
              </div>
            </div>

            <!-- Loading -->
            <div v-if="historyLoading" class="text-center py-4"><div class="csm-spinner"></div></div>

            <!-- Tabel -->
            <div v-else>
              <div v-if="!historyList.length" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-3 d-block mb-2 text-muted"></i>
                Tidak ada riwayat pemakaian part untuk unit ini
              </div>
              <div v-else>
                <div v-for="bon in historyList" :key="bon.id" class="card border mb-3">
                  <!-- Header Bon -->
                  <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center"
                    style="background:#f8f9fa;">
                    <div class="d-flex align-items-center gap-3">
                      <span class="fw-bold text-success small">{{ bon.bon_number }}</span>
                      <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i>{{ $formatDate(bon.issue_date) }}</span>
                      <span class="text-muted small"><i class="bi bi-building me-1"></i>{{ bon.warehouse?.name }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                      <span class="text-muted small" v-if="bon.received_by">
                        <i class="bi bi-person me-1"></i>{{ bon.received_by }}
                      </span>
                      <span class="badge bg-secondary rounded-pill small">{{ bon.items?.length }} item</span>
                    </div>
                  </div>
                  <!-- List Part -->
                  <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                      <thead class="table-light">
                        <tr>
                          <th class="ps-3" style="width:5%">#</th>
                          <th style="width:40%">Nama Barang</th>
                          <th style="width:20%">Part Number</th>
                          <th class="text-end" style="width:15%">Qty</th>
                          <th style="width:10%">Satuan</th>
                          <th style="width:10%">Keterangan</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(item, idx) in bon.items" :key="item.id">
                          <td class="ps-3 text-muted small">{{ idx + 1 }}</td>
                          <td class="fw-semibold small">{{ item.nama_barang }}</td>
                          <td><code class="small text-primary">{{ item.item?.part_number || '-' }}</code></td>
                          <td class="text-end fw-bold small">{{ item.qty }}</td>
                          <td class="text-muted small">{{ item.satuan }}</td>
                          <td><small class="text-muted">{{ item.keterangan || '-' }}</small></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-2" v-if="historyMeta.last_page > 1">
                  <small class="text-muted">Halaman {{ historyMeta.page }} dari {{ historyMeta.last_page }}</small>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" :disabled="historyMeta.page <= 1" @click="changeHistoryPage(historyMeta.page - 1)">‹ Prev</button>
                    <button class="btn btn-outline-secondary" :disabled="historyMeta.page >= historyMeta.last_page" @click="changeHistoryPage(historyMeta.page + 1)">Next ›</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'

const auth = useAuthStore(); const toast = useToast()
const { listenMaster, stopMaster } = useRealtime()
const can = (p) => auth.hasPermission(p)
const units = ref([]); const warehouses = ref([]); const loading = ref(false); const saving = ref(false)
const search = ref(''); const whFilter = ref('')
const form = ref({ id: null, unit_code: '', type_unit: '', brand: '', hm_current: 0, warehouse_id: '', status: 'active' })
let modal = null; let timer = null; let suppressNextToast = false

// Riwayat Part per Unit
const selectedUnit = ref(null)
const historyList = ref([])
const historyLoading = ref(false)
const historyMeta = ref({ total: 0, page: 1, last_page: 1 })
const historyFilter = ref({ date_from: '', date_to: '' })
let historyModal = null

async function openHistory(u) {
  selectedUnit.value = u
  historyFilter.value = { date_from: '', date_to: '' }
  historyMeta.value.page = 1
  if (!historyModal) historyModal = new Modal(document.getElementById('unitHistoryModal'))
  historyModal.show()
  await loadHistory()
}

async function loadHistory() {
  if (!selectedUnit.value) return
  historyLoading.value = true
  try {
    const res = await axios.get(`/units/${selectedUnit.value.id}/parts-history`, {
      params: { ...historyFilter.value, page: historyMeta.value.page, per_page: 10 }
    })
    historyList.value = res.data.data
    historyMeta.value = res.data.meta
  } catch { toast.error('Gagal memuat riwayat part') }
  finally { historyLoading.value = false }
}

function resetHistoryFilter() {
  historyFilter.value = { date_from: '', date_to: '' }
  historyMeta.value.page = 1
  loadHistory()
}

function changeHistoryPage(p) {
  historyMeta.value.page = p
  loadHistory()
}

onMounted(async () => {
  modal = new Modal(document.getElementById('unitModal'))
  const r = await axios.get('/warehouses')
  warehouses.value = r.data.data
  load()
  const actionLabels = {"created": "Unit baru ditambahkan", "updated": "Data unit diperbarui", "deleted": "Unit dihapus"}
  listenMaster((e) => {
    if (!suppressNextToast) {
      toast.info(`🔔 ${actionLabels[e.action] || "Data master diperbarui"}`, { timeout: 3500 })
    }
    suppressNextToast = false
    load()
  })
})

onUnmounted(() => stopMaster())
async function load() {
  loading.value = true
  try {
    const r = await axios.get('/units', { params: { search: search.value, warehouse_id: whFilter.value } })
    units.value = r.data.data
  } finally { loading.value = false }
}
function debouncedLoad() { clearTimeout(timer); timer = setTimeout(load, 400) }
function openModal(u = null) {
  form.value = u ? { id: u.id, unit_code: u.unit_code, type_unit: u.type_unit, brand: u.brand||'', hm_current: parseFloat(u.hm_current)||0, warehouse_id: u.warehouse_id||'', status: u.status } : { id: null, unit_code: '', type_unit: '', brand: '', hm_current: 0, warehouse_id: '', status: 'active' }
  modal.show()
}
async function save() {
  saving.value = true
  try {
    if (form.value.id) { await axios.put(`/units/${form.value.id}`, form.value); toast.success('Unit diperbarui') }
    else { await axios.post('/units', form.value); toast.success('Unit ditambahkan') }
    suppressNextToast = true
    modal.hide(); load()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
</script>