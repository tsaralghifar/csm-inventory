<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Penggajian (Payroll)</h5>
        <small class="text-muted">Kelola periode dan pembayaran gaji karyawan</small>
      </div>
      <button v-if="can('manage-payroll')" class="btn btn-csm-primary btn-sm" @click="openPeriodModal()">
        <i class="bi bi-plus-circle me-1"></i>Buat Periode Gaji
      </button>
    </div>

    <!-- List Periode -->
    <div class="csm-card mb-3" v-if="!selectedPeriod">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr><th>Periode</th><th>Tgl Bayar</th><th>Jumlah Karyawan</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              <tr v-if="!periods.length"><td colspan="5" class="text-center text-muted py-4">Belum ada periode penggajian</td></tr>
              <tr v-for="p in periods" :key="p.id">
                <td class="fw-semibold">{{ p.name }}</td>
                <td class="small">{{ p.payment_date ? $formatDate(p.payment_date) : '-' }}</td>
                <td><span class="badge bg-info text-dark">{{ p.items_count }} karyawan</span></td>
                <td><span :class="statusClass(p.status)">{{ statusLabel(p.status) }}</span></td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-primary" @click="openPeriod(p)" title="Buka Detail">
                      <i class="bi bi-folder2-open"></i>
                    </button>
                    <button v-if="can('manage-payroll') && p.status === 'draft'" class="btn btn-xs btn-outline-success" @click="generate(p)" title="Generate Gaji">
                      <i class="bi bi-magic"></i>
                    </button>
                    <button v-if="can('approve-payroll') && p.status === 'processing'" class="btn btn-xs btn-outline-warning" @click="approve(p)" title="Setujui">
                      <i class="bi bi-check2-all"></i>
                    </button>
                    <button v-if="can('approve-payroll') && p.status === 'approved'" class="btn btn-xs btn-csm-primary btn-xs" @click="markPaid(p)" title="Tandai Sudah Dibayar">
                      <i class="bi bi-cash-stack"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Detail Periode -->
    <div v-if="selectedPeriod">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary btn-sm" @click="selectedPeriod = null">
            <i class="bi bi-arrow-left me-1"></i>Kembali
          </button>
          <h6 class="mb-0 fw-bold">{{ selectedPeriod.name }}</h6>
          <span :class="statusClass(selectedPeriod.status)">{{ statusLabel(selectedPeriod.status) }}</span>
        </div>
        <div class="d-flex gap-2">
          <div class="fw-semibold small text-muted">
            Total Gaji Bersih:
            <span class="text-success fs-6 fw-bold ms-1">{{ $formatCurrency(totalNetSalary) }}</span>
          </div>
        </div>
      </div>

      <div class="csm-card">
        <div class="csm-card-body p-0">
          <div v-if="detailLoading" class="p-4 text-center"><div class="csm-spinner"></div></div>
          <div class="table-responsive" v-else>
            <table class="table csm-table mb-0">
              <thead>
                <tr>
                  <th>Karyawan</th>
                  <th class="text-end">Gaji Pokok</th>
                  <th class="text-end">Tunjangan</th>
                  <th class="text-end">Bonus/THR</th>
                  <th class="text-end">Potongan</th>
                  <th class="text-end">Gaji Bersih</th>
                  <th>Status</th>
                  <th v-if="selectedPeriod.status === 'processing'">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!payrollItems.length"><td colspan="8" class="text-center text-muted py-4">Belum ada data gaji</td></tr>
                <tr v-for="item in payrollItems" :key="item.id">
                  <td>
                    <div class="fw-semibold small">{{ item.employee?.name }}</div>
                    <div class="text-muted" style="font-size:11px;">{{ item.employee?.position || '-' }}</div>
                  </td>
                  <td class="text-end small">{{ $formatCurrency(item.basic_salary) }}</td>
                  <td class="text-end small text-info">
                    {{ $formatCurrency((item.allowance_transport||0) + (item.allowance_meal||0) + (item.allowance_position||0) + (item.allowance_other||0)) }}
                  </td>
                  <td class="text-end small text-warning">
                    {{ $formatCurrency((item.bonus||0) + (item.thr||0) + (item.overtime||0)) }}
                  </td>
                  <td class="text-end small text-danger">
                    -{{ $formatCurrency(item.total_deduction) }}
                  </td>
                  <td class="text-end fw-bold text-success">{{ $formatCurrency(item.net_salary) }}</td>
                  <td><span :class="item.status === 'paid' ? 'badge bg-success' : item.status === 'approved' ? 'badge bg-primary' : 'badge bg-warning text-dark'">
                    {{ item.status === 'paid' ? 'Dibayar' : item.status === 'approved' ? 'Disetujui' : 'Draft' }}
                  </span></td>
                  <td v-if="selectedPeriod.status === 'processing'">
                    <button class="btn btn-xs btn-outline-primary" @click="openEditItem(item)" title="Edit Bonus/Potongan">
                      <i class="bi bi-pencil"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <td class="fw-bold">TOTAL</td>
                  <td class="text-end fw-bold">{{ $formatCurrency(payrollItems.reduce((a,i)=>a+(+i.basic_salary),0)) }}</td>
                  <td class="text-end fw-bold text-info">{{ $formatCurrency(payrollItems.reduce((a,i)=>a+(+i.allowance_transport||0)+(+i.allowance_meal||0)+(+i.allowance_position||0)+(+i.allowance_other||0),0)) }}</td>
                  <td class="text-end fw-bold text-warning">{{ $formatCurrency(payrollItems.reduce((a,i)=>a+(+i.bonus||0)+(+i.thr||0)+(+i.overtime||0),0)) }}</td>
                  <td class="text-end fw-bold text-danger">-{{ $formatCurrency(payrollItems.reduce((a,i)=>a+(+i.total_deduction),0)) }}</td>
                  <td class="text-end fw-bold text-success fs-6">{{ $formatCurrency(totalNetSalary) }}</td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Buat Periode -->
    <div class="modal fade" id="periodModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Buat Periode Penggajian</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Bulan <span class="text-danger">*</span></label>
                <select v-model="periodForm.month" class="form-select form-select-sm">
                  <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tahun <span class="text-danger">*</span></label>
                <input v-model.number="periodForm.year" type="number" min="2020" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Periode Awal <span class="text-danger">*</span></label>
                <input v-model="periodForm.period_start" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Periode Akhir <span class="text-danger">*</span></label>
                <input v-model="periodForm.period_end" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="periodForm.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="savePeriod" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>Buat Periode
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Edit Item Gaji -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content" v-if="editItem">
          <div class="modal-header">
            <h5 class="modal-title">Edit Komponen Gaji — {{ editItem.employee?.name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info small py-2">Gaji Pokok: <strong>{{ $formatCurrency(editItem.basic_salary) }}</strong></div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Bonus / Insentif</label>
                <input v-model.number="editItem.bonus" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">THR</label>
                <input v-model.number="editItem.thr" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Lembur</label>
                <input v-model.number="editItem.overtime" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Potongan Denda</label>
                <input v-model.number="editItem.deduction_fine" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Potongan Lain</label>
                <input v-model.number="editItem.deduction_other" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="editItem.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="saveItem" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>Simpan
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { useRealtime } from '@/composables/useRealtime'

const auth = useAuthStore(); const toast = useToast()
const { listenPayroll, stopPayroll } = useRealtime()
const can = (p) => auth.hasPermission(p)
const periods = ref([]); const payrollItems = ref([])
const loading = ref(false); const detailLoading = ref(false); const saving = ref(false)
const selectedPeriod = ref(null); const editItem = ref(null)
const now = new Date()
const periodForm = ref({ month: now.getMonth() + 1, year: now.getFullYear(), period_start: '', period_end: '', notes: '' })
let periodModal = null; let editItemModal = null

const totalNetSalary = computed(() => payrollItems.value.reduce((a, i) => a + (+i.net_salary || 0), 0))
const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']
function monthName(m) { return monthNames[m - 1] }

onMounted(() => {
  periodModal = new Modal(document.getElementById('periodModal'))
  editItemModal = new Modal(document.getElementById('editItemModal'))
  load()
  listenPayroll(() => load())
})

onUnmounted(() => stopPayroll())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/payroll/periods')
    periods.value = r.data.data
  } finally { loading.value = false }
}

