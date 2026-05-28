<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Permintaan Material</h5>
        <small class="text-muted">Permintaan sparepart & perlengkapan office</small>
      </div>
      <button v-if="can('create-pm')" class="btn btn-csm-primary btn-sm" @click="showDrawer = true">
        <i class="bi bi-plus-circle me-1"></i>Buat Permintaan
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-2">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. MR..." @input="debouncedLoad" />
          </div>
          <div class="col-md-2">
            <select v-model="filters.type" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Tipe</option>
              <option value="part">🔧 MR Part</option>
              <option value="office">🏢 MR Office</option>
            </select>
          </div>
          <div class="col-md-3">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="pending_chief">Menunggu Chief Mekanik</option>
              <option value="pending_manager">Menunggu Manager</option>
              <option value="pending_ho">Menunggu Admin HO</option>
              <option value="manager_approved">Disetujui Manager</option>
              <option value="approved">Disetujui HO</option>
              <option value="pending_purchasing">Menunggu Pengajuan PO</option>
              <option value="purchasing">Proses Purchasing</option>
              <option value="partial_ordered">Sebagian PO</option>
              <option value="bon_pengeluaran">Bon Pengeluaran</option>
              <option value="completed">Selesai</option>
              <option value="rejected">Ditolak</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-1">
            <button class="btn btn-outline-secondary btn-sm w-100" @click="resetFilters">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center p-5"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. MR</th>
                <th>Tipe</th>
                <th>Gudang</th>
                <th>Item</th>
                <th>Status</th>
                <th>Diajukan Oleh</th>
                <th>Tanggal</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="8" class="text-center text-muted py-5">Tidak ada data permintaan material</td>
              </tr>
              <tr v-for="pm in list" :key="pm.id">
                <td>
                  <router-link :to="`/permintaan-material/${pm.id}`" class="fw-semibold text-primary text-decoration-none">
                    {{ pm.nomor }}
                  </router-link>
                </td>
                <td>
                  <span class="badge" :class="pm.type === 'part' ? 'bg-primary' : 'bg-info text-dark'">
                    {{ pm.type === 'part' ? '🔧 Part' : '🏢 Office' }}
                  </span>
                </td>
                <td><small>{{ pm.warehouse?.name }}</small></td>
                <td><span class="badge bg-secondary rounded-pill">{{ pm.items_count }} item</span></td>
                <td><span class="badge" :class="statusClass(pm.status)">{{ statusLabel(pm.status) }}</span></td>
                <td><small>{{ pm.requester?.name }}</small></td>
                <td><small class="text-muted">{{ $formatDate(pm.created_at) }}</small></td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <router-link :to="`/permintaan-material/${pm.id}`" class="btn btn-outline-primary" title="Detail">
                      <i class="bi bi-eye"></i>
                    </router-link>
                    <button class="btn btn-outline-danger" title="Print / PDF" @click="printPMDirect(pm)">
                      <i class="bi bi-printer"></i>
                    </button>
                    <button v-if="pm.status === 'draft' && can('create-pm')"
                      class="btn btn-outline-info" title="Submit" @click="doSubmit(pm)">
                      <i class="bi bi-send"></i>
                    </button>
                    <button v-if="pm.status === 'pending_chief' && can('authorize-mr-chief')"
                      class="btn btn-outline-warning" title="Otorisasi Chief Mekanik" @click="doAuthorizeChief(pm)">
                      <i class="bi bi-person-check"></i>
                    </button>
                    <button v-if="pm.status === 'pending_manager' && can('approve-mr-manager')"
                      class="btn btn-outline-success" title="Approve Manager" @click="doApproveManager(pm)">
                      <i class="bi bi-check-circle"></i>
                    </button>
                    <button v-if="pm.status === 'pending_ho' && can('approve-pm-ho')"
                      class="btn btn-outline-success" title="Approve Admin HO" @click="doApproveHO(pm)">
                      <i class="bi bi-check-all"></i>
                    </button>
                    <button v-if="canReject(pm)"
                      class="btn btn-outline-danger" title="Tolak" @click="openReject(pm)">
                      <i class="bi bi-x-circle"></i>
                    </button>
                    <button v-if="pm.status === 'draft' && can('create-pm')"
                      class="btn btn-outline-danger" title="Hapus" @click="doDelete(pm)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">Total {{ meta.total }} permintaan</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Drawer Buat Permintaan (menggantikan modal lama) -->
    <BuatPermintaanDrawer
      v-model="showDrawer"
      :warehouses="warehouses"
      :categories="categories"
      :all-units="allUnits"
      @saved="loadData"
    />

    <!-- Modal Tolak (Vue-native, tanpa Bootstrap Modal JS) -->
    <Teleport to="body">
      <Transition name="fade-modal">
        <div v-if="showRejectModal" class="modal-native-overlay" @click.self="showRejectModal = false">
          <div class="modal-native-box">
            <div class="modal-header">
              <h6 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Tolak Permintaan</h6>
              <button type="button" class="btn-close" @click="showRejectModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="small text-muted mb-2">Permintaan: <strong>{{ selectedPM?.nomor }}</strong></p>
              <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
              <textarea v-model="rejectReason" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..."></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary btn-sm" @click="showRejectModal = false">Batal</button>
              <button type="button" class="btn btn-danger btn-sm" @click="doReject" :disabled="acting || !rejectReason">
                <span v-if="acting" class="csm-spinner me-1"></span>Tolak Permintaan
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import BuatPermintaanDrawer from '@/pages/permintaan/BuatPermintaanDrawer.vue'

