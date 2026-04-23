<template>
  <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
  <div v-else-if="mr">
    <!-- Header -->
    <div class="d-flex align-items-start justify-content-between mb-3">
      <div>
        <button class="btn btn-sm btn-outline-secondary mb-2" @click="$router.back()">
          <i class="bi bi-arrow-left me-1"></i>Kembali
        </button>
        <h5 class="fw-bold mb-1" style="color:#1a3a5c;">{{ mr.mr_number }}</h5>
        <div class="d-flex gap-2 align-items-center">
          <span :class="'badge badge-'+mr.status">{{ statusLabel(mr.status) }}</span>
          <small class="text-muted">Dibuat: {{ $formatDate(mr.created_at) }}</small>
        </div>
      </div>
      <div class="d-flex gap-2">
        <!-- Action buttons based on status -->
        <button v-if="mr.status==='draft' && canSubmit" class="btn btn-primary btn-sm" @click="doSubmit" :disabled="acting">
          <i class="bi bi-send me-1"></i>Submit
        </button>
        <template v-if="mr.status==='submitted' && can('approve-mr')">
          <button class="btn btn-success btn-sm" @click="openApprove" :disabled="acting">
            <i class="bi bi-check-circle me-1"></i>Approve
          </button>
          <button class="btn btn-danger btn-sm" @click="openReject" :disabled="acting">
            <i class="bi bi-x-circle me-1"></i>Tolak
          </button>
        </template>
        <button v-if="mr.status==='approved' && can('dispatch-mr')" class="btn btn-warning btn-sm" @click="openDispatch" :disabled="acting">
          <i class="bi bi-truck me-1"></i>Kirim Barang
        </button>
      </div>
    </div>

    <div class="row g-3">
      <!-- Info -->
      <div class="col-md-4">
        <div class="csm-card mb-3">
          <div class="csm-card-header"><h6><i class="bi bi-info-circle me-2"></i>Informasi Transfer</h6></div>
          <div class="csm-card-body">
            <dl class="row mb-0" style="font-size:0.875rem;">
              <dt class="col-5 text-muted">Dari</dt>
              <dd class="col-7 fw-semibold">{{ mr.from_warehouse?.name }}</dd>
              <dt class="col-5 text-muted">Ke (HO)</dt>
              <dd class="col-7 fw-semibold">{{ mr.to_warehouse?.name }}</dd>
              <dt class="col-5 text-muted">Dibuat oleh</dt>
              <dd class="col-7">{{ mr.requester?.name }}</dd>
              <dt class="col-5 text-muted">Tgl Butuh</dt>
              <dd class="col-7">{{ mr.needed_date ? $formatDate(mr.needed_date) : '-' }}</dd>
              <dt class="col-5 text-muted" v-if="mr.approved_by">Diapprove</dt>
              <dd class="col-7" v-if="mr.approved_by">{{ mr.approver?.name }}</dd>
              <dt class="col-5 text-muted" v-if="mr.notes">Catatan</dt>
              <dd class="col-7" v-if="mr.notes">{{ mr.notes }}</dd>
              <dt class="col-5 text-muted" v-if="mr.rejection_reason">Alasan Tolak</dt>
              <dd class="col-7 text-danger" v-if="mr.rejection_reason">{{ mr.rejection_reason }}</dd>
            </dl>
          </div>
        </div>

        <!-- Timeline -->
        <div class="csm-card">
          <div class="csm-card-header"><h6><i class="bi bi-clock-history me-2"></i>Status Timeline</h6></div>
          <div class="csm-card-body">
            <div class="csm-timeline">
              <div v-for="step in timeline" :key="step.key" class="csm-timeline-item">
                <div :class="['csm-timeline-dot', step.status]">
                  <i :class="step.icon"></i>
                </div>
                <div>
                  <div class="fw-semibold small">{{ step.label }}</div>
                  <small class="text-muted">{{ step.date ? $formatDate(step.date) : (step.status==='pending' ? 'Menunggu' : '-') }}</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="col-md-8">
        <div class="csm-card mb-3">
          <div class="csm-card-header">
            <h6><i class="bi bi-list-check me-2"></i>Daftar Barang ({{ mr.items?.length }} item)</h6>
          </div>
          <div class="csm-card-body p-0">
            <div class="table-responsive">
              <table class="table csm-table mb-0">
                <thead>
                  <tr>
                    <th>Barang</th>
                    <th class="text-end">Diminta</th>
                    <th class="text-end" v-if="mr.status !== 'draft' && mr.status !== 'submitted'">Disetujui</th>
                    <th class="text-end" v-if="mr.status === 'dispatched' || mr.status === 'received'">Dikirim</th>
                    <th class="text-end" v-if="mr.status === 'received'">Diterima</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in mr.items" :key="item.id">
                    <td>
                      <div class="fw-semibold small">{{ item.item?.name }}</div>
                      <small class="text-muted">{{ item.item?.part_number }} · {{ item.item?.unit }}</small>
                    </td>
                    <td class="text-end">{{ $formatNumber(item.qty_request) }}</td>
                    <td class="text-end" v-if="mr.status !== 'draft' && mr.status !== 'submitted'">
                      <span :class="parseFloat(item.qty_approved) < parseFloat(item.qty_request) ? 'text-warning fw-bold' : 'fw-bold'">
                        {{ $formatNumber(item.qty_approved) }}
                      </span>
                    </td>
                    <td class="text-end" v-if="mr.status === 'dispatched' || mr.status === 'received'">{{ $formatNumber(item.qty_sent) }}</td>
                    <td class="text-end" v-if="mr.status === 'received'">{{ $formatNumber(item.qty_received) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Delivery Orders -->
        <div class="csm-card" v-if="mr.delivery_orders?.length > 0">
          <div class="csm-card-header"><h6><i class="bi bi-truck me-2"></i>Surat Jalan / DO</h6></div>
          <div class="csm-card-body p-0">
            <table class="table csm-table mb-0">
              <thead><tr><th>No DO</th><th>Pengirim</th><th>Status</th><th>Tgl Kirim</th><th>Tgl Terima</th></tr></thead>
              <tbody>
                <tr v-for="do_ in mr.delivery_orders" :key="do_.id">
                  <td class="fw-semibold text-primary">{{ do_.do_number }}</td>
                  <td><small>{{ do_.driver_name || '-' }} {{ do_.vehicle_plate ? '('+do_.vehicle_plate+')' : '' }}</small></td>
                  <td><span :class="do_.status==='received'?'badge bg-success':'badge bg-warning text-dark'">{{ do_.status }}</span></td>
                  <td><small>{{ do_.sent_at ? $formatDate(do_.sent_at) : '-' }}</small></td>
                  <td><small>{{ do_.received_at ? $formatDate(do_.received_at) : '-' }}</small></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Approve Transfer Barang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <p class="text-muted small mb-3">Konfirmasi jumlah yang disetujui untuk setiap barang:</p>
            <div class="table-responsive">
              <table class="table csm-table">
                <thead><tr><th>Barang</th><th class="text-end">Diminta</th><th class="text-end" style="width:150px;">Disetujui</th></tr></thead>
                <tbody>
                  <tr v-for="item in approveItems" :key="item.id">
                    <td>
                      <div class="fw-semibold small">{{ item.item?.name }}</div>
                      <small class="text-muted">{{ item.item?.part_number }}</small>
                    </td>
                    <td class="text-end">{{ $formatNumber(item.qty_request) }} {{ item.item?.unit }}</td>
                    <td><input v-model.number="item.qty_approved" type="number" min="0" :max="item.qty_request" step="0.01" class="form-control form-control-sm text-end" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-success btn-sm" @click="doApprove" :disabled="acting">
              <span v-if="acting"><span class="csm-spinner me-1"></span></span>Approve Transfer
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title text-danger">Tolak Transfer Barang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <label class="form-label fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea v-model="rejectReason" class="form-control" rows="3" placeholder="Masukkan alasan penolakan..."></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-danger btn-sm" @click="doReject" :disabled="acting || !rejectReason">Tolak Transfer</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Dispatch Modal -->
    <div class="modal fade" id="dispatchModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Kirim Barang - Buat Surat Jalan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label fw-semibold small">Nama Pengemudi</label>
                <input v-model="dispatchForm.driver_name" class="form-control form-control-sm" />
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold small">Plat Kendaraan</label>
                <input v-model="dispatchForm.vehicle_plate" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="dispatchForm.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-warning btn-sm" @click="doDispatch" :disabled="acting">
              <span v-if="acting"><span class="csm-spinner me-1"></span></span><i class="bi bi-truck me-1"></i>Proses Pengiriman
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'

const route = useRoute(); const router = useRouter()
const auth = useAuthStore(); const toast = useToast()
const { listenMR, stopMR } = useRealtime()
const can = (p) => auth.hasPermission(p)

const mr = ref(null); const loading = ref(true); const acting = ref(false)
const approveItems = ref([]); const rejectReason = ref(''); const dispatchForm = ref({ driver_name: '', vehicle_plate: '', notes: '' })

let approveModal = null; let rejectModal = null; let dispatchModal = null

const canSubmit = computed(() => mr.value?.requested_by === auth.user?.id || auth.isSuperuser || auth.isAdminHO)

const timeline = computed(() => {
  if (!mr.value) return []
  const steps = [
    { key: 'draft', label: 'Draft Dibuat', icon: 'bi bi-file-earmark', date: mr.value.created_at },
    { key: 'submitted', label: 'Disubmit', icon: 'bi bi-send', date: mr.value.submitted_at },
    { key: 'approved', label: 'Disetujui', icon: 'bi bi-check-circle', date: mr.value.approved_at },
    { key: 'dispatched', label: 'Dikirim', icon: 'bi bi-truck', date: mr.value.dispatched_at },
    { key: 'received', label: 'Diterima', icon: 'bi bi-box-seam', date: mr.value.received_at },
  ]
  const statusOrder = ['draft','submitted','approved','dispatched','received']
  const currentIdx = statusOrder.indexOf(mr.value.status)
  return steps.map((s, i) => ({
    ...s,
    status: i < currentIdx ? 'done' : i === currentIdx ? 'active' : 'pending'
  }))
})

onMounted(async () => {
  const approveEl = document.getElementById('approveModal')
  const rejectEl = document.getElementById('rejectModal')
  const dispatchEl = document.getElementById('dispatchModal')
  if (approveEl) approveModal = new Modal(approveEl)
  if (rejectEl) rejectModal = new Modal(rejectEl)
  if (dispatchEl) dispatchModal = new Modal(dispatchEl)
  await loadMR()
  listenMR(() => loadMR())
})
onUnmounted(() => stopMR())

async function loadMR() {
  loading.value = true
  try {
    const r = await axios.get(`/material-requests/${route.params.id}`)
    mr.value = r.data.data
  } finally { loading.value = false }
}

function statusLabel(s) {
  const map = { draft:'Draft', submitted:'Submitted', approved:'Disetujui', dispatched:'Dikirim', received:'Diterima', rejected:'Ditolak', cancelled:'Dibatalkan' }
  return map[s] || s
}

async function doSubmit() {
  acting.value = true
  try {
    await axios.post(`/material-requests/${mr.value.id}/submit`)
    toast.success('Transfer berhasil disubmit')
    loadMR()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

function openApprove() {
  approveItems.value = mr.value.items.map(i => ({ ...i, qty_approved: parseFloat(i.qty_request) }))
  approveModal.show()
}

async function doApprove() {
  acting.value = true
  try {
    await axios.post(`/material-requests/${mr.value.id}/approve`, { items: approveItems.value.map(i => ({ id: i.id, qty_approved: i.qty_approved })) })
    toast.success('Transfer berhasil diapprove')
    approveModal.hide(); loadMR()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

function openReject() { rejectReason.value = ''; rejectModal.show() }
async function doReject() {
  acting.value = true
  try {
    await axios.post(`/material-requests/${mr.value.id}/reject`, { reason: rejectReason.value })
    toast.success('Transfer ditolak'); rejectModal.hide(); loadMR()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

function openDispatch() { dispatchForm.value = { driver_name: '', vehicle_plate: '', notes: '' }; dispatchModal.show() }
async function doDispatch() {
  acting.value = true
  try {
    await axios.post(`/material-requests/${mr.value.id}/dispatch`, dispatchForm.value)
    toast.success('Barang berhasil dikirim, Surat Jalan dibuat'); dispatchModal.hide(); loadMR()
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}
</script>