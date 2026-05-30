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
              <option v-for="r in availableRoles" :key="r.value" :value="r.value">{{ r.label }}</option>
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
                  <option v-for="r in availableRoles" :key="r.value" :value="r.value">{{ r.label }}</option>
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
                <hr class="my-2" />

                <!-- Header -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                      style="width:28px;height:28px;background:#f0f5ff;">
                      <i class="bi bi-pen-fill" style="color:#1a3a5c;font-size:.75rem;"></i>
                    </div>
                    <span class="fw-semibold small" style="color:#1a3a5c;">Tanda Tangan Digital</span>
                  </div>
                  <span v-if="form.signature_preview"
                    class="badge d-flex align-items-center gap-1"
                    style="background:#d1fae5;color:#065f46;font-size:.68rem;font-weight:600;">
                    <i class="bi bi-check-circle-fill"></i> Tersedia
                  </span>
                  <span v-else
                    class="badge d-flex align-items-center gap-1"
                    style="background:#fef3c7;color:#92400e;font-size:.68rem;font-weight:600;">
                    <i class="bi bi-exclamation-triangle-fill"></i> Belum ada
                  </span>
                </div>

                <!-- Preview area -->
                <div
                  class="rounded-3 text-center mb-2 position-relative overflow-hidden"
                  style="border:2px dashed;transition:border-color .2s,background .2s;min-height:110px;"
                  :style="form.signature_preview
                    ? 'border-color:#10b981;background:#f0fdf4;'
                    : 'border-color:#d1d5db;background:#f9fafb;'"
                >
                  <template v-if="form.signature_preview">
                    <img
                      :src="form.signature_preview"
                      alt="Tanda Tangan"
                      class="p-3"
                      style="max-height:90px;max-width:100%;object-fit:contain;cursor:zoom-in;display:block;margin:auto;"
                      @click="sigZoom = true"
                      title="Klik untuk perbesar"
                    />
                    <div class="position-absolute bottom-0 end-0 m-1">
                      <span class="badge bg-dark bg-opacity-50" style="font-size:.6rem;">
                        <i class="bi bi-zoom-in me-1"></i>Perbesar
                      </span>
                    </div>
                  </template>
                  <div v-else class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
                    <i class="bi bi-pen" style="font-size:2rem;opacity:.25;"></i>
                    <div class="small mt-2" style="font-size:.78rem;">Belum ada tanda tangan</div>
                    <div class="mt-1" style="font-size:.7rem;color:#9ca3af;">Upload file PNG/JPG</div>
                  </div>
                </div>

                <!-- Feedback -->
                <div v-if="sigError"
                  class="d-flex align-items-center gap-2 rounded-2 px-3 py-2 mb-2 small"
                  style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;">
                  <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                  <span>{{ sigError }}</span>
                </div>
                <div v-if="sigSuccess"
                  class="d-flex align-items-center gap-2 rounded-2 px-3 py-2 mb-2 small"
                  style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;">
                  <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                  <span>{{ sigSuccess }}</span>
                </div>

                <!-- Tombol aksi -->
                <div class="d-flex gap-2 align-items-center">
                  <label
                    class="btn btn-sm mb-0 d-flex align-items-center gap-2"
                    :class="sigUploading ? 'btn-secondary disabled' : (form.signature_preview ? 'btn-outline-primary' : 'btn-primary')"
                    style="cursor:pointer;flex:1;justify-content:center;"
                  >
                    <span v-if="sigUploading" class="spinner-border spinner-border-sm" style="width:13px;height:13px;border-width:2px;"></span>
                    <i v-else :class="form.signature_preview ? 'bi bi-arrow-repeat' : 'bi bi-cloud-upload-fill'"></i>
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
                    class="btn btn-sm btn-outline-danger d-flex align-items-center gap-2"
                    @click="handleDeleteFor"
                    :disabled="sigDeleting"
                  >
                    <span v-if="sigDeleting" class="spinner-border spinner-border-sm" style="width:13px;height:13px;border-width:2px;"></span>
                    <i v-else class="bi bi-trash3"></i>
                    {{ sigDeleting ? 'Menghapus...' : 'Hapus' }}
                  </button>
                </div>

                <!-- Info box -->
                <div class="d-flex align-items-start gap-2 mt-2 rounded-2 px-3 py-2"
                  style="background:#eff6ff;border:1px solid #bfdbfe;font-size:.7rem;color:#1e40af;">
                  <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
                  <span>PNG/JPG, maks. 3MB. Gunakan latar transparan untuk hasil terbaik. User juga bisa upload sendiri di halaman <strong>Profil</strong>.</span>
                </div>
              </div>

              <!-- Lightbox zoom TTD -->
              <Teleport to="body">
                <div
                  v-if="sigZoom && form.signature_preview"
                  class="position-fixed d-flex align-items-center justify-content-center"
                  style="inset:0;z-index:9999;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);"
                  @click="sigZoom = false"
                >
                  <div class="position-relative" @click.stop>
                    <button
                      class="btn btn-sm btn-light position-absolute"
                      style="top:-14px;right:-14px;width:32px;height:32px;padding:0;border-radius:50%;z-index:1;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);"
                      @click="sigZoom = false"
                    ><i class="bi bi-x-lg" style="font-size:.75rem;"></i></button>
                    <img
                      :src="form.signature_preview"
                      alt="Tanda Tangan"
                      style="max-height:80vh;max-width:90vw;object-fit:contain;border-radius:12px;background:#fff;padding:28px;box-shadow:0 8px 40px rgba(0,0,0,.5);"
                    />
                    <div class="text-center mt-2" style="color:#cbd5e1;font-size:.72rem;">
                      <i class="bi bi-x-circle me-1"></i>Klik di luar gambar atau tombol × untuk menutup
                    </div>
                  </div>
                </div>
              </Teleport>

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
const sigZoom      = ref(false)