const toast = useToast()
const auth = useAuthStore()
const { listenPM, stopPM } = useRealtime()
const can = (p) => auth.hasPermission(p)

const list = ref([])
const warehouses = ref([])
const categories = ref([])
const allUnits = ref([])
const loading = ref(false)
const acting = ref(false)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ search: '', type: '', status: '', date_from: '', date_to: '' })
const selectedPM = ref(null)
const rejectReason = ref('')
const showDrawer = ref(false)
const showRejectModal = ref(false)
let timer = null


const statusLabel = (s) => ({
  draft: 'Draft',
  pending_chief: 'Menunggu Chief Mekanik',
  pending_manager: 'Menunggu Manager',
  pending_ho: 'Menunggu Admin HO',
  manager_approved: 'Disetujui Manager',
  approved: 'Disetujui HO',
  pending_purchasing: 'Menunggu Pengajuan PO',
  purchasing: 'Proses Purchasing',
  partial_ordered: 'Sebagian PO',
  bon_pengeluaran: 'Bon Pengeluaran',
  completed: 'Selesai',
  rejected: 'Ditolak',
}[s] || s)

const statusClass = (s) => ({
  draft: 'bg-secondary',
  pending_chief: 'bg-warning text-dark',
  pending_manager: 'bg-warning text-dark',
  pending_ho: 'bg-info text-dark',
  manager_approved: 'bg-primary',
  approved: 'bg-primary',
  pending_purchasing: 'bg-warning text-dark',
  purchasing: 'bg-info text-dark',
  partial_ordered: 'bg-warning text-dark',
  bon_pengeluaran: 'bg-info text-dark',
  completed: 'bg-success',
  rejected: 'bg-danger',
}[s] || 'bg-secondary')

function canReject(pm) {
  if (pm.status === 'pending_chief' && can('authorize-mr-chief')) return true
  if (pm.status === 'pending_manager' && can('approve-mr-manager')) return true
  if (['pending_ho', 'manager_approved'].includes(pm.status) && can('approve-pm-ho')) return true
  return false
}

onMounted(async () => {
  const [warehousesRes, categoriesRes] = await Promise.all([
    axios.get('/warehouses'),
    axios.get('/categories'),
  ])
  warehouses.value = warehousesRes.data.data
  categories.value = categoriesRes.data.data || categoriesRes.data
  // Load units untuk search kode unit
  try {
    const unitsRes = await axios.get('/units', { params: { per_page: 999 } })
    allUnits.value = unitsRes.data.data || []
  } catch {}
  loadData()
  listenPM(() => loadData())
})
onUnmounted(() => stopPM())

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/permintaan-material', { params: { ...filters.value, page: meta.value.page, per_page: 15 } })
    list.value = res.data.data ?? []
    meta.value = res.data.meta ?? { total: 0, page: 1, last_page: 1 }
  } finally { loading.value = false }
}

function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400) }
function changePage(p) { meta.value.page = p; loadData() }
function resetFilters() { filters.value = { search: '', type: '', status: '', date_from: '', date_to: '' }; meta.value.page = 1; loadData() }


