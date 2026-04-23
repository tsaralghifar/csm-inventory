<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Pinjaman Karyawan</h5>
        <small class="text-muted">Kelola pinjaman dan cicilan karyawan</small>
      </div>
      <button v-if="can('manage-payroll')" class="btn btn-csm-primary btn-sm" @click="openModal()">
        <i class="bi bi-plus-circle me-1"></i>Tambah Pinjaman
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-4">
            <select v-model="employeeFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Karyawan</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <select v-model="statusFilter" class="form-select form-select-sm" @change="load">
              <option value="">Semua Status</option>
              <option value="active">Aktif</option>
              <option value="completed">Lunas</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. Pinjaman</th><th>Karyawan</th><th>Mulai</th>
                <th class="text-end">Pokok Pinjaman</th>
                <th class="text-end">Cicilan/Bln</th>
                <th>Progress</th>
                <th class="text-end">Sisa</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!loans.length"><td colspan="8" class="text-center text-muted py-4">Tidak ada data pinjaman</td></tr>
              <tr v-for="l in loans" :key="l.id">
                <td><code class="small text-primary">{{ l.loan_number }}</code></td>
                <td class="fw-semibold small">{{ l.employee?.name }}</td>
                <td class="small">{{ $formatDate(l.start_date) }}</td>
                <td class="text-end small">{{ $formatCurrency(l.loan_amount) }}</td>
                <td class="text-end small text-danger">-{{ $formatCurrency(l.monthly_deduction) }}</td>
                <td style="min-width:120px;">
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px;">
                      <div class="progress-bar bg-success" :style="`width:${(l.paid_installments/l.total_installments)*100}%`"></div>
                    </div>
                    <small class="text-muted">{{ l.paid_installments }}/{{ l.total_installments }}</small>
                  </div>
                </td>
                <td class="text-end fw-bold" :class="l.remaining_balance > 0 ? 'text-danger' : 'text-success'">
                  {{ $formatCurrency(l.remaining_balance) }}
                </td>
                <td>
                  <span :class="l.status === 'active' ? 'badge bg-warning text-dark' : l.status === 'completed' ? 'badge bg-success' : 'badge bg-secondary'">
                    {{ l.status === 'active' ? 'Aktif' : l.status === 'completed' ? 'Lunas' : 'Dibatalkan' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center px-3 py-2" v-if="meta.last_page > 1">
          <small class="text-muted">{{ meta.total }} data</small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page - 1)">‹</button>
            <button class="btn btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page + 1)">›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Tambah Pinjaman -->
    <div class="modal fade" id="loanModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Tambah Pinjaman Karyawan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold small">Karyawan <span class="text-danger">*</span></label>
                <select v-model="form.employee_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Karyawan --</option>
                  <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }} — {{ e.position || '-' }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Pokok Pinjaman <span class="text-danger">*</span></label>
                <input v-model.number="form.loan_amount" type="number" min="1" class="form-control form-control-sm" @input="calcInstallment" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Cicilan per Bulan <span class="text-danger">*</span></label>
                <input v-model.number="form.monthly_deduction" type="number" min="1" class="form-control form-control-sm" @input="calcInstallment" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jumlah Cicilan <span class="text-danger">*</span></label>
                <input v-model.number="form.total_installments" type="number" min="1" class="form-control form-control-sm" readonly />
                <small class="text-muted">Otomatis dihitung dari pokok ÷ cicilan</small>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Mulai Potong Gaji <span class="text-danger">*</span></label>
                <input v-model="form.start_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="form.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="save" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>Simpan Pinjaman
            </button>
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
import { useRealtime } from '@/composables/useRealtime'

const auth = useAuthStore(); const toast = useToast()
const { listenPinjamanKaryawan, stopPinjamanKaryawan } = useRealtime()
const can = (p) => auth.hasPermission(p)
const loans = ref([]); const employees = ref([])
const loading = ref(false); const saving = ref(false)
const employeeFilter = ref(''); const statusFilter = ref('active')
const meta = ref({ total: 0, page: 1, last_page: 1 })
const form = ref({ employee_id: '', loan_amount: 0, monthly_deduction: 0, total_installments: 0, start_date: '', notes: '' })
let modal = null

onMounted(async () => {
  modal = new Modal(document.getElementById('loanModal'))
  const r = await axios.get('/employees')
  employees.value = r.data.data || r.data
  load()
  listenPinjamanKaryawan(() => load())
})

onUnmounted(() => stopPinjamanKaryawan())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/payroll/loans', { params: { employee_id: employeeFilter.value, status: statusFilter.value, page: meta.value.page } })
    loans.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}
function changePage(p) { meta.value.page = p; load() }
function openModal() {
  form.value = { employee_id: '', loan_amount: 0, monthly_deduction: 0, total_installments: 0, start_date: '', notes: '' }
  modal.show()
}
function calcInstallment() {
  if (form.value.loan_amount > 0 && form.value.monthly_deduction > 0) {
    form.value.total_installments = Math.ceil(form.value.loan_amount / form.value.monthly_deduction)
  }
}
async function save() {
  saving.value = true
  try {
    await axios.post('/payroll/loans', form.value)
    toast.success('Pinjaman berhasil dicatat')
    modal.hide(); load()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
</script>