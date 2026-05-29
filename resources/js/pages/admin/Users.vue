<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Manajemen User</h5>
        <small class="text-muted">Kelola akun pengguna sistem</small>
      </div>
      <button class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Tambah User
      </button>
    </div>

    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-4">
            <input v-model="search" class="form-control form-control-sm" placeholder="🔍 Cari nama / email..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="roleFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Role</option>
              <option value="superuser">Superuser</option>
              <option value="admin_ho">Admin HO</option>
              <option value="admin_site">Admin Site</option>
              <option value="manager">Manager</option>
              <option value="chief_mekanik">Chief Mekanik</option>
              <option value="purchasing">Purchasing</option>
              <option value="viewer">Viewer</option>
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
              <tr><th>Nama</th><th>Email</th><th>Role</th><th>Gudang / Site</th><th>Login Terakhir</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              <tr v-if="!users.length">
                <td colspan="7" class="text-center text-muted py-4">Tidak ada data</td>
              </tr>
              <tr v-for="u in users" :key="u.id">
                <td>
                  <div class="fw-semibold d-flex align-items-center gap-2">
                    {{ u.name }}
                    <!-- Indikator TTD -->
                    <i
                      :class="u.has_signature ? 'bi bi-pen-fill text-success' : 'bi bi-pen text-warning'"
                      :title="u.has_signature ? 'Tanda tangan sudah diupload' : 'Belum upload tanda tangan'"
                      style="font-size:12px;"
                    ></i>
                  </div>
                  <small class="text-muted">{{ u.position || '-' }}</small>
                </td>
                <td><small>{{ u.email }}</small></td>
                <td>
                  <span v-for="r in u.roles" :key="r.id" :class="roleClass(r.name)" class="badge me-1">{{ r.name }}</span>
                </td>
                <td><small class="text-muted">{{ u.warehouse?.name || '-' }}</small></td>
                <td><small class="text-muted">{{ u.last_login_at ? $formatDate(u.last_login_at) : 'Belum pernah' }}</small></td>
                <td>
                  <span :class="u.is_active ? 'badge bg-success' : 'badge bg-secondary'">{{ u.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-primary" @click="openModal(u)" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-xs btn-outline-warning" @click="openResetPwd(u)" title="Reset Password"><i class="bi bi-key"></i></button>
                    <button class="btn btn-xs btn-outline-danger" @click="deleteUser(u)" title="Hapus" :disabled="u.id === authStore.user.id"><i class="bi bi-trash"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">{{ meta.total }} user</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page<=1" @click="changePage(meta.page-1)">‹</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page>=meta.last_page" @click="changePage(meta.page+1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ User Modal (Edit / Tambah) ══ -->
    <div class="modal fade" id="userModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ form.id ? 'Edit User' : 'Tambah User Baru' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                <input v-model="form.name" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Email <span class="text-danger">*</span></label>
                <input v-model="form.email" type="email" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6" v-if="!form.id">
                <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                <input v-model="form.password" type="password" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">No. HP</label>
                <input v-model="form.phone" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jabatan</label>
                <input v-model="form.position" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Role <span class="text-danger">*</span></label>
                <select v-model="form.role" class="form-select form-select-sm">
                  <option value="">-- Pilih Role --</option>
                  <option value="superuser">Superuser</option>
                  <option value="admin_ho">Admin HO</option>
                  <option value="admin_site">Admin Site</option>
                  <option value="manager">Manager</option>
                  <option value="chief_mekanik">Chief Mekanik</option>
                  <option value="purchasing">Purchasing</option>
                  <option value="viewer">Viewer</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Gudang / Site</label>
                <select v-model="form.warehouse_id" class="form-select form-select-sm">
                  <option value="">-- Tidak Ada / Semua --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-12" v-if="form.id">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" v-model="form.is_active" id="userActive">
                  <label class="form-check-label small" for="userActive">User Aktif</label>
                </div>
              </div>

              <!-- ══ TANDA TANGAN DIGITAL (hanya saat Edit) ══ -->
              <div class="col-12" v-if="form.id">
                <hr class="my-1" />
                <label class="form-label fw-semibold small mb-2">
                  <i class="bi bi-pen me-1"></i>Tanda Tangan Digital
                </label>

                <!-- Preview area -->
                <div
                  class="border rounded-3 text-center mb-2 position-relative"
                  :class="form.signature_preview ? 'border-success bg-light' : 'border-secondary bg-light'"
                  style="min-height:80px;"
                >
                  <img
                    v-if="form.signature_preview"
                    :src="form.signature_preview"
                    alt="TTD"
                    class="p-2"
                    style="max-height:70px; max-width:100%; object-fit:contain;"
                  />
                  <div v-else class="py-3 text-muted small">
                    <i class="bi bi-exclamation-circle me-1 text-warning"></i>
                    Belum ada tanda tangan
                  </div>
                </div>

                <!-- Feedback TTD -->
                <div v-if="sigError" class="alert alert-danger py-1 px-2 small mb-2">
                  <i class="bi bi-exclamation-triangle me-1"></i>{{ sigError }}
                </div>
                <div v-if="sigSuccess" class="alert alert-success py-1 px-2 small mb-2">
                  <i class="bi bi-check-circle me-1"></i>{{ sigSuccess }}
                </div>

                <!-- Tombol upload / hapus TTD -->
                <div class="d-flex gap-2">
                  <label
                    class="btn btn-outline-primary btn-sm mb-0"
                    :class="{ disabled: sigUploading }"
                    style="cursor:pointer;"
                  >
                    <span v-if="sigUploading"><span class="csm-spinner me-1"></span></span>
                    <i v-else class="bi bi-upload me-1"></i>
                    {{ sigUploading ? 'Mengupload...' : (form.signature_preview ? 'Ganti TTD' : 'Upload TTD') }}
                    <input
                      ref="sigInput"
                      type="file"
                      accept="image/png,image/jpeg"
                      class="d-none"
                      @change="handleUploadFor"
                      :disabled="sigUploading"
                    />
                  </label>

                  <button
                    v-if="form.signature_preview"
                    class="btn btn-outline-danger btn-sm"
                    @click="handleDeleteFor"
                    :disabled="sigDeleting"
                  >
                    <span v-if="sigDeleting"><span class="csm-spinner me-1"></span></span>
                    <i v-else class="bi bi-trash me-1"></i>
                    {{ sigDeleting ? 'Menghapus...' : 'Hapus TTD' }}
                  </button>
                </div>
                <div class="text-muted mt-1" style="font-size:11px;">
                  PNG/JPG, maks. 2MB. User juga bisa upload sendiri di halaman Profil.
                </div>
              </div>

            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="save" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>
              {{ form.id ? 'Perbarui' : 'Buat User' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPwdModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Reset Password: {{ selectedUser?.name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label fw-semibold small">Password Baru <span class="text-danger">*</span></label>
            <input v-model="newPassword" type="password" class="form-control form-control-sm" placeholder="Min. 8 karakter" />
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-warning btn-sm" @click="doResetPwd" :disabled="saving || !newPassword || newPassword.length < 8">
              Reset Password
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
import Swal from 'sweetalert2'
import { useRealtime } from '@/composables/useRealtime'

const authStore = useAuthStore()
const toast     = useToast()
const { listenUsers, stopUsers } = useRealtime()

const users      = ref([])
const warehouses = ref([])
const loading    = ref(false)
const saving     = ref(false)
const search     = ref('')
const roleFilter = ref('')
const meta       = ref({ total: 0, page: 1, last_page: 1 })
const form       = ref({
  id: null, name: '', email: '', password: '', phone: '',
  position: '', role: '', warehouse_id: '', is_active: true,
  signature_preview: null,
})
const selectedUser = ref(null)
const newPassword  = ref('')

// ── State tanda tangan di modal ──────────────────────────────────────
const sigUploading = ref(false)
const sigDeleting  = ref(false)
const sigError     = ref(null)
const sigSuccess   = ref(null)
const sigInput     = ref(null)

let userModal  = null
let resetModal = null
let timer      = null

onMounted(async () => {
  userModal  = new Modal(document.getElementById('userModal'))
  resetModal = new Modal(document.getElementById('resetPwdModal'))
  const r = await axios.get('/warehouses')
  warehouses.value = r.data.data
  load()
  listenUsers(() => load())
})

onUnmounted(() => stopUsers())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/users', {
      params: { search: search.value, role: roleFilter.value, page: meta.value.page }
    })
    users.value = r.data.data
    meta.value  = r.data.meta
  } finally {
    loading.value = false
  }
}

function debouncedLoad() {
  clearTimeout(timer)
  timer = setTimeout(() => { meta.value.page = 1; load() }, 400)
}

function changePage(p) { meta.value.page = p; load() }

function roleClass(r) {
  const m = {
    superuser:'bg-danger', admin_ho:'bg-primary', admin_site:'bg-info text-dark',
    manager:'bg-success', chief_mekanik:'bg-warning text-dark',
    purchasing:'bg-dark', viewer:'bg-secondary'
  }
  return m[r] || 'bg-secondary'
}

function clearSigFeedback() { sigError.value = null; sigSuccess.value = null }

function openModal(u = null) {
  clearSigFeedback()
  if (u) {
    form.value = {
      id:                u.id,
      name:              u.name,
      email:             u.email,
      password:          '',
      phone:             u.phone        || '',
      position:          u.position     || '',
      role:              u.roles?.[0]?.name || '',
      warehouse_id:      u.warehouse_id || '',
      is_active:         u.is_active,
      signature_preview: u.signature_preview ?? null,
    }
  } else {
    form.value = {
      id: null, name: '', email: '', password: '', phone: '',
      position: '', role: '', warehouse_id: '', is_active: true,
      signature_preview: null,
    }
  }
  userModal.show()
}

async function save() {
  saving.value = true
  try {
    if (form.value.id) {
      await axios.put(`/users/${form.value.id}`, form.value)
      toast.success('User diperbarui')
    } else {
      await axios.post('/users', form.value)
      toast.success('User berhasil dibuat')
    }
    userModal.hide()
    load()
  } catch(e) {
    toast.error(
      e.response?.data?.message ||
      Object.values(e.response?.data?.errors || {})[0]?.[0] ||
      'Gagal menyimpan'
    )
  } finally {
    saving.value = false
  }
}

// ── Upload TTD untuk user lain (oleh superuser) ──────────────────────
async function handleUploadFor(e) {
  const file = e.target.files?.[0]
  if (!file) return
  clearSigFeedback()

  if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
    sigError.value = 'Hanya file PNG atau JPG yang diizinkan.'; return
  }
  if (file.size > 2 * 1024 * 1024) {
    sigError.value = 'Ukuran file maksimal 2MB.'; return
  }

  // Preview lokal sementara
  const reader = new FileReader()
  reader.onload = ev => { form.value.signature_preview = ev.target.result }
  reader.readAsDataURL(file)

  sigUploading.value = true
  try {
    const formData = new FormData()
    formData.append('signature_file', file)
    const { data } = await axios.post(`/users/${form.value.id}/signature`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    form.value.signature_preview = data.data.preview_url
    sigSuccess.value = 'Tanda tangan berhasil disimpan!'
    // Update indikator di tabel
    const idx = users.value.findIndex(u => u.id === form.value.id)
    if (idx !== -1) users.value[idx].has_signature = true
  } catch (err) {
    sigError.value = err.response?.data?.message ?? 'Gagal mengupload tanda tangan.'
    form.value.signature_preview = null
  } finally {
    sigUploading.value = false
    if (sigInput.value) sigInput.value.value = ''
  }
}

// ── Hapus TTD user lain (oleh superuser) ────────────────────────────
async function handleDeleteFor() {
  const confirmed = window.confirm(`Hapus tanda tangan ${form.value.name}?`)
  if (!confirmed) return
  clearSigFeedback()
  sigDeleting.value = true
  try {
    await axios.delete(`/users/${form.value.id}/signature`)
    form.value.signature_preview = null
    sigSuccess.value = 'Tanda tangan berhasil dihapus.'
    // Update indikator di tabel
    const idx = users.value.findIndex(u => u.id === form.value.id)
    if (idx !== -1) users.value[idx].has_signature = false
  } catch (err) {
    sigError.value = err.response?.data?.message ?? 'Gagal menghapus tanda tangan.'
  } finally {
    sigDeleting.value = false
  }
}

function openResetPwd(u) {
  selectedUser.value = u
  newPassword.value  = ''
  resetModal.show()
}

async function doResetPwd() {
  saving.value = true
  try {
    await axios.post(`/users/${selectedUser.value.id}/reset-password`, { password: newPassword.value })
    toast.success('Password berhasil direset')
    resetModal.hide()
  } catch(e) {
    toast.error('Gagal reset password')
  } finally {
    saving.value = false
  }
}

async function deleteUser(u) {
  const r = await Swal.fire({
    title: `Hapus user ${u.name}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e74c3c',
    confirmButtonText: 'Hapus'
  })
  if (!r.isConfirmed) return
  try {
    await axios.delete(`/users/${u.id}`)
    toast.success('User dihapus')
    load()
  } catch(e) {
    toast.error('Gagal menghapus')
  }
}
</script>