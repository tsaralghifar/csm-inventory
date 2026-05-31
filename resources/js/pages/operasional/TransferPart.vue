<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Transfer Part Darurat</h5>
        <small class="text-muted">Pencatatan pelepasan part dari unit untuk dikirim ke unit/site lain karena urgent</small>
      </div>
      <button class="btn btn-csm-primary btn-sm" @click="openCreate">
        <i class="bi bi-plus-circle me-1"></i>Buat Transfer Part
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-12 col-md-4">
            <input v-model="search" placeholder="🔍 Cari nomor TP..." class="form-control form-control-sm" @input="debounceFetch" />
          </div>
          <div class="col-6 col-md-3">
            <select v-model="filterStatus" @change="fetchData" class="form-select form-select-sm">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="pending_chief">Menunggu Chief</option>
              <option value="pending_manager">Menunggu Manager</option>
              <option value="approved">Disetujui</option>
              <option value="rejected">Ditolak</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div class="table-responsive">
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>Nomor</th>
                <th>Unit Asal</th>
                <th>Unit Tujuan</th>
                <th>Dibuat Oleh</th>
                <th>Tgl. Dibuat</th>
                <th>Status</th>
                <th>PO Pengganti</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="8" class="text-center py-4 text-muted"><span class="csm-spinner me-2"></span>Memuat...</td>
              </tr>
              <tr v-else-if="!items.length">
                <td colspan="8" class="text-center py-5 text-muted">
                  <i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada data
                </td>
              </tr>
              <tr v-for="item in items" :key="item.id">
                <td>
                  <span class="fw-semibold text-primary font-monospace" style="cursor:pointer" @click="openDetail(item)">
                    {{ item.mr_number }}
                  </span>
                </td>
                <td>
                  <div class="fw-medium">{{ item.unit_from_kode || '—' }}</div>
                  <small class="text-muted">{{ item.unit_from_tipe }}</small>
                </td>
                <td>
                  <div class="fw-medium">{{ item.unit_to_kode || '—' }}</div>
                  <small class="text-muted">{{ item.unit_to_tipe }}</small>
                </td>
                <td>{{ item.requester?.name }}</td>
                <td><small>{{ formatDate(item.created_at) }}</small></td>
                <td>
                  <span class="badge rounded-pill" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
                </td>
                <td>
                  <span v-if="item.linked_po" class="text-success font-monospace small fw-semibold">{{ item.linked_po?.po_number }}</span>
                  <span v-else class="text-muted fst-italic small">Belum ada</span>
                </td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <!-- Detail -->
                    <button @click="openDetail(item)" class="btn btn-outline-primary" title="Detail"><i class="bi bi-eye"></i></button>
                    <!-- Submit (hanya pembuat, status draft) -->
                    <button v-if="item.status === 'draft' && item.requested_by === authStore.user?.id"
                      @click="confirmAction('submit', item)" class="btn btn-outline-secondary" title="Submit ke Chief">
                      <i class="bi bi-send"></i>
                    </button>
                    <!-- Approve Chief -->
                    <button v-if="item.status === 'pending_chief' && canApproveChief"
                      @click="confirmAction('approve-chief', item)" class="btn btn-outline-info" title="Setujui (Chief)">
                      <i class="bi bi-check-circle"></i>
                    </button>
                    <!-- Approve Manager -->
                    <button v-if="item.status === 'pending_manager' && canApproveManager"
                      @click="confirmAction('approve-manager', item)" class="btn btn-outline-success" title="Setujui (Manager)">
                      <i class="bi bi-check-all"></i>
                    </button>
                    <!-- Reject (Chief atau Manager) -->
                    <button v-if="(item.status === 'pending_chief' && canApproveChief) || (item.status === 'pending_manager' && canApproveManager)"
                      @click="openReject(item)" class="btn btn-outline-danger" title="Tolak">
                      <i class="bi bi-x-circle"></i>
                    </button>
                    <!-- Hapus draft -->
                    <button v-if="item.status === 'draft' && (item.requested_by === authStore.user?.id || authStore.isSuperuser)"
                      @click="confirmAction('delete', item)" class="btn btn-outline-danger" title="Hapus">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="meta && meta.last_page > 1" class="d-flex justify-content-between align-items-center p-3 border-top">
          <small class="text-muted">Total {{ meta.total }} data</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
            <button v-for="p in pageRange" :key="p" class="btn" :class="p === meta.page ? 'btn-csm-primary' : 'btn-outline-secondary'" @click="changePage(p)">{{ p }}</button>
            <button class="btn btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Buat Transfer Part
    ══════════════════════════════════════════════ -->
    <div v-if="showCreate" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,0.45);">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-semibold">
              <i class="bi bi-arrow-left-right me-2 text-primary"></i>Buat Transfer Part Darurat
            </h5>
            <button type="button" class="btn-close" @click="showCreate = false"></button>
          </div>
          <div class="modal-body" style="overflow-y:auto;max-height:calc(90vh - 130px);">
            <form @submit.prevent="submitCreate" id="formCreate">

              <!-- ASAL & TUJUAN -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <div class="border rounded p-3 h-100 bg-light">
                    <div class="fw-semibold small text-primary mb-2"><i class="bi bi-box-arrow-right me-1"></i>ASAL</div>
                    <div class="mb-2">
                      <label class="form-label form-label-sm fw-medium mb-1">Gudang Asal <span class="text-danger">*</span></label>
                      <select v-model="form.from_warehouse_id" required class="form-select form-select-sm" @change="onFromWarehouseChange">
                        <option value="">— Pilih Gudang —</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="form-label form-label-sm fw-medium mb-1">Unit Asal <span class="text-danger">*</span></label>
                      <select v-model="form.unit_from_id" required class="form-select form-select-sm"
                        :disabled="!form.from_warehouse_id || loadingUnitsFrom" @change="onUnitFromChange">
                        <option value="">{{ !form.from_warehouse_id ? '— Pilih gudang dulu —' : loadingUnitsFrom ? 'Memuat...' : unitsFrom.length ? '— Pilih Unit —' : 'Tidak ada unit' }}</option>
                        <option v-for="u in unitsFrom" :key="u.id" :value="u.id">{{ u.unit_code }}{{ u.type_unit ? ' — ' + u.type_unit : '' }}</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="border rounded p-3 h-100 bg-light">
                    <div class="fw-semibold small text-success mb-2"><i class="bi bi-box-arrow-in-right me-1"></i>TUJUAN</div>
                    <div class="mb-2">
                      <label class="form-label form-label-sm fw-medium mb-1">Gudang Tujuan <span class="text-danger">*</span></label>
                      <select v-model="form.to_warehouse_id" required class="form-select form-select-sm" @change="onToWarehouseChange">
                        <option value="">— Pilih Gudang —</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id" :disabled="w.id === form.from_warehouse_id">{{ w.name }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="form-label form-label-sm fw-medium mb-1">Unit Tujuan <span class="text-danger">*</span></label>
                      <select v-model="form.unit_to_id" required class="form-select form-select-sm"
                        :disabled="!form.to_warehouse_id || loadingUnitsTo" @change="onUnitToChange">
                        <option value="">{{ !form.to_warehouse_id ? '— Pilih gudang dulu —' : loadingUnitsTo ? 'Memuat...' : unitsTo.length ? '— Pilih Unit —' : 'Tidak ada unit' }}</option>
                        <option v-for="u in unitsTo" :key="u.id" :value="u.id" :disabled="u.id === form.unit_from_id">{{ u.unit_code }}{{ u.type_unit ? ' — ' + u.type_unit : '' }}</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Alasan Urgent -->
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label fw-medium mb-0">Alasan Urgent <span class="text-danger">*</span></label>
                  <small :class="(form.alasan_urgent?.length || 0) < 10 ? 'text-danger' : 'text-success'">
                    {{ form.alasan_urgent?.length || 0 }} karakter (min. 10)
                  </small>
                </div>
                <textarea v-model="form.alasan_urgent" required rows="3"
                  placeholder="Jelaskan alasan mengapa part perlu dipinjam/dipindah secara darurat..."
                  class="form-control form-control-sm"
                  :class="form.alasan_urgent && form.alasan_urgent.length < 10 ? 'is-invalid' : ''"></textarea>
              </div>

              <!-- Tanggal Dibutuhkan -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label fw-medium">Tgl. Dibutuhkan</label>
                  <input v-model="form.needed_date" type="date" class="form-control form-control-sm" />
                </div>
              </div>

              <!-- Daftar Part -->
              <div class="mb-2">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <label class="form-label fw-medium mb-0">Daftar Part <span class="text-danger">*</span></label>
                  <button type="button" @click="addItem" class="btn btn-outline-primary btn-xs">
                    <i class="bi bi-plus"></i> Tambah Part
                  </button>
                </div>
                <div v-for="(item, idx) in form.items" :key="idx" class="border rounded p-2 mb-2 bg-light">
                  <div class="row g-2 align-items-start">
                    <div class="col-md-5 position-relative">
                      <input
                        :value="item.item_search"
                        @input="e => { form.items[idx].item_search = e.target.value; searchItem(form.items[idx]) }"
                        placeholder="Cari nama/part number..."
                        class="form-control form-control-sm"
                      />
                      <ul v-if="item.searchResults?.length" class="list-unstyled border rounded bg-white shadow mb-0 position-absolute w-100" style="top:100%;z-index:9999;max-height:200px;overflow-y:auto;">
                        <li v-for="res in item.searchResults" :key="res.id"
                          @mousedown.prevent="selectItem(item, res)"
                          class="px-3 py-2 border-bottom"
                          style="font-size:0.82rem;cursor:pointer;"
                          @mouseenter="$event.currentTarget.style.background='#eef2ff'"
                          @mouseleave="$event.currentTarget.style.background=''">
                          <span class="badge bg-secondary me-1" style="font-size:0.7rem;font-family:monospace;">{{ res.part_number || '—' }}</span>
                          {{ res.name }}
                          <small v-if="res.brand" class="text-muted ms-1">· {{ res.brand }}</small>
                        </li>
                      </ul>
                      <small v-if="item.item_id" class="text-success d-block mt-1"><i class="bi bi-check-circle-fill"></i> {{ item.item_name }}</small>
                    </div>
                    <div class="col-md-2">
                      <input v-model.number="form.items[idx].qty" type="number" min="0.01" step="0.01" placeholder="Qty" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-2">
                      <input v-model="form.items[idx].satuan" placeholder="Satuan" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-2">
                      <input v-model="form.items[idx].keterangan" placeholder="Keterangan" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                      <button v-if="form.items.length > 1" type="button" @click="removeItem(idx)" class="btn btn-outline-danger btn-xs">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Error -->
              <div v-if="createError" class="alert alert-danger py-2 small">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ createError }}
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" @click="showCreate = false" class="btn btn-secondary btn-sm">Batal</button>
            <button type="submit" form="formCreate" :disabled="creating" class="btn btn-csm-primary btn-sm">
              <span v-if="creating"><span class="csm-spinner me-1"></span>Menyimpan...</span>
              <span v-else><i class="bi bi-save me-1"></i>Simpan Draft</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Detail
    ══════════════════════════════════════════════ -->
    <div v-if="showDetail && selected" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,0.45);">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h5 class="modal-title font-monospace fw-semibold">{{ selected.mr_number }}</h5>
              <span class="badge rounded-pill" :class="statusClass(selected.status)">{{ statusLabel(selected.status) }}</span>
            </div>
            <button type="button" class="btn-close" @click="showDetail = false"></button>
          </div>
          <div class="modal-body">

            <!-- Info Utama -->
            <div class="row g-3 mb-3">
              <div class="col-6">
                <small class="text-muted d-block">Gudang Asal</small>
                <span class="fw-medium">{{ selected.from_warehouse?.name }}</span>
              </div>
              <div class="col-6">
                <small class="text-muted d-block">Gudang Tujuan</small>
                <span class="fw-medium">{{ selected.to_warehouse?.name }}</span>
              </div>
              <div class="col-6">
                <small class="text-muted d-block">Unit Asal</small>
                <span class="fw-semibold font-monospace">{{ selected.unit_from_kode || '—' }}</span>
                <small class="text-muted ms-1">{{ selected.unit_from_tipe }}</small>
              </div>
              <div class="col-6">
                <small class="text-muted d-block">Unit Tujuan</small>
                <span class="fw-semibold font-monospace">{{ selected.unit_to_kode || '—' }}</span>
                <small class="text-muted ms-1">{{ selected.unit_to_tipe }}</small>
              </div>
              <div class="col-6" v-if="selected.needed_date">
                <small class="text-muted d-block">Tgl. Dibutuhkan</small>
                <span class="fw-medium">{{ formatDate(selected.needed_date) }}</span>
              </div>
            </div>

            <!-- Alasan Urgent -->
            <div class="alert alert-warning py-2 mb-3">
              <small class="fw-semibold d-block mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Alasan Urgent</small>
              {{ selected.alasan_urgent || '—' }}
            </div>

            <!-- Alasan Ditolak -->
            <div v-if="selected.status === 'rejected' && selected.rejection_reason" class="alert alert-danger py-2 mb-3">
              <small class="fw-semibold d-block mb-1"><i class="bi bi-x-circle me-1"></i>Alasan Penolakan</small>
              {{ selected.rejection_reason }}
            </div>

            <!-- Persetujuan -->
            <div class="csm-card mb-3">
              <div class="csm-card-header py-2"><h6 class="mb-0 small fw-semibold">Persetujuan</h6></div>
              <div class="csm-card-body py-2">
                <div class="row g-2">
                  <div class="col-6">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi" :class="selected.chief_authorized_by ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'"></i>
                      <div>
                        <div class="small fw-medium">Chief Mekanik</div>
                        <small v-if="selected.chief_authorizer" class="text-muted">{{ selected.chief_authorizer?.name }}</small>
                        <small v-if="selected.chief_authorized_at" class="text-muted d-block">{{ formatDate(selected.chief_authorized_at) }}</small>
                      </div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi" :class="selected.manager_approved_by ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'"></i>
                      <div>
                        <div class="small fw-medium">Manager</div>
                        <small v-if="selected.manager_approver" class="text-muted">{{ selected.manager_approver?.name }}</small>
                        <small v-if="selected.manager_approved_at" class="text-muted d-block">{{ formatDate(selected.manager_approved_at) }}</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Daftar Part -->
            <div class="mb-3">
              <div class="fw-semibold small text-muted mb-2">DAFTAR PART</div>
              <div class="table-responsive">
                <table class="table table-sm csm-table mb-0 border">
                  <thead>
                    <tr>
                      <th>Part</th>
                      <th class="text-end">Qty</th>
                      <th>Satuan</th>
                      <th>Keterangan</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in selected.items" :key="item.id">
                      <td>
                        <div>{{ item.item?.name }}</div>
                        <small class="text-muted font-monospace">{{ item.item?.part_number }}</small>
                      </td>
                      <td class="text-end fw-medium">{{ item.qty_request }}</td>
                      <td class="text-muted">{{ item.satuan }}</td>
                      <td><small class="text-muted">{{ item.notes || '—' }}</small></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- PO Pengganti -->
            <div>
              <div class="fw-semibold small text-muted mb-2">PO PENGGANTI</div>
              <div v-if="selected.linked_po" class="alert alert-success py-2 d-flex align-items-center gap-2 mb-0">
                <i class="bi bi-check-circle-fill"></i>
                <span class="font-monospace fw-semibold">{{ selected.linked_po?.po_number }}</span>
                <small class="text-muted">— {{ selected.linked_po?.supplier?.name }}</small>
              </div>
              <div v-else class="text-muted fst-italic small">
                <i class="bi bi-info-circle me-1"></i>Belum ada PO pengganti. Link dari halaman PO saat membuat/mengedit PO.
              </div>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-between">
            <!-- Aksi dari modal detail -->
            <div class="d-flex gap-2">
              <button v-if="selected.status === 'draft' && selected.requested_by === authStore.user?.id"
                @click="showDetail=false; confirmAction('submit', selected)" class="btn btn-secondary btn-sm">
                <i class="bi bi-send me-1"></i>Submit ke Chief
              </button>
              <button v-if="selected.status === 'pending_chief' && canApproveChief"
                @click="showDetail=false; confirmAction('approve-chief', selected)" class="btn btn-info btn-sm text-white">
                <i class="bi bi-check-circle me-1"></i>Setujui (Chief)
              </button>
              <button v-if="selected.status === 'pending_manager' && canApproveManager"
                @click="showDetail=false; confirmAction('approve-manager', selected)" class="btn btn-success btn-sm">
                <i class="bi bi-check-all me-1"></i>Setujui (Manager)
              </button>
              <button v-if="(selected.status === 'pending_chief' && canApproveChief) || (selected.status === 'pending_manager' && canApproveManager)"
                @click="showDetail=false; openReject(selected)" class="btn btn-danger btn-sm">
                <i class="bi bi-x-circle me-1"></i>Tolak
              </button>
            </div>
            <button type="button" @click="showDetail = false" class="btn btn-secondary btn-sm">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Konfirmasi Aksi
    ══════════════════════════════════════════════ -->
    <div v-if="showConfirm" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,0.45);">
      <div class="modal-dialog modal-sm">
        <div class="modal-content">
          <div class="modal-header py-2">
            <h6 class="modal-title fw-semibold">{{ confirmTitle }}</h6>
            <button type="button" class="btn-close btn-close-sm" @click="showConfirm = false"></button>
          </div>
          <div class="modal-body py-3">
            <p class="mb-1">{{ confirmMessage }}</p>
            <p class="font-monospace fw-semibold text-primary small mb-0">{{ confirmTarget?.mr_number }}</p>
          </div>
          <div class="modal-footer py-2">
            <button @click="showConfirm = false" class="btn btn-secondary btn-sm">Batal</button>
            <button @click="executeAction" :disabled="actionLoading" :class="confirmBtnClass" class="btn btn-sm">
              <span v-if="actionLoading"><span class="csm-spinner me-1"></span></span>
              <span v-else>{{ confirmBtnLabel }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Tolak
    ══════════════════════════════════════════════ -->
    <div v-if="showReject" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,0.45);">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title fw-semibold text-danger"><i class="bi bi-x-circle me-2"></i>Tolak Transfer Part</h6>
            <button type="button" class="btn-close" @click="showReject = false"></button>
          </div>
          <div class="modal-body">
            <p class="small text-muted mb-2">Nomor: <span class="font-monospace fw-semibold">{{ rejectTarget?.mr_number }}</span></p>
            <label class="form-label fw-medium">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea v-model="rejectReason" rows="3" placeholder="Jelaskan alasan penolakan (min. 5 karakter)..." class="form-control form-control-sm"></textarea>
            <div v-if="rejectError" class="text-danger small mt-1">{{ rejectError }}</div>
          </div>
          <div class="modal-footer">
            <button @click="showReject = false" class="btn btn-secondary btn-sm">Batal</button>
            <button @click="submitReject" :disabled="actionLoading" class="btn btn-danger btn-sm">
              <span v-if="actionLoading"><span class="csm-spinner me-1"></span></span>
              <span v-else><i class="bi bi-x-circle me-1"></i>Tolak</span>
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/store/auth'
import { useToast } from 'vue-toastification'

