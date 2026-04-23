<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Transfer Barang</h5>
      <button v-if="can('create-mr')" class="btn btn-csm-primary btn-sm" @click="openCreateMR">
        <i class="bi bi-plus-circle me-1"></i>Buat Transfer Baru
      </button>
    </div>

    <!-- Filters -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-12 col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. Transfer..." @input="debouncedLoad" />
          </div>
          <div class="col-6 col-md-2">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadMRs">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="submitted">Submitted</option>
              <option value="approved">Approved</option>
              <option value="dispatched">Dispatched</option>
              <option value="received">Received</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="loadMRs" />
          </div>
          <div class="col-6 col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="loadMRs" />
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
                <th>No. Transfer</th>
                <th>Dari Site</th>
                <th>Ke Gudang</th>
                <th>Item</th>
                <th>Status</th>
                <th>Diajukan Oleh</th>
                <th>Tanggal</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="mr in mrs" :key="mr.id">
                <td><span class="fw-semibold text-primary">{{ mr.mr_number }}</span></td>
                <td>{{ mr.from_warehouse?.name }}</td>
                <td>{{ mr.to_warehouse?.name }}</td>
                <td><span class="badge bg-secondary rounded-pill">{{ mr.items_count }} item</span></td>
                <td>
                  <span class="badge rounded-pill" :class="`badge-${mr.status}`">
                    {{ statusLabel(mr.status) }}
                  </span>
                </td>
                <td>{{ mr.requester?.name }}</td>
                <td><small>{{ $formatDate(mr.created_at) }}</small></td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <router-link :to="`/mr/${mr.id}`" class="btn btn-outline-primary"><i class="bi bi-eye"></i></router-link>
                    <button v-if="mr.status === 'draft' && can('create-mr')" class="btn btn-outline-info" @click="submitMR(mr)" title="Submit">
                      <i class="bi bi-send"></i>
                    </button>
                    <button v-if="mr.status === 'submitted' && can('approve-mr')" class="btn btn-outline-success" @click="openApprove(mr)" title="Approve">
                      <i class="bi bi-check-circle"></i>
                    </button>
                    <button v-if="mr.status === 'approved' && can('dispatch-mr')" class="btn btn-outline-warning" @click="openDispatch(mr)" title="Dispatch">
                      <i class="bi bi-truck"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!mrs.length && !loading">
                <td colspan="8" class="text-center text-muted py-5">Tidak ada data Transfer Barang</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top" v-if="meta.last_page > 1">
          <small class="text-muted">{{ meta.total }} total</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
            <button class="btn btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Buat Transfer -->
    <div class="modal fade" id="modalCreateMR" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-clipboard-plus me-2"></i>Buat Transfer Barang</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Dari Site / Gudang <span class="text-danger">*</span></label>
                <select v-model="mrForm.from_warehouse_id" class="form-select">
                  <option value="">-- Pilih Site --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Ke Gudang <span class="text-danger">*</span></label>
                <select v-model="mrForm.to_warehouse_id" class="form-select">
                  <option value="">-- Pilih Gudang Tujuan --</option>
                  <option v-for="w in warehouses.filter(w => w.id != mrForm.from_warehouse_id)" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Tanggal Butuh</label>
                <input v-model="mrForm.needed_date" type="date" class="form-control" />
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Catatan</label>
                <input v-model="mrForm.notes" class="form-control" placeholder="Keterangan tambahan..." />
              </div>
            </div>

            <!-- Item List -->
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong class="small">Daftar Barang</strong>
              <button class="btn btn-sm btn-outline-primary" @click="addMRItem"><i class="bi bi-plus me-1"></i>Tambah Barang</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm">
                <thead class="table-light">
                  <tr><th>Barang</th><th style="width:120px;">Qty</th><th style="width:160px;">Satuan</th><th>Catatan</th><th style="width:40px;"></th></tr>
                </thead>
                <tbody>
                  <tr v-for="(item, i) in mrForm.items" :key="i">
                    <td>
                      <select v-model="item.item_id" class="form-select form-select-sm">
                        <option value="">-- Pilih Barang --</option>
                        <option v-for="it in allItems" :key="it.id" :value="it.id">{{ it.part_number }} - {{ it.name }}</option>
                      </select>
                    </td>
                    <td><input v-model="item.qty" type="number" class="form-control form-control-sm" min="0.01" step="0.01" /></td>
                    <td><small class="text-muted">{{ getItemUnit(item.item_id) }}</small></td>
                    <td><input v-model="item.notes" class="form-control form-control-sm" placeholder="Ket..." /></td>
                    <td><button class="btn btn-sm btn-outline-danger" @click="mrForm.items.splice(i, 1)"><i class="bi bi-trash"></i></button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="saveMR" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>Simpan Transfer
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Dispatch -->
    <div class="modal fade" id="modalDispatch" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header"><h6 class="modal-title"><i class="bi bi-truck me-2"></i>Kirim Barang</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-3"><label class="form-label small fw-semibold">Nama Driver</label><input v-model="dispatchForm.driver_name" class="form-control" /></div>
            <div class="mb-3"><label class="form-label small fw-semibold">Nomor Kendaraan</label><input v-model="dispatchForm.vehicle_plate" class="form-control" /></div>
            <div class="mb-3"><label class="form-label small fw-semibold">Catatan</label><input v-model="dispatchForm.notes" class="form-control" /></div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-warning btn-sm" @click="doDispatch" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span><i class="bi bi-truck me-1"></i>Kirim & Buat DO
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/store/auth'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { Modal } from 'bootstrap'

