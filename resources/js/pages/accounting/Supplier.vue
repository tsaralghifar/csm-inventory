<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Data Supplier</h5>
        <small class="text-muted">Kelola data supplier / vendor perusahaan</small>
      </div>
      <button v-if="can('manage-accounting')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Tambah Supplier
      </button>
    </div>

    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-5">
            <input v-model="search" class="form-control form-control-sm" placeholder="🔍 Cari nama / kode supplier..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="activeFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Status</option>
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
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
            <thead>
              <tr>
                <th>Kode</th><th>Nama Supplier</th><th>Kontak</th><th>No. Telp</th>
                <th class="text-end">Hutang Outstanding</th><th>Status</th><th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!suppliers.length"><td colspan="7" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="s in suppliers" :key="s.id">
                <td><code class="small text-primary">{{ s.code }}</code></td>
                <td class="fw-semibold">{{ s.name }}</td>
                <td class="small text-muted">{{ s.contact_name || '-' }}</td>
                <td class="small">{{ s.phone || '-' }}</td>
                <td class="text-end fw-bold" :class="s.outstanding_balance > 0 ? 'text-danger' : 'text-muted'">
                  {{ $formatCurrency(s.outstanding_balance) }}
                </td>
                <td>
                  <span :class="s.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                    {{ s.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td>
                  <button v-if="can('manage-accounting')" class="btn btn-xs btn-outline-primary" @click="openModal(s)">
                    <i class="bi bi-pencil"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit Supplier -->
    <div class="modal fade" id="supplierModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.id ? 'Edit Supplier' : 'Tambah Supplier' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Kode <span class="text-danger">*</span></label>
                <input v-model="form.code" class="form-control form-control-sm" :disabled="!!form.id" />
              </div>
              <div class="col-md-8">
                <label class="form-label fw-semibold small">Nama Supplier <span class="text-danger">*</span></label>
                <input v-model="form.name" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Nama Kontak</label>
                <input v-model="form.contact_name" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">No. Telp</label>
                <input v-model="form.phone" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Email</label>
                <input v-model="form.email" type="email" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">NPWP</label>
                <input v-model="form.npwp" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Alamat</label>
                <textarea v-model="form.address" class="form-control form-control-sm" rows="2"></textarea>
              </div>
              <div class="col-12" v-if="form.id">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" v-model="form.is_active" id="supplierActive" />
                  <label class="form-check-label small" for="supplierActive">Supplier Aktif</label>
                </div>
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
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>
              {{ form.id ? 'Perbarui' : 'Simpan' }}
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
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { useRealtime } from '@/composables/useRealtime'

const auth = useAuthStore(); const toast = useToast()
const { listenSupplier, stopSupplier } = useRealtime()
const can = (p) => auth.hasPermission(p)
const suppliers = ref([]); const loading = ref(false); const saving = ref(false)
const search = ref(''); const activeFilter = ref('')
const form = ref({ id: null, code: '', name: '', contact_name: '', phone: '', email: '', npwp: '', address: '', is_active: true, notes: '' })
let modal = null; let timer = null

onMounted(() => {
  modal = new Modal(document.getElementById('supplierModal'))
  load()
  listenSupplier(() => load())
})

onUnmounted(() => stopSupplier())

async function load() {
  loading.value = true
  try {
    const params = { search: search.value }
    if (activeFilter.value !== '') params.active = activeFilter.value
    const r = await axios.get('/suppliers', { params })
    suppliers.value = r.data.data
  } finally { loading.value = false }
}
function debouncedLoad() { clearTimeout(timer); timer = setTimeout(load, 400) }

function openModal(s = null) {
  form.value = s
    ? { id: s.id, code: s.code, name: s.name, contact_name: s.contact_name || '', phone: s.phone || '', email: s.email || '', npwp: s.npwp || '', address: s.address || '', is_active: s.is_active, notes: s.notes || '' }
    : { id: null, code: '', name: '', contact_name: '', phone: '', email: '', npwp: '', address: '', is_active: true, notes: '' }
  modal.show()
}

async function save() {
  saving.value = true
  try {
    if (form.value.id) {
      await axios.put(`/suppliers/${form.value.id}`, form.value)
      toast.success('Supplier diperbarui')
    } else {
      await axios.post('/suppliers', form.value)
      toast.success('Supplier ditambahkan')
    }
    modal.hide(); load()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
</script>