const authStore = useAuthStore()
const toast     = useToast()

// ── State ─────────────────────────────────────────────────────────────────────
const items        = ref([])
const meta         = ref({ total: 0, page: 1, last_page: 1 })
const loading      = ref(false)
const search       = ref('')
const filterStatus = ref('')
const page         = ref(1)

const warehouses       = ref([])
const unitsFrom        = ref([])
const unitsTo          = ref([])
const loadingUnitsFrom = ref(false)
const loadingUnitsTo   = ref(false)

const showCreate  = ref(false)
const showDetail  = ref(false)
const showConfirm = ref(false)
const showReject  = ref(false)
const selected    = ref(null)
const creating    = ref(false)
const createError = ref('')
const form        = ref(defaultForm())

const confirmTarget   = ref(null)
const confirmAction_  = ref('')
const actionLoading   = ref(false)
const rejectTarget    = ref(null)
const rejectReason    = ref('')
const rejectError     = ref('')

// ── Permissions ───────────────────────────────────────────────────────────────
const canApproveChief   = computed(() => authStore.hasPermission('approve-mr') || authStore.isSuperuser)
const canApproveManager = computed(() => authStore.hasPermission('approve-mr-manager') || authStore.isSuperuser)

// ── Confirm modal computed ────────────────────────────────────────────────────
const confirmTitle = computed(() => ({
  'submit':          'Submit ke Chief Mekanik',
  'approve-chief':   'Setujui sebagai Chief Mekanik',
  'approve-manager': 'Setujui sebagai Manager',
  'delete':          'Hapus Draft',
}[confirmAction_.value] || 'Konfirmasi'))