async function openPeriod(p) {
  selectedPeriod.value = p
  detailLoading.value = true
  try {
    const r = await axios.get(`/payroll/periods/${p.id}`)
    payrollItems.value = r.data.data.items || []
  } finally { detailLoading.value = false }
}

function openPeriodModal() {
  periodForm.value = { month: now.getMonth() + 1, year: now.getFullYear(), period_start: '', period_end: '', notes: '' }
  periodModal.show()
}

async function savePeriod() {
  saving.value = true
  try {
    await axios.post('/payroll/periods', periodForm.value)
    toast.success('Periode penggajian dibuat')
    periodModal.hide(); load()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal membuat periode') }
  finally { saving.value = false }
}

async function generate(p) {
  if (!confirm(`Generate data gaji untuk periode ${p.name}? Semua karyawan aktif akan diambil komponennya.`)) return
  try {
    const r = await axios.post(`/payroll/periods/${p.id}/generate`)
    toast.success(r.data.message)
    load(); if (selectedPeriod.value?.id === p.id) openPeriod(p)
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal generate') }
}

async function approve(p) {
  if (!confirm(`Setujui penggajian periode ${p.name}?`)) return
  try {
    await axios.post(`/payroll/periods/${p.id}/approve`)
    toast.success('Penggajian disetujui')
    load()
    if (selectedPeriod.value?.id === p.id) { selectedPeriod.value.status = 'approved' }
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyetujui') }
}

async function markPaid(p) {
  if (!confirm(`Tandai gaji periode ${p.name} sudah dibayarkan?`)) return
  try {
    await axios.post(`/payroll/periods/${p.id}/pay`)
    toast.success('Penggajian ditandai sudah dibayar')
    load()
    if (selectedPeriod.value?.id === p.id) { selectedPeriod.value.status = 'paid' }
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal mengubah status') }
}

function openEditItem(item) {
  editItem.value = { ...item }
  editItemModal.show()
}

async function saveItem() {
  saving.value = true
  try {
    const r = await axios.put(`/payroll/periods/${selectedPeriod.value.id}/items/${editItem.value.id}`, editItem.value)
    const idx = payrollItems.value.findIndex(i => i.id === editItem.value.id)
    if (idx !== -1) payrollItems.value[idx] = r.data.data
    toast.success('Data gaji diperbarui')
    editItemModal.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}

function statusClass(s) {
  return { draft: 'badge bg-secondary', processing: 'badge bg-warning text-dark', approved: 'badge bg-primary', paid: 'badge bg-success' }[s] || 'badge bg-secondary'
}
function statusLabel(s) {
  return { draft: 'Draft', processing: 'Diproses', approved: 'Disetujui', paid: 'Dibayar' }[s] || s
}
</script>