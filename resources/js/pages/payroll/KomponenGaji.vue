<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Komponen Gaji Karyawan</h5>
        <small class="text-muted">Setting gaji pokok dan tunjangan default per karyawan</small>
      </div>
    </div>

    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-4">
            <input v-model="search" class="form-control form-control-sm" placeholder="🔍 Cari nama karyawan..." @input="debouncedFilter" />
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12" v-if="loading"><div class="p-4 text-center"><div class="csm-spinner"></div></div></div>
      <div class="col-md-6 col-lg-4" v-for="emp in filteredEmployees" :key="emp.id">
        <div class="csm-card h-100">
          <div class="csm-card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold"
                style="width:38px;height:38px;font-size:14px;">
                {{ emp.name.charAt(0).toUpperCase() }}
              </div>
              <div>
                <div class="fw-semibold small">{{ emp.name }}</div>
                <div class="text-muted" style="font-size:11px;">{{ emp.position || '-' }}</div>
              </div>
            </div>

            <div v-if="components[emp.id]" class="small">
              <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                <span class="text-muted">Gaji Pokok</span>
                <span class="fw-semibold">{{ $formatCurrency(components[emp.id].basic_salary) }}</span>
              </div>
              <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                <span class="text-muted">Tunjangan Total</span>
                <span class="text-info fw-semibold">
                  {{ $formatCurrency(
                    (+components[emp.id].allowance_transport||0) +
                    (+components[emp.id].allowance_meal||0) +
                    (+components[emp.id].allowance_position||0) +
                    (+components[emp.id].allowance_other||0)
                  ) }}
                </span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted">Potongan BPJS</span>
                <span class="text-danger fw-semibold">
                  -{{ $formatCurrency((+components[emp.id].deduction_bpjs_tk||0) + (+components[emp.id].deduction_bpjs_kes||0)) }}
                </span>
              </div>
            </div>
            <div v-else class="text-muted small text-center py-2">
              <i class="bi bi-exclamation-circle me-1"></i>Belum ada komponen gaji
            </div>

            <button class="btn btn-outline-primary btn-sm w-100 mt-3" @click="openEdit(emp)">
              <i class="bi bi-pencil me-1"></i>{{ components[emp.id] ? 'Edit Komponen' : 'Set Komponen' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Edit Komponen -->
    <div class="modal fade" id="componentModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedEmployee">
          <div class="modal-header">
            <h5 class="modal-title">Komponen Gaji — {{ selectedEmployee.name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <h6 class="fw-bold text-primary border-bottom pb-1 mb-2"><i class="bi bi-cash me-1"></i>Gaji & Tunjangan</h6>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Gaji Pokok <span class="text-danger">*</span></label>
                <input v-model.number="compForm.basic_salary" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Tunjangan Transport</label>
                <input v-model.number="compForm.allowance_transport" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Tunjangan Makan</label>
                <input v-model.number="compForm.allowance_meal" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Tunjangan Jabatan</label>
                <input v-model.number="compForm.allowance_position" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Tunjangan Lain</label>
                <input v-model.number="compForm.allowance_other" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-12 mt-2">
                <h6 class="fw-bold text-danger border-bottom pb-1 mb-2"><i class="bi bi-dash-circle me-1"></i>Potongan Tetap</h6>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">BPJS Ketenagakerjaan</label>
                <input v-model.number="compForm.deduction_bpjs_tk" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">BPJS Kesehatan</label>
                <input v-model.number="compForm.deduction_bpjs_kes" type="number" min="0" class="form-control form-control-sm" />
              </div>
              <!-- Ringkasan -->
              <div class="col-12">
                <div class="alert alert-light border mt-2 small">
                  <div class="row">
                    <div class="col-md-4">
                      <span class="text-muted">Total Gaji + Tunjangan:</span>
                      <strong class="d-block text-success">{{ $formatCurrency(grossTotal) }}</strong>
                    </div>
                    <div class="col-md-4">
                      <span class="text-muted">Total Potongan Tetap:</span>
                      <strong class="d-block text-danger">-{{ $formatCurrency(deductionTotal) }}</strong>
                    </div>
                    <div class="col-md-4">
                      <span class="text-muted">Estimasi Gaji Bersih:</span>
                      <strong class="d-block text-primary">{{ $formatCurrency(grossTotal - deductionTotal) }}</strong>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="saveComponent" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>Simpan Komponen
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
const { listenKomponenGaji, stopKomponenGaji } = useRealtime()
const employees = ref([]); const components = ref({})
const loading = ref(false); const saving = ref(false)
const search = ref(''); const selectedEmployee = ref(null)
const compForm = ref({ basic_salary: 0, allowance_transport: 0, allowance_meal: 0, allowance_position: 0, allowance_other: 0, deduction_bpjs_tk: 0, deduction_bpjs_kes: 0 })
let modal = null; let timer = null

const filteredEmployees = computed(() => {
  if (!search.value) return employees.value
  return employees.value.filter(e => e.name.toLowerCase().includes(search.value.toLowerCase()))
})
const grossTotal = computed(() => (compForm.value.basic_salary||0) + (compForm.value.allowance_transport||0) + (compForm.value.allowance_meal||0) + (compForm.value.allowance_position||0) + (compForm.value.allowance_other||0))
const deductionTotal = computed(() => (compForm.value.deduction_bpjs_tk||0) + (compForm.value.deduction_bpjs_kes||0))

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/employees')
    employees.value = r.data.data || r.data
    // Load semua komponen gaji
    await Promise.all(employees.value.map(async emp => {
      try {
        const c = await axios.get(`/payroll/salary-components/${emp.id}`)
        if (c.data.data?.basic_salary > 0) components.value[emp.id] = c.data.data
      } catch {}
    }))
  } finally { loading.value = false }
}

onMounted(async () => {
  modal = new Modal(document.getElementById('componentModal'))
  await load()
  listenKomponenGaji(() => load())
})

onUnmounted(() => stopKomponenGaji())

function debouncedFilter() { clearTimeout(timer); timer = setTimeout(() => {}, 300) }

async function openEdit(emp) {
  selectedEmployee.value = emp
  const existing = components.value[emp.id]
  compForm.value = existing
    ? { basic_salary: +existing.basic_salary, allowance_transport: +existing.allowance_transport, allowance_meal: +existing.allowance_meal, allowance_position: +existing.allowance_position, allowance_other: +existing.allowance_other, deduction_bpjs_tk: +existing.deduction_bpjs_tk, deduction_bpjs_kes: +existing.deduction_bpjs_kes }
    : { basic_salary: 0, allowance_transport: 0, allowance_meal: 0, allowance_position: 0, allowance_other: 0, deduction_bpjs_tk: 0, deduction_bpjs_kes: 0 }
  modal.show()
}

async function saveComponent() {
  saving.value = true
  try {
    const r = await axios.put(`/payroll/salary-components/${selectedEmployee.value.id}`, compForm.value)
    components.value[selectedEmployee.value.id] = r.data.data
    toast.success('Komponen gaji disimpan')
    modal.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal menyimpan') }
  finally { saving.value = false }
}
</script>