const confirmMessage = computed(() => ({
  'submit':          'Submit Transfer Part ini untuk mendapat persetujuan Chief Mekanik?',
  'approve-chief':   'Setujui Transfer Part ini sebagai Chief Mekanik?',
  'approve-manager': 'Setujui Transfer Part ini sebagai Manager?',
  'delete':          'Hapus draft Transfer Part ini? Tindakan ini tidak bisa dibatalkan.',
}[confirmAction_.value] || ''))

const confirmBtnClass = computed(() => ({
  'submit':          'btn-secondary',
  'approve-chief':   'btn-info text-white',
  'approve-manager': 'btn-success',
  'delete':          'btn-danger',
}[confirmAction_.value] || 'btn-primary'))

const confirmBtnLabel = computed(() => ({
  'submit':          'Submit',
  'approve-chief':   'Setujui',
  'approve-manager': 'Setujui',
  'delete':          'Hapus',
}[confirmAction_.value] || 'Ya'))

// ── Pagination ────────────────────────────────────────────────────────────────
const pageRange = computed(() => {
  if (!meta.value?.last_page) return []
  const cur   = meta.value.page
  const start = Math.max(1, cur - 2)
  const end   = Math.min(meta.value.last_page, cur + 2)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(() => { fetchData(); fetchWarehouses() })

// ── Fetch ─────────────────────────────────────────────────────────────────────
async function fetchData() {
  loading.value = true
  try {
    const { data } = await axios.get('/transfer-part', {
      params: { search: search.value, status: filterStatus.value, page: page.value }
    })
    items.value = data.data ?? []
    if (data.meta) meta.value = data.meta
  } catch (e) {
    console.error('[TransferPart] fetchData:', e)
  } finally {
    loading.value = false
  }
}

let debounceTimer = null
function debounceFetch() { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetchData, 400) }

async function fetchWarehouses() {
  try {
    const { data } = await axios.get('/warehouses')
    warehouses.value = data.data || data
  } catch (e) { console.error('[TransferPart] fetchWarehouses:', e) }
}

async function fetchUnits(warehouseId, target) {
  const loadRef  = target === 'from' ? loadingUnitsFrom : loadingUnitsTo
  const unitsRef = target === 'from' ? unitsFrom : unitsTo
  if (!warehouseId) { unitsRef.value = []; return }
  loadRef.value = true
  try {
    const { data } = await axios.get('/units', { params: { warehouse_id: warehouseId } })
    unitsRef.value = data.data || []
  } catch (e) { unitsRef.value = [] }
  finally { loadRef.value = false }
}

function changePage(p) { page.value = p; fetchData() }

// ── Warehouse / Unit handlers ─────────────────────────────────────────────────
function onFromWarehouseChange() {
  form.value.unit_from_id = ''; form.value.unit_from_kode = ''; form.value.unit_from_tipe = ''
  fetchUnits(form.value.from_warehouse_id, 'from')
}
function onToWarehouseChange() {
  form.value.unit_to_id = ''; form.value.unit_to_kode = ''; form.value.unit_to_tipe = ''
  fetchUnits(form.value.to_warehouse_id, 'to')
}
function onUnitFromChange() {
  const u = unitsFrom.value.find(u => u.id === form.value.unit_from_id)
  form.value.unit_from_kode = u?.unit_code || ''; form.value.unit_from_tipe = u?.type_unit || ''
}
function onUnitToChange() {
  const u = unitsTo.value.find(u => u.id === form.value.unit_to_id)
  form.value.unit_to_kode = u?.unit_code || ''; form.value.unit_to_tipe = u?.type_unit || ''
}

// ── Create ────────────────────────────────────────────────────────────────────
function openCreate() {
  form.value = defaultForm(); createError.value = ''
  unitsFrom.value = []; unitsTo.value = []; showCreate.value = true
}

async function submitCreate() {
  createError.value = ''
  if (!form.value.from_warehouse_id)  { createError.value = 'Gudang asal harus dipilih'; return }
  if (!form.value.to_warehouse_id)    { createError.value = 'Gudang tujuan harus dipilih'; return }
  if (!form.value.unit_from_id || !form.value.unit_from_kode) { createError.value = 'Unit asal harus dipilih'; return }
  if (!form.value.unit_to_id   || !form.value.unit_to_kode)   { createError.value = 'Unit tujuan harus dipilih'; return }
  if (!form.value.alasan_urgent || form.value.alasan_urgent.trim().length < 10) {
    createError.value = 'Alasan urgent minimal 10 karakter'; return
  }
  for (const item of form.value.items) {
    if (!item.item_id)              { createError.value = 'Semua part harus dipilih dari daftar'; return }
    if (!item.qty || item.qty <= 0) { createError.value = 'Qty part harus lebih dari 0'; return }
    if (!item.satuan)               { createError.value = 'Satuan part harus diisi'; return }
  }
  creating.value = true
  try {
    const res = await axios.post('/transfer-part', {
      from_warehouse_id: form.value.from_warehouse_id,
      to_warehouse_id:   form.value.to_warehouse_id,
      unit_from_kode:    form.value.unit_from_kode,
      unit_from_tipe:    form.value.unit_from_tipe,
      unit_to_kode:      form.value.unit_to_kode,
      unit_to_tipe:      form.value.unit_to_tipe,
      alasan_urgent:     form.value.alasan_urgent,
      needed_date:       form.value.needed_date || null,
      notes:             form.value.notes || null,
      items: form.value.items.map(i => ({
        item_id: i.item_id, qty: i.qty, satuan: i.satuan, keterangan: i.keterangan
      }))
    })
    showCreate.value = false
    toast.success(res.data.message || 'Draft berhasil dibuat')
    fetchData()
  } catch (e) {
    const d = e.response?.data
    if (d?.errors) createError.value = Object.values(d.errors).flat().join(' · ')
    else createError.value = d?.message || 'Gagal menyimpan'
  } finally {
    creating.value = false
  }
}

// ── Detail ────────────────────────────────────────────────────────────────────
async function openDetail(item) {
  try {
    const { data } = await axios.get(`/transfer-part/${item.id}`)
    selected.value = data.data; showDetail.value = true
  } catch (e) { toast.error('Gagal memuat detail') }
}

// ── Confirm aksi (submit / approve / delete) ──────────────────────────────────
function confirmAction(action, item) {
  confirmAction_.value = action; confirmTarget.value = item; showConfirm.value = true
}

async function executeAction() {
  actionLoading.value = true
  const item = confirmTarget.value
  try {
    let res
    if (confirmAction_.value === 'submit') {
      res = await axios.post(`/transfer-part/${item.id}/submit`)
    } else if (confirmAction_.value === 'approve-chief') {
      res = await axios.post(`/transfer-part/${item.id}/approve-chief`)
    } else if (confirmAction_.value === 'approve-manager') {
      res = await axios.post(`/transfer-part/${item.id}/approve-manager`)
    } else if (confirmAction_.value === 'delete') {
      res = await axios.delete(`/transfer-part/${item.id}`)
    }
    toast.success(res.data.message || 'Berhasil')
    showConfirm.value = false
    fetchData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal melakukan aksi')
  } finally {
    actionLoading.value = false
  }
}

// ── Reject ────────────────────────────────────────────────────────────────────
function openReject(item) { rejectTarget.value = item; rejectReason.value = ''; rejectError.value = ''; showReject.value = true }

async function submitReject() {
  rejectError.value = ''
  if (!rejectReason.value || rejectReason.value.trim().length < 5) { rejectError.value = 'Alasan penolakan minimal 5 karakter'; return }
  actionLoading.value = true
  try {
    const res = await axios.post(`/transfer-part/${rejectTarget.value.id}/reject`, { reason: rejectReason.value })
    toast.success(res.data.message || 'Transfer Part ditolak')
    showReject.value = false
    fetchData()
  } catch (e) {
    rejectError.value = e.response?.data?.message || 'Gagal menolak'
  } finally {
    actionLoading.value = false
  }
}

// ── Part search ────────────────────────────────────────────────────────────────
let searchTimer = null
async function searchItem(item) {
  clearTimeout(searchTimer)
  const idx = form.value.items.indexOf(item)
  if (idx === -1) return
  if (!item.item_search || item.item_search.length < 2) {
    form.value.items[idx] = { ...item, searchResults: [] }; return
  }
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get('/items', { params: { search: item.item_search, per_page: 10 } })
      form.value.items[idx] = { ...form.value.items[idx], searchResults: data.data ?? [] }
    } catch { form.value.items[idx] = { ...form.value.items[idx], searchResults: [] } }
  }, 300)
}

