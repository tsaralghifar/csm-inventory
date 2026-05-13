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

    <!-- ─── Modal Detail (teleport ke body agar tidak terpengaruh z-index parent) ─── -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="audit-overlay"
        @click.self="closeModal"
      >
        <div class="audit-modal-dialog">
          <div class="audit-modal-content" v-if="selectedLog">

            <!-- Modal Header -->
            <div class="audit-modal-header">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-primary fs-5"></i>
                <span class="fw-bold">Detail Audit Log <span class="text-muted">#{{ selectedLog.id }}</span></span>
              </div>
              <button class="audit-close-btn" @click="closeModal">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>

            <!-- Modal Body -->
            <div class="audit-modal-body">

              <!-- Info Grid -->
              <div class="audit-info-grid">
                <div class="audit-info-item">
                  <div class="audit-info-label"><i class="bi bi-clock me-1"></i>Waktu</div>
                  <div class="audit-info-value">{{ formatDateTime(selectedLog.created_at) }}</div>
                </div>
                <div class="audit-info-item">
                  <div class="audit-info-label"><i class="bi bi-wifi me-1"></i>IP Address</div>
                  <div class="audit-info-value font-monospace">{{ selectedLog.ip_address || '—' }}</div>
                </div>
                <div class="audit-info-item">
                  <div class="audit-info-label"><i class="bi bi-person me-1"></i>User</div>
                  <div class="audit-info-value">
                    {{ selectedLog.user_name || '—' }}
                    <span v-if="selectedLog.user_role" class="badge ms-1" :class="roleClass(selectedLog.user_role)" style="font-size:0.65rem;">
                      {{ selectedLog.user_role }}
                    </span>
                  </div>
                </div>
                <div class="audit-info-item">
                  <div class="audit-info-label"><i class="bi bi-grid me-1"></i>Modul</div>
                  <div class="audit-info-value">
                    <span class="badge bg-light text-dark border">{{ moduleLabel(selectedLog.module) }}</span>
                  </div>
                </div>
                <div class="audit-info-item">
                  <div class="audit-info-label"><i class="bi bi-lightning me-1"></i>Aksi</div>
                  <div class="audit-info-value">
                    <span class="badge" :class="`bg-${actionBadgeClass(selectedLog.action)}`">
                      {{ actionLabel(selectedLog.action) }}
                    </span>
                  </div>
                </div>
                <div class="audit-info-item" v-if="selectedLog.method">
                  <div class="audit-info-label"><i class="bi bi-arrow-right-circle me-1"></i>Method</div>
                  <div class="audit-info-value">
                    <span class="badge bg-secondary">{{ selectedLog.method }}</span>
                  </div>
                </div>
              </div>

              <!-- Deskripsi -->
              <div class="audit-section">
                <div class="audit-section-label">Deskripsi</div>
                <div class="audit-section-value">{{ selectedLog.description }}</div>
              </div>

              <!-- URL -->
              <div class="audit-section" v-if="selectedLog.url">
                <div class="audit-section-label">URL</div>
                <div class="audit-section-value font-monospace small text-break">{{ selectedLog.url }}</div>
              </div>

              <!-- ─── DATA PERUBAHAN (Before vs After) ─── -->
              <div v-if="selectedLog.old_values || selectedLog.new_values" class="audit-diff-section">
                <div class="audit-section-label mb-2">
                  <i class="bi bi-arrow-left-right me-1"></i>Data Perubahan
                </div>
                <div class="audit-diff-grid">

                  <!-- SEBELUM -->
                  <div class="audit-diff-box audit-diff-old" v-if="selectedLog.old_values">
                    <div class="audit-diff-title">
                      <i class="bi bi-dash-circle-fill me-1"></i>Sebelum
                    </div>
                    <div class="audit-diff-rows">
                      <div
                        v-for="(val, key) in selectedLog.old_values"
                        :key="key"
                        class="audit-diff-row"
                        :class="{ 'audit-diff-changed': isDifferent(key, selectedLog.old_values, selectedLog.new_values) }"
                      >
                        <span class="audit-diff-key">{{ key }}</span>
                        <span class="audit-diff-val">{{ formatVal(val) }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- SESUDAH -->
                  <div class="audit-diff-box audit-diff-new" v-if="selectedLog.new_values">
                    <div class="audit-diff-title">
                      <i class="bi bi-plus-circle-fill me-1"></i>Sesudah
                    </div>
                    <div class="audit-diff-rows">
                      <div
                        v-for="(val, key) in selectedLog.new_values"
                        :key="key"
                        class="audit-diff-row"
                        :class="{ 'audit-diff-changed': isDifferent(key, selectedLog.old_values, selectedLog.new_values) }"
                      >
                        <span class="audit-diff-key">{{ key }}</span>
                        <span class="audit-diff-val">{{ formatVal(val) }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Jika hanya ada satu sisi (create atau delete) -->
                  <div class="audit-diff-box audit-diff-new" v-if="!selectedLog.old_values && selectedLog.new_values">
                    <!-- sudah dirender di atas -->
                  </div>
                </div>
              </div>

              <!-- Tidak ada perubahan data -->
              <div v-else class="audit-no-diff">
                <i class="bi bi-info-circle me-1"></i>Tidak ada data perubahan tercatat untuk aksi ini.
              </div>

              <!-- User Agent -->
              <div class="audit-section" v-if="selectedLog.user_agent">
                <div class="audit-section-label">Browser / User Agent</div>
                <div class="audit-section-value small text-muted text-break">{{ selectedLog.user_agent }}</div>
              </div>

            </div>

            <!-- Modal Footer -->
            <div class="audit-modal-footer">
              <button class="btn btn-secondary btn-sm" @click="closeModal">
                <i class="bi bi-x me-1"></i>Tutup
              </button>
            </div>

          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

// ─── State ───────────────────────────────────────────────────────────────────

const logs             = ref([])
const loading          = ref(false)
const meta             = ref({ total: 0, page: 1, last_page: 1, per_page: 50 })
const selectedLog      = ref(null)
const showModal        = ref(false)
const availableModules = ref([])
const availableActions = ref([])

const filters = ref({
  search:    '',
  module:    '',
  action:    '',
  date_from: '',
  date_to:   '',
})

let timer = null

// ─── Lifecycle ───────────────────────────────────────────────────────────────

onMounted(async () => {
  await Promise.all([loadMeta(), load()])
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})

function onKeydown(e) {
  if (e.key === 'Escape' && showModal.value) closeModal()
}

// ─── Data fetching ────────────────────────────────────────────────────────────

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

// ─── Modal ────────────────────────────────────────────────────────────────────

function showDetail(log) {
  selectedLog.value = log
  showModal.value   = true
  document.body.style.overflow = 'hidden'
}

function closeModal() {
  showModal.value  = false
  selectedLog.value = null
  document.body.style.overflow = ''
}

// ─── Diff helper ──────────────────────────────────────────────────────────────

function isDifferent(key, oldVals, newVals) {
  if (!oldVals || !newVals) return false
  return String(oldVals[key] ?? '') !== String(newVals[key] ?? '')
}

function formatVal(val) {
  if (val === null || val === undefined) return '—'
  if (typeof val === 'boolean') return val ? 'Ya' : 'Tidak'
  if (typeof val === 'object') return JSON.stringify(val)
  return String(val)
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

<style scoped>
/* ─── Overlay ──────────────────────────────────────────────────────────── */
.audit-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  animation: fadeIn 0.15s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ─── Dialog ───────────────────────────────────────────────────────────── */
.audit-modal-dialog {
  width: 100%;
  max-width: 760px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  animation: slideUp 0.2s ease;
}

@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}

.audit-modal-content {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.25);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  overflow: hidden;
}

/* ─── Header ───────────────────────────────────────────────────────────── */
.audit-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e9ecef;
  background: #f8f9fa;
  border-radius: 12px 12px 0 0;
  flex-shrink: 0;
}

