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
          <div class="col-md-4"><input v-model="search" class="form-control form-control-sm" placeholder="🔍 Cari nama / email..." @input="debouncedLoad" /></div>
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
            <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Gudang / Site</th><th>Login Terakhir</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              <tr v-if="!users.length"><td colspan="7" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="u in users" :key="u.id">
                <td>
                  <div class="fw-semibold">{{ u.name }}</div>
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

    <!-- User Modal -->
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
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="save" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>{{ form.id ? 'Perbarui' : 'Buat User' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPwdModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Reset Password: {{ selectedUser?.name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <label class="form-label fw-semibold small">Password Baru <span class="text-danger">*</span></label>
            <input v-model="newPassword" type="password" class="form-control form-control-sm" placeholder="Min. 8 karakter" />
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-warning btn-sm" @click="doResetPwd" :disabled="saving || !newPassword || newPassword.length < 8">Reset Password</button>
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

const authStore = useAuthStore(); const toast = useToast()
const { listenUsers, stopUsers } = useRealtime()
const users = ref([]); const warehouses = ref([]); const loading = ref(false); const saving = ref(false)
const search = ref(''); const roleFilter = ref('')
const meta = ref({ total: 0, page: 1, last_page: 1 })
const form = ref({ id: null, name: '', email: '', password: '', phone: '', position: '', role: '', warehouse_id: '', is_active: true })
const selectedUser = ref(null); const newPassword = ref('')
let userModal = null; let resetModal = null; let timer = null

onMounted(async () => {
  userModal = new Modal(document.getElementById('userModal'))
  resetModal = new Modal(document.getElementById('resetPwdModal'))
  const r = await axios.get('/warehouses'); warehouses.value = r.data.data
  load()
  listenUsers(() => load())
})

onUnmounted(() => stopUsers())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/users', { params: { search: search.value, role: roleFilter.value, page: meta.value.page } })
    users.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}
function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page=1; load() }, 400) }
function changePage(p) { meta.value.page = p; load() }

function roleClass(r) {
  const m = { superuser:'bg-danger', admin_ho:'bg-primary', admin_site:'bg-info text-dark', manager:'bg-success', chief_mekanik:'bg-warning text-dark', purchasing:'bg-dark', viewer:'bg-secondary' }
  return m[r] || 'bg-secondary'
}

function openModal(u = null) {
  if (u) {
    form.value = { id: u.id, name: u.name, email: u.email, password: '', phone: u.phone||'', position: u.position||'', role: u.roles?.[0]?.name||'', warehouse_id: u.warehouse_id||'', is_active: u.is_active }
  } else {
    form.value = { id: null, name: '', email: '', password: '', phone: '', position: '', role: '', warehouse_id: '', is_active: true }
  }
  userModal.show()
}

async function save() {
  saving.value = true
  try {
    if (form.value.id) { await axios.put(`/users/${form.value.id}`, form.value); toast.success('User diperbarui') }
    else { await axios.post('/users', form.value); toast.success('User berhasil dibuat') }
    userModal.hide(); load()
  } catch(e) { toast.error(e.response?.data?.message || Object.values(e.response?.data?.errors||{})[0]?.[0] || 'Gagal menyimpan') }
  finally { saving.value = false }
}

function openResetPwd(u) { selectedUser.value = u; newPassword.value = ''; resetModal.show() }
async function doResetPwd() {
  saving.value = true
  try {
    await axios.post(`/users/${selectedUser.value.id}/reset-password`, { password: newPassword.value })
    toast.success('Password berhasil direset'); resetModal.hide()
  } catch(e) { toast.error('Gagal reset password') } finally { saving.value = false }
}

async function deleteUser(u) {
  const r = await Swal.fire({ title: `Hapus user ${u.name}?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#e74c3c', confirmButtonText: 'Hapus' })
  if (!r.isConfirmed) return
  try { await axios.delete(`/users/${u.id}`); toast.success('User dihapus'); load() } catch(e) { toast.error('Gagal menghapus') }
}
</script>