async function doSubmit(pm) {
  const msg = pm.type === 'part' ? `Submit MR Part ${pm.nomor} ke Chief Mekanik?` : `Submit MR Office ${pm.nomor} ke Admin HO?`
  if (!confirm(msg)) return
  try {
    await axios.post(`/permintaan-material/${pm.id}/submit`)
    toast.success('Permintaan berhasil disubmit')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal submit') }
}

async function doAuthorizeChief(pm) {
  if (!confirm(`Otorisasi MR ${pm.nomor} sebagai Chief Mekanik?`)) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.id}/authorize-chief`)
    toast.success('MR diotorisasi, diteruskan ke Manager')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doApproveManager(pm) {
  if (!confirm(`Setujui MR ${pm.nomor} sebagai Manager?`)) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.id}/approve-manager`)
    toast.success('MR disetujui Manager, diteruskan ke Admin HO')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doApproveHO(pm) {
  if (!confirm(`Setujui MR ${pm.nomor} sebagai Admin HO?`)) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.id}/approve-ho`)
    toast.success('MR disetujui Admin HO')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

function openReject(pm) {
  selectedPM.value = pm
  rejectReason.value = ''
  showRejectModal.value = true
}

async function doReject() {
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${selectedPM.value.id}/reject`, { reason: rejectReason.value })
    toast.success('Permintaan ditolak')
    showRejectModal.value = false
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doDelete(pm) {
  if (!confirm(`Hapus permintaan ${pm.nomor}?`)) return
  try {
    await axios.delete(`/permintaan-material/${pm.id}`)
    toast.success('Permintaan dihapus')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menghapus') }
}

// ── Print helpers ────────────────────────────────────────
function fmtRpPM(val) { return 'Rp ' + Number(val || 0).toLocaleString('id-ID') }
function fmtDatePM(val) {
  if (!val) return '-'
  return new Date(val).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

function buildPMHtml(pm) {
  const isPart = pm.type === 'part'
  const statusMap = {
    draft: 'DRAFT', pending_chief: 'MENUNGGU CHIEF MEKANIK',
    pending_manager: 'MENUNGGU MANAGER', pending_ho: 'MENUNGGU ADMIN HO',
    manager_approved: 'DISETUJUI MANAGER', approved: 'DISETUJUI HO',
    pending_purchasing: 'MENUNGGU PENGAJUAN PO',
    purchasing: 'PROSES PURCHASING', partial_ordered: 'SEBAGIAN PO', completed: 'SELESAI', rejected: 'DITOLAK',
  }
  const statusColor = {
    draft: '#6c757d', pending_chief: '#f59e0b', pending_manager: '#f59e0b',
    pending_ho: '#0ea5e9', manager_approved: '#3b82f6', approved: '#3b82f6',
    pending_purchasing: '#f59e0b',
    purchasing: '#0ea5e9', partial_ordered: '#f59e0b', completed: '#16a34a', rejected: '#dc2626',
  }
  const sBg   = statusColor[pm.status] || '#6c757d'
  const sTxt  = statusMap[pm.status] || (pm.status || '').toUpperCase()

  const partHeaders = isPart
    ? `<th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Part Number</th>
       <th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Kode Unit</th>
       <th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Tipe Unit</th>` : ''

  const itemRows = (pm.items || []).map((item, i) => {
    const partCells = isPart
      ? `<td style="font-family:Courier New,monospace;color:#1a3a5c;font-weight:600;text-align:center;border:1px solid #dee2e6;">${item.part_number || item.item?.part_number || '-'}</td>
         <td style="text-align:center;border:1px solid #dee2e6;">${item.kode_unit || '-'}</td>
         <td style="text-align:center;border:1px solid #dee2e6;">${item.tipe_unit || '-'}</td>` : ''
    return `<tr style="${i % 2 === 1 ? 'background:#f8fafc;' : ''}">
      <td style="text-align:center;border:1px solid #dee2e6;">${i + 1}</td>
      ${partCells}
      <td style="font-weight:600;border:1px solid #dee2e6;">${item.nama_barang || '-'}</td>
      <td style="text-align:center;border:1px solid #dee2e6;">${item.qty}</td>
      <td style="text-align:center;border:1px solid #dee2e6;">${item.satuan}</td>
      <td style="border:1px solid #dee2e6;color:#64748b;font-size:9.5pt;">${item.keterangan || '-'}</td>
    </tr>`
  }).join('')

  return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<title>Permintaan Material — ${pm.nomor}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 11px; color: #1a1a2e; background: #fff; }
  .page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm 16mm; }

  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 3px solid #1a3a5c; }
  .company-name { font-size: 20px; font-weight: 800; color: #1a3a5c; letter-spacing: -0.5px; }
  .company-sub  { font-size: 10px; color: #6c757d; margin-top: 3px; font-weight: 500; }
  .doc-block    { text-align: right; }
  .doc-label    { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #6c757d; }
  .doc-number   { font-size: 20px; font-weight: 800; color: #1a3a5c; letter-spacing: -0.5px; }
  .status-pill  { display: inline-block; margin-top: 4px; padding: 3px 10px; border-radius: 20px; font-size: 9px; font-weight: 700; letter-spacing: 1px; color: #fff; background: ${sBg}; }
  .type-pill    { display: inline-block; margin-top: 4px; margin-right: 4px; padding: 3px 10px; border-radius: 20px; font-size: 9px; font-weight: 700; color: #fff; background: ${isPart ? '#2563a8' : '#0891b2'}; }

  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 18px; border: 1.5px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
  .info-section { padding: 11px 14px; }
  .info-section:first-child { border-right: 1.5px solid #e2e8f0; }
  .info-title { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; margin-bottom: 8px; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 10.5px; }
  .info-label { color: #64748b; font-weight: 500; min-width: 100px; }
  .info-value { font-weight: 600; color: #1a1a2e; text-align: right; }
  .info-value.hi { color: #1a3a5c; }

  table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
  thead th { padding: 8px; color: #fff; background: #1a3a5c; font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; border: 1px solid #1a3a5c; }
  td { padding: 7px 8px; vertical-align: middle; }

  .sign-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; margin-top: 28px; }
  .sign-box { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
  .sign-label { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 40px; }
  .sign-line { border-top: 1.5px solid #cbd5e1; padding-top: 6px; font-size: 10px; font-weight: 600; color: #475569; min-height: 22px; }

  .notes-box { margin-top: 16px; padding: 9px 12px; background: #f8fafc; border-left: 3px solid #1a3a5c; border-radius: 0 6px 6px 0; font-size: 9.5px; color: #64748b; }
  @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div>
      <div class="company-name">PT. Cipta Sarana Makmur</div>
      <div class="company-sub">CSM Inventory Management System</div>
    </div>
    <div class="doc-block">
      <div class="doc-label">${isPart ? 'Material Request Part' : 'Material Request Office'}</div>
      <div class="doc-number">${pm.nomor}</div>
      <div>
        <span class="type-pill">${isPart ? '🔧 MR Part' : '🏢 MR Office'}</span>
        <span class="status-pill">${sTxt}</span>
      </div>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-section">
      <div class="info-title">Informasi Permintaan</div>
      <div class="info-row"><span class="info-label">No. MR</span><span class="info-value hi">${pm.nomor}</span></div>
      <div class="info-row"><span class="info-label">Gudang / Site</span><span class="info-value hi">${pm.warehouse?.name || '-'}</span></div>
      <div class="info-row"><span class="info-label">Diajukan Oleh</span><span class="info-value">${pm.requester?.name || '-'}</span></div>
      <div class="info-row"><span class="info-label">Tanggal Dibuat</span><span class="info-value">${fmtDatePM(pm.created_at)}</span></div>
    </div>
    <div class="info-section">
      <div class="info-title">Persetujuan</div>
      <div class="info-row"><span class="info-label">Tgl. Dibutuhkan</span><span class="info-value">${pm.needed_date ? fmtDatePM(pm.needed_date) : '-'}</span></div>
      <div class="info-row"><span class="info-label">Chief Mekanik</span><span class="info-value">${pm.chiefAuthorizer?.name || (pm.chief_authorized_at ? '✓' : '-')}</span></div>
      <div class="info-row"><span class="info-label">Manager</span><span class="info-value">${pm.managerApprover?.name || (pm.manager_approved_at ? '✓' : '-')}</span></div>
      <div class="info-row"><span class="info-label">Admin HO</span><span class="info-value">${pm.approver?.name || (pm.approved_at ? '✓' : '-')}</span></div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:30px;text-align:center;">#</th>
        ${partHeaders}
        <th>Nama Barang / Deskripsi</th>
        <th style="width:45px;text-align:center;">Qty</th>
        <th style="width:50px;text-align:center;">Satuan</th>
        <th style="width:120px;">Keterangan</th>
      </tr>
    </thead>
    <tbody>${itemRows}</tbody>
  </table>

  ${pm.notes ? `<div class="notes-box"><strong>Catatan:</strong> ${pm.notes}</div>` : ''}

  <div class="sign-grid">
    <div class="sign-box">
      <div class="sign-label">Ordered by Logistic</div>
      <div class="sign-line"></div>
    </div>
    <div class="sign-box">
      <div class="sign-label">Received by Purchasing</div>
      <div class="sign-line"></div>
    </div>
    <div class="sign-box">
      <div class="sign-label">Authorized by</div>
      <div class="sign-line">${pm.chiefAuthorizer?.name || ''}</div>
    </div>
    <div class="sign-box">
      <div class="sign-label">Approved by</div>
      <div class="sign-line">${pm.approver?.name || pm.managerApprover?.name || ''}</div>
    </div>
  </div>

</div>
</body>
</html>`
}

async function printPMDirect(pm) {
  try {
    const res = await axios.get(`/permintaan-material/${pm.id}`)
    const data = res.data.data
    const html = buildPMHtml(data)
    const win  = window.open('', '_blank', 'width=900,height=700')
    win.document.write(html)
    win.document.close()
    win.onload = () => { win.focus(); win.print() }
  } catch { toast.error('Gagal memuat data PM') }
}

</script>

<style scoped>
.modal-native-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 1055;
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.modal-native-box {
  background: #fff;
  border-radius: 12px;
  width: 100%; max-width: 480px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.fade-modal-enter-active,
.fade-modal-leave-active { transition: opacity .2s ease; }
.fade-modal-enter-from,
.fade-modal-leave-to     { opacity: 0; }
</style>