.audit-close-btn {
  background: none;
  border: none;
  font-size: 1rem;
  color: #6c757d;
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  transition: background 0.15s;
}
.audit-close-btn:hover { background: #e9ecef; color: #212529; }

/* ─── Body ─────────────────────────────────────────────────────────────── */
.audit-modal-body {
  padding: 1.25rem;
  overflow-y: auto;
  flex: 1;
}

/* ─── Info Grid ────────────────────────────────────────────────────────── */
.audit-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
  background: #f8f9fa;
  border-radius: 8px;
  padding: 1rem;
  border: 1px solid #e9ecef;
}

.audit-info-label {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6c757d;
  margin-bottom: 0.2rem;
}

.audit-info-value {
  font-size: 0.875rem;
  color: #212529;
}

/* ─── Sections ─────────────────────────────────────────────────────────── */
.audit-section {
  margin-bottom: 1rem;
}

.audit-section-label {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6c757d;
  margin-bottom: 0.3rem;
}

.audit-section-value {
  font-size: 0.875rem;
  color: #212529;
}

/* ─── Diff Section ─────────────────────────────────────────────────────── */
.audit-diff-section {
  margin-bottom: 1rem;
}

.audit-diff-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.audit-diff-box {
  border-radius: 8px;
  border: 1px solid #e9ecef;
  overflow: hidden;
}

.audit-diff-old {
  border-color: #f5c6cb;
  background: #fff5f5;
}

.audit-diff-new {
  border-color: #c3e6cb;
  background: #f5fff7;
}

.audit-diff-title {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid;
}

.audit-diff-old .audit-diff-title {
  color: #842029;
  background: #f8d7da;
  border-color: #f5c6cb;
}

.audit-diff-new .audit-diff-title {
  color: #155724;
  background: #d4edda;
  border-color: #c3e6cb;
}

.audit-diff-rows {
  padding: 0.5rem;
}

.audit-diff-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.3rem 0.25rem;
  border-radius: 4px;
  font-size: 0.8rem;
  border-bottom: 1px solid rgba(0,0,0,0.04);
}

.audit-diff-row:last-child { border-bottom: none; }

/* Highlight baris yang berubah */
.audit-diff-old .audit-diff-row.audit-diff-changed {
  background: rgba(220, 53, 69, 0.08);
}
.audit-diff-new .audit-diff-row.audit-diff-changed {
  background: rgba(25, 135, 84, 0.08);
}

.audit-diff-key {
  color: #6c757d;
  font-weight: 600;
  white-space: nowrap;
  min-width: 80px;
}

.audit-diff-val {
  color: #212529;
  text-align: right;
  word-break: break-word;
}

.audit-no-diff {
  font-size: 0.85rem;
  color: #6c757d;
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
}

/* ─── Footer ───────────────────────────────────────────────────────────── */
.audit-modal-footer {
  padding: 0.75rem 1.25rem;
  border-top: 1px solid #e9ecef;
  background: #f8f9fa;
  border-radius: 0 0 12px 12px;
  display: flex;
  justify-content: flex-end;
  flex-shrink: 0;
}
</style>