function selectItem(item, res) {
  const idx = form.value.items.indexOf(item)
  if (idx !== -1) {
    form.value.items[idx] = {
      ...form.value.items[idx],
      item_id: res.id, item_name: res.name,
      item_search: `${res.part_number ? res.part_number + ' — ' : ''}${res.name}`,
      satuan: res.satuan || 'Pcs', searchResults: []
    }
  }
}

function addItem()       { form.value.items.push(defaultItem()) }
function removeItem(idx) { form.value.items.splice(idx, 1) }

// ── Helpers ───────────────────────────────────────────────────────────────────
function defaultForm() {
  return {
    from_warehouse_id: '', to_warehouse_id: '',
    unit_from_id: '', unit_from_kode: '', unit_from_tipe: '',
    unit_to_id:   '', unit_to_kode:   '', unit_to_tipe:   '',
    alasan_urgent: '', needed_date: '', notes: '',
    items: [defaultItem()]
  }
}
function defaultItem() {
  return { item_id: null, item_name: '', item_search: '', qty: 1, satuan: 'Pcs', keterangan: '', searchResults: [] }
}
function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
function statusLabel(s) {
  return { draft: 'Draft', pending_chief: 'Menunggu Chief', pending_manager: 'Menunggu Manager', approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan' }[s] || s
}
function statusClass(s) {
  return { draft: 'badge-draft', pending_chief: 'bg-warning text-dark', pending_manager: 'badge-submitted', approved: 'badge-received', rejected: 'badge-rejected', cancelled: 'badge-cancelled' }[s] || 'bg-secondary'
}
</script>