let userModal  = null
let resetModal = null
let timer      = null

const availableRoles = ref([])

onMounted(async () => {
  userModal  = new Modal(document.getElementById('userModal'))
  resetModal = new Modal(document.getElementById('resetPwdModal'))
  const [warehousesRes, rolesRes] = await Promise.all([
    axios.get('/warehouses'),
    axios.get('/roles').catch(() => ({ data: { data: [] } })),
  ])
  warehouses.value = warehousesRes.data.data
  // Build availableRoles dari API — otomatis include role baru tanpa perlu edit frontend
  const apiRoles = rolesRes.data.data || rolesRes.data || []
  if (apiRoles.length) {
    availableRoles.value = apiRoles.map(r => ({
      value: r.name,
      label: roleLabel(r.name),
    }))
  } else {
    // Fallback statis jika endpoint /roles tidak tersedia
    availableRoles.value = [
      { value: 'superuser',     label: 'Superuser' },
      { value: 'admin_ho',      label: 'Admin HO' },
      { value: 'admin_site',    label: 'Admin Site' },
      { value: 'manager',       label: 'Manager' },
      { value: 'chief_mekanik', label: 'Chief Mekanik' },
      { value: 'purchasing',    label: 'Purchasing' },
      { value: 'accounting',    label: 'Accounting' },
      { value: 'viewer',        label: 'Viewer' },
      { value: 'logistik_ho',   label: 'Logistik HO' },
      { value: 'logistik_site', label: 'Logistik Site' },
    ]
  }
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
    purchasing:'bg-dark', viewer:'bg-secondary',
    accounting:'bg-purple',
    logistik_ho:'bg-teal', logistik_site:'bg-orange',
  }
  return m[r] || 'bg-secondary'
}

// Label display per role — fallback ke nama role jika tidak ada di map
function roleLabel(name) {
  const m = {
    superuser: 'Superuser', admin_ho: 'Admin HO', admin_site: 'Admin Site',
    manager: 'Manager', chief_mekanik: 'Chief Mekanik',
    purchasing: 'Purchasing', viewer: 'Viewer',
    accounting: 'Accounting',
    logistik_ho: 'Logistik HO', logistik_site: 'Logistik Site',
  }
  return m[name] || name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function clearSigFeedback() { sigError.value = null; sigSuccess.value = null; sigZoom.value = false }

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
  if (file.size > 3 * 1024 * 1024) {
    sigError.value = 'Ukuran file maksimal 3MB.'; return
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
<style scoped>
.bg-teal   { background-color: #0d9488 !important; color: #fff !important; }
.bg-orange { background-color: #ea580c !important; color: #fff !important; }
.bg-purple { background-color: #7c3aed !important; color: #fff !important; }
</style>