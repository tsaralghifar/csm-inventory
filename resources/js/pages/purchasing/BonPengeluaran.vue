<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Bon Pengeluaran</h5>
        <small class="text-muted">Pengeluaran barang dari stok gudang</small>
      </div>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. Bon..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="pending_confirmation">Menunggu Konfirmasi Mekanik</option>
              <option value="confirmed">Dikonfirmasi Mekanik</option>
              <option value="rejected_by_mechanic">Ditolak Mekanik</option>
              <option value="issued">Sudah Dikeluarkan</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-2">
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
                <th>No. Bon</th>
                <th>No. MR / PM</th>
                <th>Gudang</th>
                <th>Item</th>
                <th>Diterima Oleh / Mekanik</th>
                <th>Tgl. Keluar</th>
                <th>Status</th>
                <th>Dibuat Oleh</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="9" class="text-center text-muted py-5">Belum ada Bon Pengeluaran</td>
              </tr>
              <tr v-for="bon in list" :key="bon.id">
                <td class="fw-semibold text-success">{{ bon.bon_number }}</td>
                <td><small class="text-muted">{{ bon.material_request?.mr_number || bon.permintaan_material?.pm_number || '-' }}</small></td>
                <td><small>{{ bon.warehouse?.name }}</small></td>
                <td><span class="badge bg-secondary rounded-pill">{{ bon.items_count }} item</span></td>
                <td><small>{{ bon.received_by || '-' }}</small></td>
                <td><small class="text-muted">{{ bon.issue_date ? $formatDate(bon.issue_date) : '-' }}</small></td>
                <td>
                  <span class="badge" :class="statusBadgeClass(bon.status)">
                    {{ statusLabel(bon.status) }}
                  </span>
                </td>
                <td><small>{{ bon.creator?.name }}</small></td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="Detail" @click="openDetail(bon)">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-danger" title="Print PDF" @click="printBonDirect(bon)">
                      <i class="bi bi-printer"></i>
                    </button>
                    <!-- Edit item (hanya draft atau rejected) -->
                    <button v-if="['draft','rejected_by_mechanic'].includes(bon.status) && can('issue-bon')"
                      class="btn btn-outline-secondary" title="Edit Item" @click="openEditItems(bon)">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <!-- Minta konfirmasi mekanik -->
                    <button v-if="bon.status === 'draft' && can('issue-bon')"
                      class="btn btn-outline-warning" title="Minta Konfirmasi Mekanik" @click="doRequestConfirmation(bon)">
                      <i class="bi bi-person-check"></i>
                    </button>
                    <!-- Keluarkan barang -->
                    <button v-if="bon.status === 'confirmed' && can('issue-bon')"
                      class="btn btn-outline-success" title="Keluarkan Barang" @click="doIssue(bon)">
                      <i class="bi bi-box-arrow-right"></i>
                    </button>
                    <!-- Kembalikan ke draft jika ditolak -->
                    <button v-if="bon.status === 'rejected_by_mechanic' && can('issue-bon')"
                      class="btn btn-outline-warning" title="Kembalikan ke Draft" @click="doRevise(bon)">
                      <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">Total {{ meta.total }} bon pengeluaran</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         Modal Detail Bon
    ════════════════════════════════════════════════ -->
    <div class="modal fade" id="modalDetailBon" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedBon">
          <div class="modal-header">
            <h6 class="modal-title text-success"><i class="bi bi-box-arrow-right me-2"></i>{{ selectedBon.bon_number }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted w-40">No. Bon</td><td class="fw-semibold">{{ selectedBon.bon_number }}</td></tr>
                  <tr><td class="text-muted">No. MR / PM</td><td>{{ selectedBon.material_request?.mr_number || selectedBon.permintaan_material?.pm_number || '-' }}</td></tr>
                  <tr><td class="text-muted">Gudang</td><td>{{ selectedBon.warehouse?.name }}</td></tr>
                  <tr><td class="text-muted">No. PO / WO</td><td>{{ selectedBon.po_number || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted w-40">Status</td>
                    <td><span class="badge" :class="statusBadgeClass(selectedBon.status)">{{ statusLabel(selectedBon.status) }}</span></td>
                  </tr>
                  <tr><td class="text-muted">Diterima Oleh</td><td>{{ selectedBon.received_by || '-' }}</td></tr>
                  <tr><td class="text-muted">Mekanik</td><td>{{ selectedBon.mechanic || '-' }}</td></tr>
                  <tr><td class="text-muted">Tgl. Keluar</td><td>{{ selectedBon.issue_date ? $formatDate(selectedBon.issue_date) : '-' }}</td></tr>
                  <tr><td class="text-muted">Kode Unit</td><td>{{ selectedBon.unit_code || '-' }}</td></tr>
                  <tr><td class="text-muted">Tipe Unit</td><td>{{ selectedBon.unit_type || '-' }}</td></tr>
                  <tr><td class="text-muted">HM / KM</td><td>{{ selectedBon.hm_km || '-' }}</td></tr>
                  <tr v-if="selectedBon.confirmed_by"><td class="text-muted">Dikonfirmasi Oleh</td><td class="fw-semibold text-success">{{ selectedBon.confirmed_by }}</td></tr>
                  <tr v-if="selectedBon.confirmed_at"><td class="text-muted">Waktu Konfirmasi</td><td>{{ $formatDate(selectedBon.confirmed_at) }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Alert penolakan -->
            <div v-if="selectedBon.status === 'rejected_by_mechanic'" class="alert alert-danger small py-2 mb-3">
              <i class="bi bi-x-circle me-1"></i>
              <strong>Ditolak oleh mekanik:</strong> {{ selectedBon.rejection_reason || '-' }}
            </div>

            <!-- Header tabel item + tombol edit -->
            <div class="d-flex align-items-center justify-content-between mb-1">
              <span class="small fw-semibold text-secondary">Daftar Barang ({{ selectedBon.items?.length || 0 }} item)</span>
              <button
                v-if="['draft','rejected_by_mechanic'].includes(selectedBon.status) && can('issue-bon')"
                class="btn btn-outline-secondary btn-sm py-0"
                @click="openEditItemsFromModal">
                <i class="bi bi-pencil me-1"></i>Edit Item
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-sm csm-table mb-0">
                <thead><tr><th>#</th><th>Nama Barang</th><th class="text-end">Qty</th><th>Satuan</th><th>Keterangan</th></tr></thead>
                <tbody>
                  <tr v-for="(item, idx) in selectedBon.items" :key="item.id">
                    <td class="text-muted">{{ idx+1 }}</td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td class="text-end fw-bold">{{ item.qty }}</td>
                    <td>{{ item.satuan }}</td>
                    <td><small class="text-muted">{{ item.keterangan || '-' }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Info status -->
            <div v-if="selectedBon.status === 'draft'" class="alert alert-warning mt-3 small py-2 mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Pastikan semua item sudah benar, lalu klik <strong>Minta Konfirmasi Mekanik</strong>.
            </div>
            <div v-else-if="selectedBon.status === 'pending_confirmation'" class="alert alert-info mt-3 small py-2 mb-0">
              <i class="bi bi-hourglass-split me-1"></i>
              Menunggu konfirmasi dari mekanik <strong>{{ selectedBon.mechanic || '-' }}</strong>.
            </div>
            <div v-else-if="selectedBon.status === 'confirmed'" class="alert alert-success mt-3 small py-2 mb-0">
              <i class="bi bi-check-circle me-1"></i>
              Dikonfirmasi oleh <strong>{{ selectedBon.confirmed_by }}</strong>. Klik <strong>Keluarkan Barang</strong>.
            </div>
            <div v-else-if="selectedBon.status === 'rejected_by_mechanic'" class="alert alert-danger mt-3 small py-2 mb-0">
              <i class="bi bi-pencil me-1"></i>
              Klik <strong>Edit Item</strong> untuk memperbaiki, lalu minta konfirmasi ulang ke mekanik.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button type="button" class="btn btn-outline-danger btn-sm" @click="printBon(selectedBon)">
              <i class="bi bi-printer me-1"></i>Print PDF
            </button>
            <button v-if="selectedBon.status === 'draft' && can('issue-bon')"
              type="button" class="btn btn-warning btn-sm" @click="doRequestConfirmationFromModal" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-person-check me-1"></i>Minta Konfirmasi Mekanik
            </button>
            <button v-if="selectedBon.status === 'pending_confirmation' && can('issue-bon')"
              type="button" class="btn btn-outline-success btn-sm" @click="openModalKonfirmasiMekanik" :disabled="acting">
              <i class="bi bi-person-check me-1"></i>Input Konfirmasi Mekanik
            </button>
            <button v-if="selectedBon.status === 'confirmed' && can('issue-bon')"
              type="button" class="btn btn-success btn-sm" @click="doIssueFromModal" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-box-arrow-right me-1"></i>Keluarkan Barang
            </button>
            <button v-if="selectedBon.status === 'rejected_by_mechanic' && can('issue-bon')"
              type="button" class="btn btn-outline-warning btn-sm" @click="doReviseFromModal" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-arrow-counterclockwise me-1"></i>Kembalikan ke Draft
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         Modal Edit Item Bon
         Aktif hanya saat: draft / rejected_by_mechanic
         Fitur: ubah qty, ganti barang, hapus, tambah
    ════════════════════════════════════════════════ -->
    <div class="modal fade" id="modalEditItems" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content" v-if="editBon">
          <div class="modal-header">
            <h6 class="modal-title">
              <i class="bi bi-pencil-square me-2 text-warning"></i>
              Edit Item — <span class="text-success fw-bold">{{ editBon.bon_number }}</span>
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">

            <div class="alert alert-warning small py-2 mb-3">
              <i class="bi bi-info-circle me-1"></i>
              Anda dapat <strong>mengubah qty</strong>, <strong>mengganti barang</strong>, <strong>menghapus</strong>, atau <strong>menambah item baru</strong>.
              Setelah disimpan, bon perlu dikonfirmasi ulang oleh mekanik.
            </div>

            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width:36%">Barang</th>
                  <th class="text-center" style="width:16%">Stok Tersedia</th>
                  <th class="text-center" style="width:16%">Jumlah Keluar</th>
                  <th class="text-center" style="width:10%">Satuan</th>
                  <th style="width:17%">Keterangan</th>
                  <th style="width:5%"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in editItems" :key="idx">
                  <!-- Kolom barang dengan autocomplete -->
                  <td class="p-1 position-relative">
                    <input
                      v-model="row.itemSearch"
                      type="text"
                      class="form-control form-control-sm"
                      placeholder="Cari nama / part number..."
                      @input="onEditItemInput(idx)"
                      @focus="row.showDrop = true; onEditItemInput(idx)"
                      @blur="hideEditDrop(idx)"
                      autocomplete="off"
                    />
                    <ul v-if="row.showDrop && row.dropResults.length"
                      class="list-group position-absolute shadow"
                      style="z-index:9999;max-height:200px;overflow-y:auto;top:100%;left:0;right:0">
                      <li v-for="s in row.dropResults" :key="s.item_id"
                        class="list-group-item list-group-item-action py-2 px-3 small"
                        style="cursor:pointer"
                        @mousedown.prevent="selectEditItem(idx, s)">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                          <div>
                            <span class="text-muted me-1 small">{{ s.item?.part_number }}</span>
                            <span class="fw-semibold">{{ s.item?.name }}</span>
                          </div>
                          <span class="badge flex-shrink-0"
                            :class="parseFloat(s.qty) > 0 ? 'bg-success' : 'bg-danger'">
                            Stok: {{ s.qty }} {{ s.item?.unit }}
                          </span>
                        </div>
                      </li>
                    </ul>
                    <!-- Jika item sudah dipilih tapi user clear field, tampilkan nama lama sebagai hint -->
                    <div v-if="!row.itemSearch && row.nama_barang" class="text-muted" style="font-size:0.7rem;margin-top:2px">
                      Sebelumnya: {{ row.nama_barang }}
                    </div>
                  </td>

                  <!-- Stok tersedia -->
                  <td class="text-center p-1">
                    <span v-if="row.loadingStock" class="csm-spinner"></span>
                    <span v-else-if="row.available !== null"
                      :class="parseFloat(row.available) > 0 ? 'fw-semibold text-success' : 'fw-semibold text-danger'">
                      {{ row.available }}
                    </span>
                    <span v-else class="text-muted">-</span>
                  </td>

                  <!-- Qty -->
                  <td class="p-1">
                    <input
                      v-model="row.qty"
                      type="number"
                      class="form-control form-control-sm text-center"
                      min="0.01" step="0.01" placeholder="0"
                      :class="row.available !== null && parseFloat(row.qty) > parseFloat(row.available) ? 'border-danger' : ''"
                    />
                    <div v-if="row.available !== null && parseFloat(row.qty) > parseFloat(row.available)"
                      class="text-danger" style="font-size:0.65rem;margin-top:2px">
                      Melebihi stok ({{ row.available }})
                    </div>
                  </td>

                  <!-- Satuan -->
                  <td class="text-center p-1 small text-muted">{{ row.unit || '-' }}</td>

                  <!-- Keterangan -->
                  <td class="p-1">
                    <input v-model="row.keterangan" type="text" class="form-control form-control-sm"
                      placeholder="Opsional..." />
                  </td>

                  <!-- Hapus -->
                  <td class="text-center p-1">
                    <button class="btn btn-outline-danger btn-sm p-0 px-1"
                      @click="removeEditItem(idx)"
                      :disabled="editItems.length === 1"
                      title="Hapus item ini">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Tambah baris -->
            <button class="btn btn-outline-secondary btn-sm mt-2" @click="addEditItem">
              <i class="bi bi-plus me-1"></i>Tambah Barang
            </button>

            <!-- Ringkasan -->
            <div class="mt-3 small text-muted">
              <i class="bi bi-list-check me-1"></i>
              Total <strong>{{ editItems.length }}</strong> item
              <span v-if="editBon.items?.length !== editItems.length">
                (semula {{ editBon.items?.length }})
              </span>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-primary btn-sm"
              @click="saveEditItems"
              :disabled="acting || !editItemsValid">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-save me-1"></i>Simpan Perubahan
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         Modal Konfirmasi Mekanik (input manual)
    ════════════════════════════════════════════════ -->
    <div class="modal fade" id="modalKonfirmasiMekanik" tabindex="-1">
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-person-check me-1"></i>Konfirmasi Mekanik</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted mb-3">Masukkan nama mekanik yang mengkonfirmasi kesesuaian barang.</p>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Nama Mekanik <span class="text-danger">*</span></label>
              <input v-model="konfirmasiForm.confirmed_by" type="text" class="form-control form-control-sm"
                placeholder="Nama mekanik..." />
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Tindakan</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" v-model="konfirmasiForm.action" value="confirm" id="radioConfirm" />
                  <label class="form-check-label small text-success" for="radioConfirm">✅ Barang Sesuai</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" v-model="konfirmasiForm.action" value="reject" id="radioReject" />
                  <label class="form-check-label small text-danger" for="radioReject">❌ Tolak</label>
                </div>
              </div>
            </div>
            <div v-if="konfirmasiForm.action === 'reject'" class="mb-2">
              <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
              <textarea v-model="konfirmasiForm.reason" class="form-control form-control-sm" rows="3"
                placeholder="Jelaskan alasan penolakan..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-sm"
              :class="konfirmasiForm.action === 'reject' ? 'btn-danger' : 'btn-success'"
              @click="submitKonfirmasiMekanik" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              {{ konfirmasiForm.action === 'reject' ? 'Tolak Barang' : 'Konfirmasi Sesuai' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'

const toast = useToast()
const auth  = useAuthStore()
const { listenBon, stopBon } = useRealtime()
const can = (p) => auth.hasPermission(p)

const list        = ref([])
const loading     = ref(false)
const acting      = ref(false)
const meta        = ref({ total: 0, page: 1, last_page: 1 })
const filters     = ref({ search: '', status: '', date_from: '', date_to: '' })
const selectedBon = ref(null)
const konfirmasiForm = ref({ confirmed_by: '', action: 'confirm', reason: '' })

// Edit item state
const editBon     = ref(null)
const editItems   = ref([])
const warehouseId = ref(null)

let timer = null
const searchTimers = {}

onMounted(() => { loadData(); listenBon(() => loadData()) })
onUnmounted(() => stopBon())

// ─── Status helpers ───────────────────────────────────────────────────────────

function statusLabel(status) {
  return {
    draft:                'Draft',
    pending_confirmation: 'Menunggu Konfirmasi',
    confirmed:            'Dikonfirmasi Mekanik',
    rejected_by_mechanic: 'Ditolak Mekanik',
    issued:               'Sudah Dikeluarkan',
  }[status] ?? status
}

function statusBadgeClass(status) {
  return {
    draft:                'bg-secondary',
    pending_confirmation: 'bg-warning text-dark',
    confirmed:            'bg-primary',
    rejected_by_mechanic: 'bg-danger',
    issued:               'bg-success',
  }[status] ?? 'bg-secondary'
}

// ─── Data loading ─────────────────────────────────────────────────────────────

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/bon-pengeluaran', {
      params: { ...filters.value, page: meta.value.page, per_page: 15 }
    })
    list.value = res.data.data
    meta.value = res.data.meta
  } finally {
    loading.value = false
    window.clearModalBackdrop?.()
  }
}

function debouncedLoad() {
  clearTimeout(timer)
  timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400)
}
function changePage(p) { meta.value.page = p; loadData() }
function resetFilters() {
  filters.value = { search: '', status: '', date_from: '', date_to: '' }
  meta.value.page = 1
  loadData()
}

// ─── Detail modal ─────────────────────────────────────────────────────────────

async function openDetail(bon) {
  try {
    const res = await axios.get(`/bon-pengeluaran/${bon.id}`)
    selectedBon.value = res.data.data
    new Modal('#modalDetailBon').show()
  } catch { toast.error('Gagal memuat detail') }
}

// ─── Edit Items ───────────────────────────────────────────────────────────────

function makeEditRow(item = null) {
  return {
    item_id:      item?.item_id    ?? null,
    nama_barang:  item?.nama_barang ?? '',
    itemSearch:   item
      ? [item.item?.part_number, item.nama_barang].filter(Boolean).join(' - ')
      : '',
    qty:          item?.qty        ?? '',
    unit:         item?.satuan     ?? '',
    satuan:       item?.satuan     ?? '',
    keterangan:   item?.keterangan ?? '',
    available:    null,
    loadingStock: false,
    dropResults:  [],
    showDrop:     false,
  }
}

async function openEditItems(bon) {
  try {
    const res = await axios.get(`/bon-pengeluaran/${bon.id}`)
    editBon.value     = res.data.data
    warehouseId.value = res.data.data.warehouse_id
    editItems.value   = res.data.data.items.map(i => makeEditRow(i))
    // Load stok untuk item yang sudah ada
    editItems.value.forEach((_, idx) => loadAvailable(idx))
    new Modal('#modalEditItems').show()
  } catch { toast.error('Gagal memuat data bon') }
}

async function openEditItemsFromModal() {
  // Tutup detail dulu, lalu buka edit
  const detailEl = document.getElementById('modalDetailBon')
  const detailModal = Modal.getInstance(detailEl)
  detailModal?.hide()
  await new Promise(r => { detailEl.addEventListener('hidden.bs.modal', r, { once: true }) })
  await openEditItems(selectedBon.value)
}

async function loadAvailable(idx) {
  const row = editItems.value[idx]
  if (!row.item_id || !warehouseId.value) return
  row.loadingStock = true
  try {
    const res = await axios.get(`/warehouses/${warehouseId.value}/stocks`, {
      params: { item_id: row.item_id, per_page: 5 }
    })
    const found = (res.data.data || []).find(s => s.item_id === row.item_id)
    row.available = found ? parseFloat(found.qty) : 0
  } catch { row.available = null }
  finally { row.loadingStock = false }
}

function onEditItemInput(idx) {
  const row = editItems.value[idx]
  row.showDrop = true
  clearTimeout(searchTimers[idx])
  searchTimers[idx] = setTimeout(() => doSearchEditItem(idx), 300)
}

async function doSearchEditItem(idx) {
  const row = editItems.value[idx]
  const q = row.itemSearch.trim()
  if (q.length < 2) { row.dropResults = []; return }
  if (!warehouseId.value) return
  try {
    const [itemsRes, stocksRes] = await Promise.all([
      axios.get('/items', { params: { search: q, per_page: 15 } }),
      axios.get(`/warehouses/${warehouseId.value}/stocks`, { params: { search: q, per_page: 15 } }),
    ])
    const stockMap = {}
    for (const s of (stocksRes.data.data || [])) stockMap[s.item_id] = s.qty
    row.dropResults = (itemsRes.data.data || []).map(item => ({
      item_id: item.id,
      item,
      qty: stockMap[item.id] ?? 0,
    }))
  } catch { row.dropResults = [] }
}

function hideEditDrop(idx) {
  setTimeout(() => { editItems.value[idx].showDrop = false }, 200)
}

function selectEditItem(idx, s) {
  const row = editItems.value[idx]
  row.item_id     = s.item_id
  row.nama_barang = s.item?.name || ''
  row.itemSearch  = [s.item?.part_number, s.item?.name].filter(Boolean).join(' - ')
  row.unit        = s.item?.unit || ''
  row.satuan      = s.item?.unit || ''
  row.available   = parseFloat(s.qty)
  row.dropResults = []
  row.showDrop    = false
}

function addEditItem() {
  editItems.value.push(makeEditRow())
}

function removeEditItem(idx) {
  if (editItems.value.length > 1) editItems.value.splice(idx, 1)
}

// Validasi: semua baris harus punya nama_barang dan qty > 0
const editItemsValid = computed(() =>
  editItems.value.length > 0 &&
  editItems.value.every(r => r.nama_barang.trim() && parseFloat(r.qty) > 0)
)

async function saveEditItems() {
  if (!editItemsValid.value) return

  // Peringatan jika ada yang melebihi stok
  const over = editItems.value.find(r =>
    r.available !== null && parseFloat(r.qty) > parseFloat(r.available)
  )
  if (over) {
    if (!confirm(`Qty "${over.nama_barang}" melebihi stok tersedia (${over.available}). Tetap simpan?`)) return
  }

  acting.value = true
  try {
    await axios.put(`/bon-pengeluaran/${editBon.value.id}/items`, {
      items: editItems.value.map(r => ({
        item_id:     r.item_id || null,
        nama_barang: r.nama_barang,
        qty:         parseFloat(r.qty),
        satuan:      r.satuan || r.unit || 'PCS',
        keterangan:  r.keterangan || null,
      }))
    })
    toast.success('Item bon berhasil diperbarui. Silakan minta konfirmasi mekanik kembali.')
    Modal.getInstance(document.getElementById('modalEditItems'))?.hide()
    loadData()
  } catch (e) {
    const errMsg = e.response?.data?.message
      || Object.values(e.response?.data?.errors || {})[0]?.[0]
      || 'Gagal menyimpan perubahan'
    toast.error(errMsg)
  } finally { acting.value = false }
}

// ─── Konfirmasi mekanik ───────────────────────────────────────────────────────

async function doRequestConfirmation(bon) {
  if (!confirm(`Kirim permintaan konfirmasi ke mekanik untuk bon ${bon.bon_number}?`)) return
  try {
    await axios.post(`/bon-pengeluaran/${bon.id}/request-confirmation`)
    toast.success('Permintaan konfirmasi berhasil dikirim ke mekanik')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
}

async function doRequestConfirmationFromModal() {
  acting.value = true
  try {
    await axios.post(`/bon-pengeluaran/${selectedBon.value.id}/request-confirmation`)
    toast.success('Permintaan konfirmasi berhasil dikirim ke mekanik')
    const modalEl = document.getElementById('modalDetailBon')
    modalEl?.addEventListener('hidden.bs.modal', () => loadData(), { once: true })
    Modal.getInstance(modalEl)?.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
  finally { acting.value = false }
}

function openModalKonfirmasiMekanik() {
  konfirmasiForm.value = { confirmed_by: selectedBon.value?.mechanic || '', action: 'confirm', reason: '' }
  new Modal('#modalKonfirmasiMekanik').show()
}

async function submitKonfirmasiMekanik() {
  if (!konfirmasiForm.value.confirmed_by.trim()) { toast.error('Nama mekanik wajib diisi'); return }
  if (konfirmasiForm.value.action === 'reject' && !konfirmasiForm.value.reason.trim()) {
    toast.error('Alasan penolakan wajib diisi'); return
  }
  acting.value = true
  try {
    if (konfirmasiForm.value.action === 'confirm') {
      await axios.post(`/bon-pengeluaran/${selectedBon.value.id}/confirm`, {
        confirmed_by: konfirmasiForm.value.confirmed_by
      })
      toast.success('Barang dikonfirmasi sesuai oleh mekanik')
    } else {
      await axios.post(`/bon-pengeluaran/${selectedBon.value.id}/reject-mechanic`, {
        rejected_by: konfirmasiForm.value.confirmed_by,
        reason:      konfirmasiForm.value.reason,
      })
      toast.warning('Barang ditolak mekanik. Silakan edit item dan minta konfirmasi ulang.')
    }
    Modal.getInstance(document.getElementById('modalKonfirmasiMekanik'))?.hide()
    const detailEl = document.getElementById('modalDetailBon')
    detailEl?.addEventListener('hidden.bs.modal', () => loadData(), { once: true })
    Modal.getInstance(detailEl)?.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
  finally { acting.value = false }
}

// ─── Issue & Revisi ───────────────────────────────────────────────────────────

async function doIssue(bon) {
  if (!confirm(`Keluarkan barang dari bon ${bon.bon_number}? Stok gudang akan berkurang.`)) return
  try {
    await axios.post(`/bon-pengeluaran/${bon.id}/issue`)
    toast.success('Barang berhasil dikeluarkan, stok dikurangi')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
}

async function doIssueFromModal() {
  acting.value = true
  try {
    await axios.post(`/bon-pengeluaran/${selectedBon.value.id}/issue`)
    toast.success('Barang berhasil dikeluarkan, stok dikurangi')
    const modalEl = document.getElementById('modalDetailBon')
    modalEl?.addEventListener('hidden.bs.modal', () => loadData(), { once: true })
    Modal.getInstance(modalEl)?.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
  finally { acting.value = false }
}

async function doRevise(bon) {
  if (!confirm(`Kembalikan bon ${bon.bon_number} ke draft untuk direvisi?`)) return
  try {
    await axios.post(`/bon-pengeluaran/${bon.id}/revise`)
    toast.success('Bon dikembalikan ke draft')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
}

async function doReviseFromModal() {
  acting.value = true
  try {
    await axios.post(`/bon-pengeluaran/${selectedBon.value.id}/revise`)
    toast.success('Bon dikembalikan ke draft')
    const modalEl = document.getElementById('modalDetailBon')
    modalEl?.addEventListener('hidden.bs.modal', () => loadData(), { once: true })
    Modal.getInstance(modalEl)?.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
  finally { acting.value = false }
}

// ─── Print ────────────────────────────────────────────────────────────────────

async function printBonDirect(bon) {
  try {
    const res = await axios.get(`/bon-pengeluaran/${bon.id}`)
    printBon(res.data.data)
  } catch { toast.error('Gagal memuat data bon') }
}

function printBon(bon) {
  const fmtD = (v) => v ? new Date(v).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'}) : '-'
  const sLabel = statusLabel(bon.status)
  const sColor = {
    draft:'#6b7280', pending_confirmation:'#d97706', confirmed:'#2563eb',
    rejected_by_mechanic:'#dc2626', issued:'#16a34a'
  }[bon.status] ?? '#6b7280'
  const mrNum = bon.material_request?.mr_number || bon.permintaan_material?.pm_number || '-'

  const rows = (bon.items||[]).map((item,i) =>
    '<tr style="background:'+(i%2?'#f8fafc':'#fff')+'">'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;color:#64748b">'+(i+1)+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 10px;font-weight:600">'+(item.nama_barang||'-')+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;font-weight:700;color:#1a3a5c">'+item.qty+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px">'+(item.satuan||'-')+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 10px;color:#64748b">'+(item.keterangan||'-')+'</td>'+
    '</tr>'
  ).join('')

  const konfirmasiRow = bon.confirmed_by
    ? '<div class="irow"><span class="ilbl">Dikonfirmasi Oleh</span><span class="ival" style="color:#16a34a">'+(bon.confirmed_by)+'</span></div>'+
      '<div class="irow"><span class="ilbl">Waktu Konfirmasi</span><span class="ival2">'+fmtD(bon.confirmed_at)+'</span></div>'
    : ''

  const penolakanRow = bon.rejection_reason
    ? '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:8px 12px;margin-top:10px;font-size:9pt;color:#991b1b">'+
      '<strong>Ditolak Mekanik:</strong> '+bon.rejection_reason+'</div>'
    : ''

  const css =
    '*{margin:0;padding:0;box-sizing:border-box}body{font-family:Arial,sans-serif;font-size:10pt;color:#1f2937;padding:20px}'+
    '@media print{body{padding:0}@page{margin:15mm 12mm;size:A4}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}}'+
    '.hdr{background:#1a3a5c;color:#fff;padding:14px 20px;border-radius:8px 8px 0 0}.hdr h1{font-size:15pt;font-weight:800}'+
    '.hdr2{background:#2563a8;color:#fff;padding:7px 20px;display:flex;align-items:center;gap:10px}'+
    '.bdg{padding:3px 12px;border-radius:20px;font-size:8pt;font-weight:700;color:#fff}'+
    '.igrid{display:grid;grid-template-columns:1fr 1fr;border:1px solid #e2e8f0;border-top:none}'+
    '.isec{padding:12px 16px}.isec:first-child{border-right:1px solid #e2e8f0}'+
    '.ititle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #e8edf4}'+
    '.irow{display:flex;margin-bottom:4px;font-size:9pt}.ilbl{color:#64748b;width:130px;flex-shrink:0}'+
    '.ival{font-weight:600;color:#1a3a5c}.ival2{color:#374151}'+
    'table.it{width:100%;border-collapse:collapse;margin-top:14px}table.it th{background:#1a3a5c;color:#fff;padding:8px 10px;font-size:9pt;font-weight:700}'+
    '.sgrid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:24px}'+
    '.sbox{border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 12px}'+
    '.stitle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;text-align:center;background:#e8edf4;margin:-8px -12px 8px;padding:6px;border-radius:4px 4px 0 0}'+
    '.sspace{height:45px;border-bottom:1.5px solid #e2e8f0;margin-bottom:6px}'+
    '.sname{font-size:9pt;font-weight:600;color:#1a3a5c;text-align:center}'

  const o='<'
  const html = '<!DOCTYPE html>'+o+'html>'+o+'head>'+o+'meta charset="UTF-8"/>'+o+'title>BON-'+bon.bon_number+o+'/title>'+
    o+'style>'+css+o+'/style>'+o+'/head>'+o+'body>'+
    '<div class="hdr"><h1>PT. CIPTA SARANA MAKMUR</h1></div>'+
    '<div class="hdr2">'+
      '<span style="font-size:11pt;font-weight:700">BON PENGELUARAN BARANG</span>'+
      '<span style="font-size:11pt;font-weight:800;background:#fff;color:#2563a8;padding:2px 12px;border-radius:4px">'+bon.bon_number+'</span>'+
      '<span class="bdg" style="background:'+sColor+'">'+sLabel.toUpperCase()+'</span>'+
    '</div>'+
    '<div class="igrid">'+
      '<div class="isec"><div class="ititle">Informasi Bon</div>'+
        '<div class="irow"><span class="ilbl">No. Bon</span><span class="ival">'+bon.bon_number+'</span></div>'+
        '<div class="irow"><span class="ilbl">No. MR / PM</span><span class="ival2">'+mrNum+'</span></div>'+
        '<div class="irow"><span class="ilbl">Gudang</span><span class="ival">'+(bon.warehouse?.name||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">No. PO / WO</span><span class="ival2">'+(bon.po_number||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Dibuat Oleh</span><span class="ival2">'+(bon.creator?.name||'-')+'</span></div>'+
      '</div>'+
      '<div class="isec"><div class="ititle">Detail Pengeluaran</div>'+
        '<div class="irow"><span class="ilbl">Diterima Oleh</span><span class="ival">'+(bon.received_by||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Mekanik</span><span class="ival2">'+(bon.mechanic||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Tgl. Keluar</span><span class="ival2">'+fmtD(bon.issue_date)+'</span></div>'+
        '<div class="irow"><span class="ilbl">Kode Unit</span><span class="ival2">'+(bon.unit_code||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Tipe Unit</span><span class="ival2">'+(bon.unit_type||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">HM / KM</span><span class="ival2">'+(bon.hm_km||'-')+'</span></div>'+
        konfirmasiRow+
      '</div>'+
    '</div>'+
    penolakanRow+
    '<table class="it"><thead><tr>'+
      '<th style="text-align:center;width:36px">#</th>'+
      '<th style="text-align:left">Nama Barang</th>'+
      '<th style="text-align:center;width:60px">Qty</th>'+
      '<th style="text-align:center;width:70px">Satuan</th>'+
      '<th style="text-align:left">Keterangan</th>'+
    '</tr></thead><tbody>'+rows+'</tbody></table>'+
    '<div class="sgrid">'+
      '<div class="sbox"><div class="stitle">Dibuat Oleh</div><div class="sspace"></div><div class="sname">'+(bon.creator?.name||'')+'</div></div>'+
      '<div class="sbox"><div class="stitle">Dikonfirmasi Mekanik</div><div class="sspace"></div><div class="sname">'+(bon.confirmed_by||bon.mechanic||'')+'</div></div>'+
      '<div class="sbox"><div class="stitle">Dikeluarkan Oleh</div><div class="sspace"></div><div class="sname">'+(bon.approver?.name||'')+'</div></div>'+
    '</div>'+
    o+'/body>'+o+'/html>'

  const win = window.open('', '_blank', 'width=900,height=700')
  win.document.write(html)
  win.document.close()
  win.onload = () => { win.focus(); win.print() }
}
</script>