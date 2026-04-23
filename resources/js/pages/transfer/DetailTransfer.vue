<template>
  <div v-if="loading" class="text-center p-5"><div class="csm-spinner"></div></div>
  <div v-else-if="!mr" class="alert alert-danger">Data tidak ditemukan</div>
  <div v-else>
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <router-link to="/transfer-barang" class="text-muted small text-decoration-none">
          <i class="bi bi-arrow-left me-1"></i>Transfer Barang
        </router-link>
        <h5 class="fw-bold mb-0 mt-1" style="color:#1a3a5c;">{{ mr.mr_number }}</h5>
        <small class="text-muted">Transfer barang antar gudang</small>
      </div>
      <span class="badge fs-6" :class="statusClass(mr.status)">{{ statusLabel(mr.status) }}</span>
    </div>

    <div class="row g-3">
      <!-- Info & Timeline -->
      <div class="col-12">
        <div class="csm-card">
          <div class="csm-card-header"><h6><i class="bi bi-info-circle me-2"></i>Informasi Transfer</h6></div>
          <div class="csm-card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted w-40">No. MR</td><td class="fw-semibold">{{ mr.mr_number }}</td></tr>
                  <tr><td class="text-muted">Gudang Asal</td><td class="fw-semibold">{{ mr.from_warehouse?.name }}</td></tr>
                  <tr><td class="text-muted">Gudang Tujuan</td><td class="fw-semibold">{{ mr.to_warehouse?.name }}</td></tr>
                  <tr><td class="text-muted">Diajukan Oleh</td><td>{{ mr.requester?.name }}</td></tr>
                  <tr><td class="text-muted">Tgl. Dibutuhkan</td><td>{{ mr.needed_date ? $formatDate(mr.needed_date) : '-' }}</td></tr>
                  <tr><td class="text-muted">Catatan</td><td>{{ mr.notes || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <!-- Timeline -->
                <div class="d-flex align-items-start">
                  <!-- Dibuat -->
                  <div class="text-center" style="min-width:65px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="mr.status !== 'draft' ? 'bg-success text-white' : 'bg-secondary text-white'"
                      style="width:36px;height:36px;"><i class="bi bi-send-fill small"></i></div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Dibuat</small>
                    <small class="fw-semibold d-block" style="font-size:0.65rem;">{{ mr.requester?.name }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Admin -->
                  <div class="text-center" style="min-width:65px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="stepClass(['pending_atasan','approved','dispatched','received'], mr.status, 'pending_admin')"
                      style="width:36px;height:36px;"><i class="bi bi-person-check-fill small"></i></div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Admin</small>
                    <small v-if="mr.approver" class="fw-semibold d-block" style="font-size:0.65rem;">{{ mr.approver?.name }}</small>
                    <small v-if="mr.approved_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(mr.approved_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Atasan -->
                  <div class="text-center" style="min-width:65px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="stepClass(['approved','dispatched','received'], mr.status, 'pending_atasan')"
                      style="width:36px;height:36px;"><i class="bi bi-briefcase-fill small"></i></div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Atasan</small>
                    <small v-if="mr.atasanApprover" class="fw-semibold d-block" style="font-size:0.65rem;">{{ mr.atasanApprover?.name }}</small>
                    <small v-if="mr.atasan_approved_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(mr.atasan_approved_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Dikirim -->
                  <div class="text-center" style="min-width:65px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="['dispatched','received'].includes(mr.status) ? 'bg-warning text-dark' : 'bg-light text-muted border'"
                      style="width:36px;height:36px;"><i class="bi bi-truck small"></i></div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Dikirim</small>
                    <small v-if="mr.dispatched_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(mr.dispatched_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Diterima -->
                  <div class="text-center" style="min-width:65px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="mr.status === 'received' ? 'bg-success text-white' : 'bg-light text-muted border'"
                      style="width:36px;height:36px;"><i class="bi bi-box-seam-fill small"></i></div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Diterima</small>
                    <small v-if="mr.received_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(mr.received_at) }}</small>
                  </div>
                </div>

                <!-- Ditolak alert -->
                <div v-if="mr.status === 'rejected'" class="alert alert-danger mt-3 small py-2 mb-0">
                  <i class="bi bi-x-circle me-1"></i>
                  <strong>Ditolak:</strong> {{ mr.rejection_reason }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Daftar Barang -->
      <div class="col-12">
        <div class="csm-card">
          <div class="csm-card-header"><h6><i class="bi bi-list-check me-2"></i>Daftar Barang</h6></div>
          <div class="csm-card-body p-0">
            <div class="table-responsive">
              <table class="table csm-table mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th class="text-end">Diminta</th>
                    <th class="text-end">Disetujui</th>
                    <th class="text-end">Dikirim</th>
                    <th class="text-end">Diterima</th>
                    <th>Satuan</th>
                    <th>Keterangan</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in mr.items" :key="item.id">
                    <td class="text-muted">{{ idx + 1 }}</td>
                    <td class="fw-semibold">{{ item.item?.name }}</td>
                    <td><small class="text-muted">{{ item.item?.category?.name || '-' }}</small></td>
                    <td class="text-end">{{ item.qty_request }}</td>
                    <td class="text-end">
                      <span :class="item.qty_approved > 0 ? 'text-success fw-bold' : 'text-muted'">
                        {{ item.qty_approved > 0 ? item.qty_approved : '-' }}
                      </span>
                    </td>
                    <td class="text-end">
                      <span :class="item.qty_sent > 0 ? 'text-warning fw-bold' : 'text-muted'">
                        {{ item.qty_sent > 0 ? item.qty_sent : '-' }}
                      </span>
                    </td>
                    <td class="text-end">
                      <span :class="item.qty_received > 0 ? 'text-success fw-bold' : 'text-muted'">
                        {{ item.qty_received > 0 ? item.qty_received : '-' }}
                      </span>
                    </td>
                    <td><small>{{ item.item?.unit || '-' }}</small></td>
                    <td><small class="text-muted">{{ item.notes || '-' }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Surat Jalan (Delivery Orders) -->
      <div class="col-12" v-if="mr.delivery_orders?.length">
        <div class="csm-card">
          <div class="csm-card-header"><h6><i class="bi bi-truck me-2"></i>Surat Jalan Pengiriman</h6></div>
          <div class="csm-card-body p-0">
            <div v-for="do_ in mr.delivery_orders" :key="do_.id" class="border-bottom p-3">
              <div class="row g-2 mb-2">
                <div class="col-md-3"><small class="text-muted">No. DO</small><div class="fw-semibold">{{ do_.do_number }}</div></div>
                <div class="col-md-2"><small class="text-muted">Driver</small><div>{{ do_.driver_name || '-' }}</div></div>
                <div class="col-md-2"><small class="text-muted">Kendaraan</small><div>{{ do_.vehicle_plate || '-' }}</div></div>
                <div class="col-md-2"><small class="text-muted">Dikirim</small><div>{{ do_.sent_at ? $formatDate(do_.sent_at) : '-' }}</div></div>
                <div class="col-md-2">
                  <small class="text-muted">Status</small>
                  <div>
                    <span class="badge" :class="do_.status === 'received' ? 'bg-success' : 'bg-warning text-dark'">
                      {{ do_.status === 'received' ? '✓ Diterima' : 'Dikirim' }}
                    </span>
                  </div>
                </div>
                <div class="col-md-1">
                  <small class="text-muted">Penerima</small>
                  <div><small>{{ do_.received_by_name || '-' }}</small></div>
                </div>
              </div>
              <table class="table table-sm csm-table mb-0">
                <thead>
                  <tr>
                    <th>Barang</th>
                    <th class="text-end">Dikirim</th>
                    <th class="text-end">Diterima</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="doItem in do_.items" :key="doItem.id">
                    <td>{{ doItem.item?.name }}</td>
                    <td class="text-end">{{ doItem.qty_sent }}</td>
                    <td class="text-end">
                      <span :class="doItem.qty_received > 0 ? 'text-success fw-bold' : 'text-muted'">
                        {{ doItem.qty_received > 0 ? doItem.qty_received : '-' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
              <!-- Tombol konfirmasi terima per DO -->
              <div v-if="do_.status === 'sent' && canReceive(mr)" class="mt-2 d-flex justify-content-end">
                <button class="btn btn-success btn-sm" @click="openTerima(do_)">
                  <i class="bi bi-box-seam me-1"></i>Konfirmasi Terima Barang
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="col-12">
        <div class="csm-card">
          <div class="csm-card-body">
            <div class="d-flex gap-2 flex-wrap align-items-center">
              <span class="small text-muted me-1">Aksi:</span>

              <!-- Submit -->
              <button v-if="mr.status === 'draft' && can('create-transfer')"
                class="btn btn-info btn-sm" @click="doSubmit" :disabled="acting">
                <i class="bi bi-send me-1"></i>Submit ke Admin
              </button>

              <!-- Approve Admin -->
              <button v-if="mr.status === 'pending_admin' && can('approve-transfer-admin')"
                class="btn btn-success btn-sm" @click="openApproveAdmin" :disabled="acting">
                <i class="bi bi-person-check me-1"></i>Setujui (Admin)
              </button>

              <!-- Approve Atasan -->
              <button v-if="mr.status === 'pending_atasan' && can('approve-transfer-atasan')"
                class="btn btn-success btn-sm" @click="doApproveAtasan" :disabled="acting">
                <i class="bi bi-check-circle me-1"></i>Setujui (Atasan)
              </button>

              <!-- Kirim Barang -->
              <button v-if="mr.status === 'approved' && can('dispatch-transfer')"
                class="btn btn-warning btn-sm" @click="openKirim" :disabled="acting">
                <i class="bi bi-truck me-1"></i>Kirim Barang
              </button>

              <!-- Tolak -->
              <button v-if="['pending_admin','pending_atasan'].includes(mr.status) && (can('approve-transfer-admin') || can('approve-transfer-atasan'))"
                class="btn btn-danger btn-sm" @click="openReject" :disabled="acting">
                <i class="bi bi-x-circle me-1"></i>Tolak
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Approve Admin ===== -->
    <div class="modal fade" id="modalApproveAdminDetail" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-success bg-opacity-10">
            <h6 class="modal-title text-success"><i class="bi bi-person-check me-2"></i>Setujui Transfer (Admin)</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info small py-2 mb-3">
              <i class="bi bi-info-circle me-1"></i>Periksa stok dan sesuaikan jumlah yang disetujui.
            </div>
            <table class="table table-sm csm-table">
              <thead>
                <tr>
                  <th>Barang</th>
                  <th class="text-end">Diminta</th>
                  <th class="text-end">Stok Tersedia</th>
                  <th class="text-end" style="width:130px;">Jumlah Disetujui</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in approveItems" :key="item.id">
                  <td class="fw-semibold">{{ item.item?.name }}</td>
                  <td class="text-end">{{ item.qty_request }}</td>
                  <td class="text-end">
                    <span :class="(item._stok || 0) < item.qty_request ? 'text-danger fw-bold' : 'text-success'">
                      {{ item._stok ?? '...' }}
                    </span>
                  </td>
                  <td>
                    <input v-model="item.qty_approved_input" type="number" class="form-control form-control-sm text-end"
                      min="0" :max="item._stok" step="0.01" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm" @click="doApproveAdmin" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-check-circle me-1"></i>Setujui & Teruskan ke Atasan
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Kirim Barang ===== -->
    <div class="modal fade" id="modalKirimTransfer" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-warning bg-opacity-10">
            <h6 class="modal-title"><i class="bi bi-truck me-2"></i>Kirim Barang</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning small py-2 mb-3">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Stok gudang <strong>{{ mr.from_warehouse?.name }}</strong> akan dikurangi saat barang dikirim.
              Penerima di <strong>{{ mr.to_warehouse?.name }}</strong> perlu konfirmasi untuk stok masuk.
            </div>
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Nama Driver</label>
                <input v-model="kirimForm.driver_name" type="text" class="form-control form-control-sm" placeholder="Nama driver..." />
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">No. Kendaraan</label>
                <input v-model="kirimForm.vehicle_plate" type="text" class="form-control form-control-sm" placeholder="Misal: B 1234 AB" />
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Catatan Pengiriman</label>
                <input v-model="kirimForm.notes" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
              </div>
            </div>
            <label class="form-label small fw-semibold">Barang yang Dikirim</label>
            <table class="table table-sm csm-table">
              <thead>
                <tr>
                  <th>Barang</th>
                  <th class="text-end">Disetujui</th>
                  <th class="text-end" style="width:130px;">Jumlah Dikirim</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in kirimForm.items" :key="item.id">
                  <td class="fw-semibold">{{ item.nama_barang }}</td>
                  <td class="text-end text-muted">{{ item.qty_approved }}</td>
                  <td>
                    <input v-model="item.qty_sent" type="number" class="form-control form-control-sm text-end"
                      min="0.01" :max="item.qty_approved" step="0.01" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-warning btn-sm" @click="doKirim" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-truck me-1"></i>Kirim Sekarang
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Konfirmasi Terima ===== -->
    <div class="modal fade" id="modalTerimaTransfer" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog">
        <div class="modal-content" v-if="terimaTarget">
          <div class="modal-header bg-success bg-opacity-10">
            <h6 class="modal-title text-success"><i class="bi bi-box-seam me-2"></i>Konfirmasi Terima Barang</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-success small py-2 mb-3">
              <i class="bi bi-info-circle me-1"></i>
              Konfirmasi penerimaan barang dari <strong>{{ terimaTarget.do_number }}</strong>.
              Barang akan masuk ke stok <strong>{{ mr.to_warehouse?.name }}</strong>.
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Nama Penerima Barang <span class="text-danger">*</span></label>
              <input v-model="terimaForm.received_by_name" type="text" class="form-control"
                placeholder="Nama lengkap orang yang menerima barang..." />
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Catatan Penerimaan</label>
              <input v-model="terimaForm.notes" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
            </div>
            <label class="form-label small fw-semibold">Jumlah Barang Diterima</label>
            <table class="table table-sm csm-table mb-0">
              <thead>
                <tr>
                  <th>Barang</th>
                  <th class="text-end">Dikirim</th>
                  <th class="text-end" style="width:130px;">Diterima</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in terimaForm.items" :key="item.id">
                  <td class="fw-semibold small">{{ item.item?.name }}</td>
                  <td class="text-end text-muted">{{ item.qty_sent }}</td>
                  <td>
                    <input v-model="item.qty_received" type="number" class="form-control form-control-sm text-end"
                      min="0" :max="item.qty_sent" step="0.01" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm" @click="doTerima"
              :disabled="acting || !terimaForm.received_by_name.trim()">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-check-circle me-1"></i>Konfirmasi Diterima
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Tolak ===== -->
    <div class="modal fade" id="modalRejectDetail" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Tolak Transfer</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea v-model="rejectReason" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..."></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger btn-sm" @click="doReject" :disabled="acting || !rejectReason">
              <span v-if="acting" class="csm-spinner me-1"></span>Tolak
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'

const route = useRoute()
const toast = useToast()
const auth = useAuthStore()
const can = (p) => auth.hasPermission(p)
const { listenTransfer, stopListenTransfer } = useRealtime()

// Hanya user di gudang tujuan, Superuser, atau Admin HO yang bisa konfirmasi terima
function canReceive(mrData) {
  if (auth.isSuperuser || auth.isAdminHO) return true
  return auth.userWarehouseId == mrData?.to_warehouse_id
}

const mr = ref(null)
const loading = ref(true)
const acting = ref(false)
let suppressNextToast = false
const approveItems = ref([])
const kirimForm = ref({ driver_name: '', vehicle_plate: '', notes: '', items: [] })
const terimaTarget = ref(null)
const terimaForm = ref({ received_by_name: '', notes: '', items: [] })
const rejectReason = ref('')

const statusLabel = (s) => ({
  draft: 'Draft',
  pending_admin: 'Menunggu Admin',
  pending_atasan: 'Menunggu Atasan',
  approved: 'Disetujui',
  dispatched: 'Dikirim',
  received: 'Diterima',
  rejected: 'Ditolak',
}[s] || s)

const statusClass = (s) => ({
  draft: 'bg-secondary',
  pending_admin: 'bg-warning text-dark',
  pending_atasan: 'bg-info text-dark',
  approved: 'bg-primary',
  dispatched: 'bg-warning text-dark',
  received: 'bg-success',
  rejected: 'bg-danger',
}[s] || 'bg-secondary')

function stepClass(doneStatuses, current, activeStatus) {
  if (current === 'rejected') return 'bg-danger text-white'
  if (doneStatuses.includes(current)) return 'bg-success text-white'
  if (current === activeStatus) return 'bg-warning text-dark'
  return 'bg-light text-muted border'
}

onMounted(() => {
  loadMR()

  // Update otomatis jika user lain mengubah transfer ini
  listenTransfer((event) => {
    if (mr.value && event.id === mr.value.id) {
      if (!suppressNextToast) {
        toast.info(`🔔 Status diperbarui: ${event.mr_number}`, { timeout: 3000 })
      }
      suppressNextToast = false
      loadMR()
    }
  })
})

onUnmounted(() => {
  stopListenTransfer()
  // Bersihkan semua modal & backdrop saat meninggalkan halaman
  document.querySelectorAll('.modal.show').forEach(el => {
    Modal.getInstance(el)?.hide()
  })
  window.clearModalBackdrop()
})

async function loadMR() {
  loading.value = true
  try {
    const res = await axios.get(`/transfer-barang/${route.params.id}`)
    mr.value = res.data.data
  } catch { toast.error('Data tidak ditemukan') } finally {
    loading.value = false
    // Pastikan tidak ada backdrop yang tersisa setelah reload
    window.clearModalBackdrop()
  }
}

// ── Submit ──
async function doSubmit() {
  if (!confirm('Submit MR ke Admin untuk disetujui?')) return
  acting.value = true
  try {
    await axios.post(`/transfer-barang/${mr.value.id}/submit`)
    toast.success('MR disubmit ke Admin')
    suppressNextToast = true
    loadMR()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Approve Admin ──
async function openApproveAdmin() {
  try {
    const stockRes = await axios.get(`/warehouses/${mr.value.from_warehouse_id}/stocks`, { params: { per_page: 999 } })
    const stockMap = {}
    ;(stockRes.data.data || []).forEach(s => { stockMap[s.item_id] = parseFloat(s.qty) })

    approveItems.value = mr.value.items.map(i => ({
      ...i,
      _stok: stockMap[i.item_id] ?? 0,
      qty_approved_input: i.qty_request,
    }))
  } catch { approveItems.value = mr.value.items.map(i => ({ ...i, _stok: 0, qty_approved_input: i.qty_request })) }
  new Modal('#modalApproveAdminDetail').show()
}

async function doApproveAdmin() {
  acting.value = true
  try {
    await axios.post(`/transfer-barang/${mr.value.id}/approve-admin`, {
      items: approveItems.value.map(i => ({ id: i.id, qty_approved: i.qty_approved_input })),
    })
    toast.success('Disetujui Admin, diteruskan ke Atasan')
    suppressNextToast = true
    const modalEl = document.getElementById('modalApproveAdminDetail')
    const modalInst = Modal.getInstance(modalEl)
    if (modalInst) {
      modalEl.addEventListener('hidden.bs.modal', () => loadMR(), { once: true })
      modalInst.hide()
    } else {
      loadMR()
    }
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Approve Atasan ──
async function doApproveAtasan() {
  if (!confirm('Setujui MR Transfer ini sebagai Atasan?')) return
  acting.value = true
  try {
    await axios.post(`/transfer-barang/${mr.value.id}/approve-atasan`)
    toast.success('Disetujui Atasan, siap untuk pengiriman')
    suppressNextToast = true
    loadMR()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Kirim Barang ──
function openKirim() {
  kirimForm.value = {
    driver_name: '',
    vehicle_plate: '',
    notes: '',
    items: mr.value.items.map(i => ({
      id: i.id,
      nama_barang: i.item?.name,
      qty_approved: i.qty_approved,
      qty_sent: i.qty_approved,
    })),
  }
  new Modal('#modalKirimTransfer').show()
}

async function doKirim() {
  for (const i of kirimForm.value.items) {
    if (!i.qty_sent || i.qty_sent <= 0) return toast.error('Jumlah pengiriman harus lebih dari 0')
  }
  acting.value = true
  try {
    const res = await axios.post(`/transfer-barang/${mr.value.id}/kirim`, {
      driver_name: kirimForm.value.driver_name,
      vehicle_plate: kirimForm.value.vehicle_plate,
      notes: kirimForm.value.notes,
      items: kirimForm.value.items.map(i => ({ id: i.id, qty_sent: i.qty_sent })),
    })
    toast.success(`Barang dikirim, ${res.data.data?.do_number} dibuat`)
    suppressNextToast = true
    const modalEl = document.getElementById('modalKirimTransfer')
    const modalInst = Modal.getInstance(modalEl)
    if (modalInst) {
      modalEl.addEventListener('hidden.bs.modal', () => loadMR(), { once: true })
      modalInst.hide()
    } else { loadMR() }
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Konfirmasi Terima ──
function openTerima(doData) {
  terimaTarget.value = doData
  terimaForm.value = {
    received_by_name: '',
    notes: '',
    items: doData.items.map(i => ({
      id: i.id,
      item: i.item,
      qty_sent: i.qty_sent,
      qty_received: i.qty_sent, // default sama dengan dikirim
    })),
  }
  new Modal('#modalTerimaTransfer').show()
}

async function doTerima() {
  if (!terimaForm.value.received_by_name.trim()) return toast.error('Nama penerima wajib diisi')
  acting.value = true
  try {
    await axios.post(`/transfer-barang/delivery/${terimaTarget.value.id}/terima`, {
      received_by_name: terimaForm.value.received_by_name.trim(),
      notes: terimaForm.value.notes,
      items: terimaForm.value.items.map(i => ({ id: i.id, qty_received: i.qty_received })),
    })
    toast.success(`Barang berhasil diterima oleh ${terimaForm.value.received_by_name}. Stok ${mr.value.to_warehouse?.name} bertambah.`)
    suppressNextToast = true
    const modalEl = document.getElementById('modalTerimaTransfer')
    const modalInst = Modal.getInstance(modalEl)
    if (modalInst) {
      modalEl.addEventListener('hidden.bs.modal', () => loadMR(), { once: true })
      modalInst.hide()
    } else { loadMR() }
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ── Tolak ──
function openReject() {
  rejectReason.value = ''
  new Modal('#modalRejectDetail').show()
}

async function doReject() {
  acting.value = true
  try {
    await axios.post(`/transfer-barang/${mr.value.id}/reject`, { reason: rejectReason.value })
    toast.success('MR Transfer ditolak')
    suppressNextToast = true
    const modalEl = document.getElementById('modalRejectDetail')
    const modalInst = Modal.getInstance(modalEl)
    if (modalInst) {
      modalEl.addEventListener('hidden.bs.modal', () => loadMR(), { once: true })
      modalInst.hide()
    } else { loadMR() }
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}
</script>