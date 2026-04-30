<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">
          <i class="bi bi-shield-check me-2 text-primary"></i>Audit Log
        </h5>
        <small class="text-muted">Riwayat seluruh aktivitas pengguna di sistem</small>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <input
              v-model="filters.search"
              class="form-control form-control-sm"
              placeholder="🔍 Cari deskripsi / user / modul..."
              @input="debouncedLoad"
            />
          </div>
          <div class="col-md-2">
            <select v-model="filters.module" class="form-select form-select-sm" @change="load">
              <option value="">Semua Modul</option>
              <option v-for="m in availableModules" :key="m" :value="m">{{ moduleLabel(m) }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="filters.action" class="form-select form-select-sm" @change="load">
              <option value="">Semua Aksi</option>
              <option v-for="a in availableActions" :key="a" :value="a">{{ actionLabel(a) }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="load" title="Dari tanggal" />
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="load" title="Sampai tanggal" />
          </div>
          <div class="col-md-1">
            <button class="btn btn-outline-secondary btn-sm w-100" @click="resetFilters" title="Reset filter">
              <i class="bi bi-x-circle"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-5 text-center">
          <div class="csm-spinner"></div>
        </div>

        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th style="width:160px;">Waktu</th>
                <th style="width:140px;">User</th>
                <th style="width:100px;">Modul</th>
                <th style="width:90px;">Aksi</th>
                <th>Deskripsi</th>
                <th style="width:100px;">IP Address</th>
                <th style="width:50px;"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!logs.length">
                <td colspan="7" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                  Tidak ada data audit log
                </td>
              </tr>
              <tr v-for="log in logs" :key="log.id">
                <td>
                  <small class="text-muted d-block">{{ formatDate(log.created_at) }}</small>
                  <small class="text-muted" style="font-size:0.72rem;">{{ formatTime(log.created_at) }}</small>
                </td>
                <td>
                  <div class="fw-semibold small">{{ log.user_name || '—' }}</div>
                  <span v-if="log.user_role" class="badge badge-sm" :class="roleClass(log.user_role)" style="font-size:0.65rem;">
                    {{ log.user_role }}
                  </span>
                </td>
                <td>
                  <span class="badge bg-light text-dark border small">{{ moduleLabel(log.module) }}</span>
                </td>
                <td>
                  <span class="badge" :class="`bg-${actionBadgeClass(log.action)}`">
                    {{ actionLabel(log.action) }}
                  </span>
                </td>
                <td>
                  <span class="small">{{ log.description }}</span>
                </td>
                <td>
                  <small class="text-muted font-monospace">{{ log.ip_address || '—' }}</small>
                </td>
                <td>
                  <button
                    class="btn btn-xs btn-outline-secondary"
                    @click="showDetail(log)"
                    title="Lihat detail"
                  >
                    <i class="bi bi-eye"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">
            Menampilkan {{ ((meta.page - 1) * meta.per_page) + 1 }}–{{ Math.min(meta.page * meta.per_page, meta.total) }}
            dari {{ meta.total.toLocaleString('id-ID') }} log
          </small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
            <span class="btn btn-xs btn-outline-primary disabled">{{ meta.page }} / {{ meta.last_page }}</span>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="auditDetailModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedLog">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="bi bi-shield-check me-2"></i>Detail Audit Log #{{ selectedLog.id }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small text-muted">WAKTU</label>
                <div>{{ formatDateTime(selectedLog.created_at) }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small text-muted">IP ADDRESS</label>
                <div class="font-monospace">{{ selectedLog.ip_address || '—' }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small text-muted">USER</label>
                <div>
                  {{ selectedLog.user_name || '—' }}
                  <span v-if="selectedLog.user_role" class="badge ms-1" :class="roleClass(selectedLog.user_role)">
                    {{ selectedLog.user_role }}
                  </span>
                </div>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted">MODUL</label>
                <div><span class="badge bg-light text-dark border">{{ moduleLabel(selectedLog.module) }}</span></div>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-semibold small text-muted">AKSI</label>
                <div>
                  <span class="badge" :class="`bg-${actionBadgeClass(selectedLog.action)}`">
                    {{ actionLabel(selectedLog.action) }}
                  </span>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small text-muted">DESKRIPSI</label>
                <div>{{ selectedLog.description }}</div>
              </div>
              <div class="col-12" v-if="selectedLog.url">
                <label class="form-label fw-semibold small text-muted">URL</label>
                <div class="font-monospace small text-break">
                  <span class="badge bg-secondary me-1">{{ selectedLog.method }}</span>{{ selectedLog.url }}
                </div>
              </div>
            </div>

            <!-- Data Perubahan -->
            <div v-if="selectedLog.old_values || selectedLog.new_values" class="mt-2">
              <label class="form-label fw-semibold small text-muted">DATA PERUBAHAN</label>
              <div class="row g-2">
                <div class="col-md-6" v-if="selectedLog.old_values">
                  <div class="p-2 rounded border bg-light">
                    <div class="fw-semibold small text-danger mb-1"><i class="bi bi-dash-circle me-1"></i>Sebelum</div>
                    <pre class="mb-0 small" style="max-height:200px;overflow:auto;">{{ JSON.stringify(selectedLog.old_values, null, 2) }}</pre>
                  </div>
                </div>
                <div class="col-md-6" v-if="selectedLog.new_values">
                  <div class="p-2 rounded border bg-light">
                    <div class="fw-semibold small text-success mb-1"><i class="bi bi-plus-circle me-1"></i>Sesudah</div>
                    <pre class="mb-0 small" style="max-height:200px;overflow:auto;">{{ JSON.stringify(selectedLog.new_values, null, 2) }}</pre>
                  </div>
                </div>
              </div>
            </div>

            <!-- User Agent -->
            <div class="mt-3" v-if="selectedLog.user_agent">
              <label class="form-label fw-semibold small text-muted">BROWSER / USER AGENT</label>
              <div class="small text-muted text-break">{{ selectedLog.user_agent }}</div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Modal } from 'bootstrap'

// ─── State ───────────────────────────────────────────────────────────────────

const logs              = ref([])
const loading           = ref(false)
const meta              = ref({ total: 0, page: 1, last_page: 1, per_page: 50 })
const selectedLog       = ref(null)
const availableModules  = ref([])
const availableActions  = ref([])

const filters = ref({
  search:    '',
  module:    '',
  action:    '',
  date_from: '',
  date_to:   '',
})

let timer        = null
let detailModal  = null

// ─── Lifecycle ───────────────────────────────────────────────────────────────

onMounted(async () => {
  detailModal = new Modal(document.getElementById('auditDetailModal'))
  await Promise.all([loadMeta(), load()])
})

// ─── Data fetching ───────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/audit-logs', {
      params: {
        ...filters.value,
        page:     meta.value.page,
        per_page: 50,
      },
    })
    logs.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

async function loadMeta() {
  try {
    const { data } = await axios.get('/audit-logs/meta')
    availableModules.value = data.data.modules
    availableActions.value = data.data.actions
  } catch { /* ignore */ }
}

function debouncedLoad() {
  clearTimeout(timer)
  timer = setTimeout(() => { meta.value.page = 1; load() }, 400)
}

function changePage(p) {
  meta.value.page = p
  load()
}

function resetFilters() {
  filters.value = { search: '', module: '', action: '', date_from: '', date_to: '' }
  meta.value.page = 1
  load()
}

// ─── Detail modal ─────────────────────────────────────────────────────────────

function showDetail(log) {
  selectedLog.value = log
  detailModal.show()
}

// ─── Label helpers ────────────────────────────────────────────────────────────

function actionLabel(a) {
  const m = {
    create: 'Buat', update: 'Perbarui', delete: 'Hapus',
    login: 'Login', logout: 'Logout', export: 'Export',
    approve: 'Approve', reject: 'Tolak', dispatch: 'Kirim',
    receive: 'Terima', reset: 'Reset Password',
  }
  return m[a] || a
}

function actionBadgeClass(a) {
  const m = {
    create: 'success', update: 'primary', delete: 'danger',
    login: 'secondary', logout: 'secondary',
    approve: 'info', receive: 'info',
    reject: 'warning', export: 'dark', reset: 'warning',
  }
  return m[a] || 'secondary'
}

function moduleLabel(m) {
  const map = {
    auth: 'Auth', users: 'User', roles: 'Role',
    items: 'Barang', gudang: 'Gudang', stok: 'Stok',
    stok_opname: 'Stok Opname', mr: 'Material Request',
    pm: 'Permintaan Material', po: 'Purchase Order',
    sj: 'Surat Jalan', bon: 'Bon Pengeluaran',
    transfer: 'Transfer', retur: 'Retur',
    akuntansi: 'Akuntansi', payroll: 'Payroll',
    karyawan: 'Karyawan', unit: 'Unit Alat',
    apd: 'APD', bbm: 'BBM/Solar', system: 'System',
  }
  return map[m] || m
}

function roleClass(r) {
  const m = {
    superuser: 'bg-danger', admin_ho: 'bg-primary', admin_site: 'bg-info text-dark',
    manager: 'bg-success', chief_mekanik: 'bg-warning text-dark',
    purchasing: 'bg-dark', viewer: 'bg-secondary', accounting: 'bg-purple',
  }
  return m[r] || 'bg-secondary'
}

// ─── Date helpers ─────────────────────────────────────────────────────────────

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function formatDateTime(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
  })
}
</script>
