<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">APD Karyawan</h5>
        <small class="text-muted">Distribusi Alat Pelindung Diri per karyawan</small>
      </div>
      <button v-if="can('manage-apd')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Catat Distribusi APD
      </button>
    </div>

    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <select v-model="filters.warehouse_id" class="form-select form-select-sm" @change="load">
              <option value="">Semua Site</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.month" type="month" class="form-control form-control-sm" @change="load" />
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
                <th>Tanggal</th>
                <th>Karyawan</th>
                <th>Jabatan</th>
                <th>APD / Barang</th>
                <th>Merek</th>
                <th>Ukuran</th>
                <th class="text-end">Qty</th>
                <th>Site</th>
                <th>Diserahkan oleh</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!apdList.length"><td colspan="9" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="a in apdList" :key="a.id">
                <td><small>{{ $formatDate(a.distribution_date) }}</small></td>
                <td class="fw-semibold small">{{ a.employee?.name }}</td>
                <td><small class="text-muted">{{ a.employee?.position }}</small></td>
                <td>
                  <div class="fw-semibold small">{{ a.item?.name }}</div>
                  <small class="text-muted">{{ a.item?.part_number }}</small>
                </td>
                <td><small>{{ a.brand || '-' }}</small></td>
                <td><small>{{ a.size || '-' }}</small></td>
                <td class="text-end fw-semibold">{{ $formatNumber(a.qty) }}</td>
                <td><small class="text-muted">{{ a.warehouse?.name }}</small></td>
                <td><small class="text-muted">{{ a.handed_by || '-' }}</small></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">{{ meta.total }} data, hal. {{ meta.page }}/{{ meta.last_page }}</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page<=1" @click="changePage(meta.page-1)">‹</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page>=meta.last_page" @click="changePage(meta.page+1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="apdModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Catat Distribusi APD</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tanggal <span class="text-danger">*</span></label>
                <input v-model="form.distribution_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Site / Gudang <span class="text-danger">*</span></label>
                <select v-model="form.warehouse_id" class="form-select form-select-sm" @change="loadEmployees">
                  <option value="">-- Pilih Site --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Karyawan <span class="text-danger">*</span></label>
                <select v-model="form.employee_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Karyawan --</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }} - {{ e.position }}</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">APD / Barang <span class="text-danger">*</span></label>
                <select v-model="form.item_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Barang --</option>
                  <option v-for="i in apdItems" :key="i.id" :value="i.id">{{ i.name }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Jumlah <span class="text-danger">*</span></label>
                <input v-model.number="form.qty" type="number" min="0.01" step="0.01" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Merek</label>
                <input v-model="form.brand" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Ukuran</label>
                <input v-model="form.size" class="form-control form-control-sm" placeholder="S/M/L/XL/No.42" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Diserahkan oleh</label>
                <input v-model="form.handed_by" class="form-control form-control-sm" />
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
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>Simpan
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
import dayjs from 'dayjs'

const auth = useAuthStore(); const toast = useToast(); const can = (p) => auth.hasPermission(p)
const { listenAPD, stopAPD } = useRealtime()
const apdList = ref([]); const warehouses = ref([]); const employees = ref([]); const apdItems = ref([])
const loading = ref(false); const saving = ref(false)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ warehouse_id: '', month: dayjs().format('YYYY-MM') })
const form = ref({ distribution_date: dayjs().format('YYYY-MM-DD'), employee_id: '', item_id: '', warehouse_id: '', qty: 1, brand: '', size: '', handed_by: '', notes: '' })
let modal = null

onMounted(async () => {
  modal = new Modal(document.getElementById('apdModal'))
  const [wRes, iRes] = await Promise.all([axios.get('/warehouses'), axios.get('/items', { params: { category_id: '' } })])
  warehouses.value = wRes.data.data
  apdItems.value = iRes.data.data.filter(i => i.category?.code === 'APD' || i.category?.name?.toLowerCase().includes('apd'))
  if (!apdItems.value.length) apdItems.value = iRes.data.data
  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) filters.value.warehouse_id = auth.userWarehouseId
  load()
  listenAPD(() => load())
})
onUnmounted(() => stopAPD())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/apd', { params: { ...filters.value, page: meta.value.page } })
    apdList.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}
function changePage(p) { meta.value.page = p; load() }

async function loadEmployees() {
  if (!form.value.warehouse_id) return
  const r = await axios.get('/employees', { params: { warehouse_id: form.value.warehouse_id } })
  employees.value = r.data.data
}

function openModal() {
  form.value = { distribution_date: dayjs().format('YYYY-MM-DD'), employee_id: '', item_id: '', warehouse_id: filters.value.warehouse_id || '', qty: 1, brand: '', size: '', handed_by: '', notes: '' }
  if (form.value.warehouse_id) loadEmployees()
  modal.show()
}

async function save() {
  saving.value = true
  try {
    await axios.post('/apd', form.value); toast.success('Distribusi APD dicatat'); modal.hide(); load()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') } finally { saving.value = false }
}
</script>