const auth = useAuthStore()
const toast = useToast()
const { listenMR, stopMR } = useRealtime()

const mrs = ref([])
const warehouses = ref([])
const allItems = ref([])
const loading = ref(true)
const saving = ref(false)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ search: '', status: '', date_from: '', date_to: '', page: 1 })
const selectedMR = ref(null)

const mrForm = ref({ from_warehouse_id: '', to_warehouse_id: '', needed_date: '', notes: '', items: [{ item_id: '', qty: '', notes: '' }] })
const dispatchForm = ref({ driver_name: '', vehicle_plate: '', notes: '' })

function can(p) { return auth.hasPermission(p) }
const statusLabel = (s) => ({ draft: 'Draft', submitted: 'Submitted', approved: 'Approved', dispatched: 'Dikirim', received: 'Diterima', rejected: 'Ditolak' }[s] || s)
const getItemUnit = (itemId) => allItems.value.find(i => i.id === itemId)?.unit || ''

let searchTimer = null
function debouncedLoad() { clearTimeout(searchTimer); searchTimer = setTimeout(loadMRs, 400) }

async function loadMRs() {
  loading.value = true
  try {
    const res = await axios.get('/material-requests', { params: { ...filters.value, per_page: 15 } })
    mrs.value = res.data.data
    meta.value = res.data.meta
  } catch (e) { toast.error('Gagal memuat data') }
  finally { loading.value = false }
}

function changePage(p) { filters.value.page = p; loadMRs() }
function addMRItem() { mrForm.value.items.push({ item_id: '', qty: '', notes: '' }) }

function openCreateMR() {
  mrForm.value = { from_warehouse_id: auth.userWarehouseId || '', to_warehouse_id: '', needed_date: '', notes: '', items: [{ item_id: '', qty: '', notes: '' }] }
  new Modal('#modalCreateMR').show()
}

function openDispatch(mr) {
  selectedMR.value = mr
  dispatchForm.value = { driver_name: '', vehicle_plate: '', notes: '' }
  new Modal('#modalDispatch').show()
}

async function openApprove(mr) {
  if (!confirm(`Approve Transfer ${mr.mr_number}? Stok akan di-reserve.`)) return
  try {
    const detail = await axios.get(`/material-requests/${mr.id}`)
    const items = detail.data.data.items.map(i => ({ id: i.id, qty_approved: i.qty_request }))
    await axios.post(`/material-requests/${mr.id}/approve`, { items })
    toast.success('Transfer berhasil diapprove')
    loadMRs()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal approve') }
}

async function submitMR(mr) {
  if (!confirm('Submit Transfer ini?')) return
  try {
    await axios.post(`/material-requests/${mr.id}/submit`)
    toast.success('Transfer berhasil disubmit')
    loadMRs()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal submit') }
}

async function saveMR() {
  if (!mrForm.value.from_warehouse_id || !mrForm.value.to_warehouse_id) return toast.error('Pilih gudang asal dan tujuan')
  if (mrForm.value.items.some(i => !i.item_id || !i.qty)) return toast.error('Lengkapi semua item')
  saving.value = true
  try {
    await axios.post('/material-requests', mrForm.value)
    toast.success('Transfer Barang berhasil dibuat')
    Modal.getInstance('#modalCreateMR')?.hide()
    loadMRs()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal membuat Transfer') }
  finally { saving.value = false }
}

async function doDispatch() {
  saving.value = true
  try {
    await axios.post(`/material-requests/${selectedMR.value.id}/dispatch`, dispatchForm.value)
    toast.success('Barang berhasil dikirim, DO dibuat')
    Modal.getInstance('#modalDispatch')?.hide()
    loadMRs()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal dispatch') }
  finally { saving.value = false }
}

onMounted(async () => {
  const [, warehousesRes, itemsRes] = await Promise.all([
    loadMRs(),
    axios.get('/warehouses', { params: { active: 1 } }),
    axios.get('/items', { params: { per_page: 999 } }),
  ])
  warehouses.value = warehousesRes.data.data
  allItems.value = itemsRes.data.data
})
</script>