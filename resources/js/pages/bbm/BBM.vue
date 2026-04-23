<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Pengeluaran Solar / BBM</h5>
        <small class="text-muted">Catatan penggunaan bahan bakar per unit alat berat</small>
      </div>
      <button v-if="can('manage-fuel')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Catat BBM
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small mb-1">Site / Gudang</label>
            <select v-model="filters.warehouse_id" class="form-select form-select-sm" @change="load">
              <option value="">Semua Site</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Bulan</label>
            <input v-model="filters.month" type="month" class="form-control form-control-sm" @change="load" />
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Kode Unit</label>
            <input v-model="filters.unit_code" class="form-control form-control-sm" placeholder="CSM 00..." @input="debouncedLoad" />
          </div>
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-3" v-if="summary">
      <div class="col-6 col-md-3">
        <div class="csm-card text-center py-3">
          <div class="text-primary fw-bold" style="font-size:1.5rem;">{{ $formatNumber(summary.total_out || 0) }}</div>
          <small class="text-muted">Total Keluar (Liter)</small>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="csm-card text-center py-3">
          <div class="text-success fw-bold" style="font-size:1.5rem;">{{ $formatNumber(summary.total_in || 0) }}</div>
          <small class="text-muted">Total Masuk (Liter)</small>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="csm-card text-center py-3">
          <div class="fw-bold" style="font-size:1.5rem;">{{ $formatNumber(summary.stock_end || 0) }}</div>
          <small class="text-muted">Stok Akhir</small>
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
                <th>Tanggal</th>
                <th>Site</th>
                <th>Unit</th>
                <th>Tipe</th>
                <th class="text-end">HM/KM</th>
                <th>Jam Isi</th>
                <th class="text-end">Masuk</th>
                <th class="text-end">Keluar</th>
                <th class="text-end">Stok Akhir</th>
                <th>Operator</th>
                <th v-if="can('manage-fuel')">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!logs.length"><td colspan="11" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="log in logs" :key="log.id">
                <td><small>{{ $formatDate(log.log_date) }}</small></td>
                <td><small class="text-muted">{{ log.warehouse?.name }}</small></td>
                <td><span class="fw-semibold text-primary small">{{ log.unit_code || '-' }}</span></td>
                <td><small>{{ log.unit_type || '-' }}</small></td>
                <td class="text-end"><small>{{ log.hm_km ? $formatNumber(log.hm_km) : '-' }}</small></td>
                <td><small>{{ log.fill_time || '-' }}</small></td>
                <td class="text-end text-success fw-semibold">{{ log.stock_in > 0 ? '+'+$formatNumber(log.stock_in) : '-' }}</td>
                <td class="text-end text-danger fw-semibold">{{ $formatNumber(log.liter_out) }}</td>
                <td class="text-end">
                  <span :class="parseFloat(log.stock_after) < 0 ? 'stock-minus' : 'fw-semibold'">{{ $formatNumber(log.stock_after) }}</span>
                </td>
                <td><small class="text-muted">{{ log.operator_name || '-' }}</small></td>
                <td v-if="can('manage-fuel')">
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-primary" @click="openModal(log)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-xs btn-outline-danger" @click="deleteLog(log)"><i class="bi bi-trash"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">{{ meta.total }} data</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="bbmModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.id ? 'Edit Log BBM' : 'Catat Pengeluaran BBM' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tanggal <span class="text-danger">*</span></label>
                <input v-model="form.log_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Site / Gudang <span class="text-danger">*</span></label>
                <select v-model="form.warehouse_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Site --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Kode Unit</label>
                <input v-model="form.unit_code" class="form-control form-control-sm" placeholder="CSM 0038" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tipe Unit</label>
                <input v-model="form.unit_type" class="form-control form-control-sm" placeholder="ZX350" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Divisi</label>
                <input v-model="form.division" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">HM/KM</label>
                <input v-model.number="form.hm_km" type="number" step="0.1" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jam Isi</label>
                <input v-model="form.fill_time" type="time" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Operator</label>
                <input v-model="form.operator_name" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Stok Masuk (Liter)</label>
                <input v-model.number="form.stock_in" type="number" min="0" step="0.01" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Liter Keluar <span class="text-danger">*</span></label>
                <input v-model.number="form.liter_out" type="number" min="0" step="0.01" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="form.notes" class="form-control form-control-sm" rows="2"></textarea>
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
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import Swal from 'sweetalert2'
import dayjs from 'dayjs'

const auth = useAuthStore(); const toast = useToast(); const can = (p) => auth.hasPermission(p)
const { listenFuel, stopFuel } = useRealtime()
const logs = ref([]); const warehouses = ref([]); const loading = ref(false); const saving = ref(false)
const summary = ref(null)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ warehouse_id: '', month: dayjs().format('YYYY-MM'), unit_code: '' })
const form = ref({ id: null, log_date: dayjs().format('YYYY-MM-DD'), warehouse_id: '', unit_code: '', unit_type: '', division: '', hm_km: null, fill_time: '', liter_out: 0, stock_in: 0, operator_name: '', notes: '' })
let modal = null; let timer = null

onMounted(async () => {
  modal = new Modal(document.getElementById('bbmModal'))
  const r = await axios.get('/warehouses'); warehouses.value = r.data.data
  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) { filters.value.warehouse_id = auth.userWarehouseId }
  load()
  listenFuel(() => load())
})
onUnmounted(() => stopFuel())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/fuel-logs', { params: { ...filters.value, page: meta.value.page } })
    logs.value = r.data.data; meta.value = r.data.meta; summary.value = r.data.summary
  } finally { loading.value = false }
}
function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page=1; load() }, 400) }
function changePage(p) { meta.value.page = p; load() }

function openModal(log = null) {
  if (log) {
    form.value = { id: log.id, log_date: log.log_date, warehouse_id: log.warehouse_id, unit_code: log.unit_code||'', unit_type: log.unit_type||'', division: log.division||'', hm_km: log.hm_km||null, fill_time: log.fill_time||'', liter_out: parseFloat(log.liter_out)||0, stock_in: parseFloat(log.stock_in)||0, operator_name: log.operator_name||'', notes: log.notes||'' }
  } else {
    form.value = { id: null, log_date: dayjs().format('YYYY-MM-DD'), warehouse_id: filters.value.warehouse_id || '', unit_code: '', unit_type: '', division: '', hm_km: null, fill_time: '', liter_out: 0, stock_in: 0, operator_name: '', notes: '' }
  }
  modal.show()
}

async function save() {
  saving.value = true
  try {
    if (form.value.id) { await axios.put(`/fuel-logs/${form.value.id}`, form.value); toast.success('Log BBM diperbarui') }
    else { await axios.post('/fuel-logs', form.value); toast.success('Log BBM dicatat') }
    modal.hide(); load()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') } finally { saving.value = false }
}

async function deleteLog(log) {
  const r = await Swal.fire({ title: 'Hapus Log BBM?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#e74c3c', confirmButtonText: 'Hapus' })
  if (!r.isConfirmed) return
  try { await axios.delete(`/fuel-logs/${log.id}`); toast.success('Dihapus'); load() } catch(e) { toast.error('Gagal menghapus') }
}
</script>