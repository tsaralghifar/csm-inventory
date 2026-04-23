<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Master Kategori</h5>
      <button v-if="can('manage-items')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Tambah Kategori
      </button>
    </div>
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
        <table class="table csm-table mb-0" v-else>
          <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Deskripsi</th><th class="text-end">Jml Barang</th><th>Aksi</th></tr></thead>
          <tbody>
            <tr v-if="!categories.length"><td colspan="6" class="text-center text-muted py-4">Tidak ada data</td></tr>
            <tr v-for="(c,i) in categories" :key="c.id">
              <td>{{ i+1 }}</td>
              <td><span class="badge bg-primary">{{ c.code }}</span></td>
              <td class="fw-semibold">{{ c.name }}</td>
              <td class="text-muted small">{{ c.description || '-' }}</td>
              <td class="text-end"><span class="badge bg-light text-dark border">{{ c.items_count }}</span></td>
              <td>
                <button v-if="can('manage-items')" class="btn btn-xs btn-outline-primary me-1" @click="openModal(c)"><i class="bi bi-pencil"></i></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal fade" id="katModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.id ? 'Edit Kategori' : 'Tambah Kategori' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label fw-semibold small">Kode <span class="text-danger">*</span></label>
              <input v-model="form.code" class="form-control form-control-sm" :disabled="!!form.id" />
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Nama Kategori <span class="text-danger">*</span></label>
              <input v-model="form.name" class="form-control form-control-sm" />
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">Deskripsi</label>
              <textarea v-model="form.description" class="form-control form-control-sm" rows="2"></textarea>
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
const { listenMaster, stopMaster } = useRealtime()
const can = (p) => auth.hasPermission(p)
const categories = ref([]); const loading = ref(false); const saving = ref(false)
const form = ref({ id: null, code: '', name: '', description: '' })
let modal = null; let suppressNextToast = false

onMounted(async () => {
  modal = new Modal(document.getElementById('katModal')); await load()
  const actionLabels = {"created": "Kategori baru ditambahkan", "updated": "Kategori diperbarui", "deleted": "Kategori dihapus"}
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
  try { const r = await axios.get('/categories'); categories.value = r.data.data } finally { loading.value = false }
}
function openModal(c = null) {
  form.value = c ? { id: c.id, code: c.code, name: c.name, description: c.description||'' } : { id: null, code: '', name: '', description: '' }
  modal.show()
}
async function save() {
  saving.value = true
  try {
    if (form.value.id) { await axios.put(`/categories/${form.value.id}`, form.value); toast.success('Kategori diperbarui') }
    else { await axios.post('/categories', form.value); toast.success('Kategori ditambahkan') }
    suppressNextToast = true
    modal.hide(); load()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
</script>