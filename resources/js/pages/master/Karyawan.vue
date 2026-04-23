<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Data Karyawan</h5>
      <button v-if="can('manage-employees')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Tambah Karyawan
      </button>
    </div>
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-4"><input v-model="search" class="form-control form-control-sm" placeholder="🔍 Cari nama karyawan..." @input="debouncedLoad" /></div>
          <div class="col-md-3">
            <select v-model="whFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Site</option>
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
            <thead><tr><th>#</th><th>ID Karyawan</th><th>Nama</th><th>Jabatan</th><th>Site</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              <tr v-if="!employees.length"><td colspan="7" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="(e,i) in employees" :key="e.id">
                <td class="text-muted">{{ i+1 }}</td>
                <td><code class="small">{{ e.employee_id }}</code></td>
                <td class="fw-semibold">{{ e.name }}</td>
                <td class="text-muted small">{{ e.position }}</td>
                <td><small>{{ e.warehouse?.name || '-' }}</small></td>
                <td><span :class="e.is_active ? 'badge bg-success' : 'badge bg-secondary'">{{ e.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td>
                  <button v-if="can('manage-employees')" class="btn btn-xs btn-outline-primary" @click="openModal(e)"><i class="bi bi-pencil"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="modal fade" id="empModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.id ? 'Edit Karyawan' : 'Tambah Karyawan' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">ID Karyawan <span class="text-danger">*</span></label>
                <input v-model="form.employee_id" class="form-control form-control-sm" :disabled="!!form.id" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                <input v-model="form.name" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jabatan <span class="text-danger">*</span></label>
                <input v-model="form.position" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Site / Gudang</label>
                <select v-model="form.warehouse_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Site --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-12" v-if="form.id">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" v-model="form.is_active" id="empActive">
                  <label class="form-check-label small" for="empActive">Karyawan Aktif</label>
                </div>
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

const auth = useAuthStore(); const toast = useToast()
const { listenMaster, stopMaster } = useRealtime(); const can = (p) => auth.hasPermission(p)
const employees = ref([]); const warehouses = ref([]); const loading = ref(false); const saving = ref(false)
const search = ref(''); const whFilter = ref('')
const form = ref({ id: null, employee_id: '', name: '', position: '', warehouse_id: '', is_active: true })
let modal = null; let timer = null; let suppressNextToast = false

onMounted(async () => {
  modal = new Modal(document.getElementById('empModal'))
  const r = await axios.get('/warehouses'); warehouses.value = r.data.data
  load()
  const actionLabels = {"created": "Karyawan baru ditambahkan", "updated": "Data karyawan diperbarui", "deleted": "Karyawan dihapus"}
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
  try { const r = await axios.get('/employees', { params: { search: search.value, warehouse_id: whFilter.value } }); employees.value = r.data.data }
  finally { loading.value = false }
}
function debouncedLoad() { clearTimeout(timer); timer = setTimeout(load, 400) }
function openModal(e = null) {
  form.value = e ? { id: e.id, employee_id: e.employee_id, name: e.name, position: e.position, warehouse_id: e.warehouse_id||'', is_active: e.is_active } : { id: null, employee_id: '', name: '', position: '', warehouse_id: '', is_active: true }
  modal.show()
}
async function save() {
  saving.value = true
  try {
    if (form.value.id) { await axios.put(`/employees/${form.value.id}`, form.value); toast.success('Karyawan diperbarui') }
    else { await axios.post('/employees', form.value); toast.success('Karyawan ditambahkan') }
    suppressNextToast = true
    modal.hide(); load()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
</script>