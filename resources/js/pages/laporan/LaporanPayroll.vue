<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Laporan Payroll</h5>
        <small class="text-muted">Rekapitulasi gaji karyawan per periode</small>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="exportExcel" :disabled="!selectedPeriodId">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
      </button>
    </div>

    <!-- Pilih Periode -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Pilih Periode Penggajian</label>
            <select v-model="selectedPeriodId" class="form-select form-select-sm" @change="load">
              <option value="">-- Pilih Periode --</option>
              <option v-for="p in periods" :key="p.id" :value="p.id">
                {{ p.name }} — <span class="text-muted">{{ statusLabel(p.status) }}</span>
              </option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!selectedPeriodId" class="text-center text-muted py-5">
      <i class="bi bi-calendar3 fs-1 d-block mb-2 text-muted"></i>
      Pilih periode penggajian untuk melihat laporan
    </div>

    <div v-else-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>

    <div v-else>
      <!-- Ringkasan Periode -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="text-muted small">Total Karyawan</div>
              <div class="fw-bold fs-5 text-primary">{{ items.length }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="text-muted small">Total Gaji Kotor</div>
              <div class="fw-bold fs-6 text-info">{{ $formatCurrency(totalGross) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="text-muted small">Total Potongan</div>
              <div class="fw-bold fs-6 text-danger">-{{ $formatCurrency(totalDeduction) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="text-muted small">Total Gaji Bersih</div>
              <div class="fw-bold fs-5 text-success">{{ $formatCurrency(totalNet) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabel Detail Gaji -->
      <div class="csm-card">
        <div class="csm-card-body p-0">
          <div class="table-responsive">
            <table class="table csm-table mb-0" style="font-size:13px;">
              <thead>
                <tr>
                  <th>Karyawan</th>
                  <th class="text-end">Gaji Pokok</th>
                  <th class="text-end">Tunjangan</th>
                  <th class="text-end">Bonus</th>
                  <th class="text-end">THR</th>
                  <th class="text-end">Lembur</th>
                  <th class="text-end text-success">Gaji Kotor</th>
                  <th class="text-end">BPJS TK</th>
                  <th class="text-end">BPJS Kes</th>
                  <th class="text-end">Pinjaman</th>
                  <th class="text-end">Potongan Lain</th>
                  <th class="text-end text-danger">Total Potong</th>
                  <th class="text-end text-success">Gaji Bersih</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!items.length">
                  <td colspan="13" class="text-center text-muted py-4">Belum ada data gaji</td>
                </tr>
                <tr v-for="item in items" :key="item.id">
                  <td>
                    <div class="fw-semibold">{{ item.employee?.name }}</div>
                    <div class="text-muted" style="font-size:11px;">{{ item.employee?.position || '-' }}</div>
                  </td>
                  <td class="text-end">{{ $formatCurrency(item.basic_salary) }}</td>
                  <td class="text-end text-info">
                    {{ $formatCurrency((+item.allowance_transport||0)+(+item.allowance_meal||0)+(+item.allowance_position||0)+(+item.allowance_other||0)) }}
                  </td>
                  <td class="text-end">{{ $formatCurrency(item.bonus || 0) }}</td>
                  <td class="text-end">{{ $formatCurrency(item.thr || 0) }}</td>
                  <td class="text-end">{{ $formatCurrency(item.overtime || 0) }}</td>
                  <td class="text-end fw-semibold text-success">{{ $formatCurrency(item.gross_salary) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(item.deduction_bpjs_tk || 0) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(item.deduction_bpjs_kes || 0) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(item.deduction_loan || 0) }}</td>
                  <td class="text-end text-danger">
                    {{ $formatCurrency((+item.deduction_pph21||0)+(+item.deduction_fine||0)+(+item.deduction_other||0)) }}
                  </td>
                  <td class="text-end fw-semibold text-danger">{{ $formatCurrency(item.total_deduction) }}</td>
                  <td class="text-end fw-bold text-success">{{ $formatCurrency(item.net_salary) }}</td>
                </tr>
              </tbody>
              <tfoot class="table-light fw-bold">
                <tr>
                  <td>TOTAL ({{ items.length }} karyawan)</td>
                  <td class="text-end">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.basic_salary),0)) }}</td>
                  <td class="text-end text-info">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.allowance_transport||0)+(+i.allowance_meal||0)+(+i.allowance_position||0)+(+i.allowance_other||0),0)) }}</td>
                  <td class="text-end">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.bonus||0),0)) }}</td>
                  <td class="text-end">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.thr||0),0)) }}</td>
                  <td class="text-end">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.overtime||0),0)) }}</td>
                  <td class="text-end text-success">{{ $formatCurrency(totalGross) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.deduction_bpjs_tk||0),0)) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.deduction_bpjs_kes||0),0)) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.deduction_loan||0),0)) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(items.reduce((a,i)=>a+(+i.deduction_pph21||0)+(+i.deduction_fine||0)+(+i.deduction_other||0),0)) }}</td>
                  <td class="text-end text-danger">{{ $formatCurrency(totalDeduction) }}</td>
                  <td class="text-end text-success fs-6">{{ $formatCurrency(totalNet) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const toast = useToast()
const periods = ref([]); const items = ref([])
const loading = ref(false); const selectedPeriodId = ref('')

const totalGross     = computed(() => items.value.reduce((a, i) => a + parseFloat(i.gross_salary || 0), 0))
const totalDeduction = computed(() => items.value.reduce((a, i) => a + parseFloat(i.total_deduction || 0), 0))
const totalNet       = computed(() => items.value.reduce((a, i) => a + parseFloat(i.net_salary || 0), 0))

onMounted(async () => {
  const r = await axios.get('/payroll/periods')
  periods.value = r.data.data
  // Otomatis pilih periode terbaru
  if (periods.value.length) {
    selectedPeriodId.value = periods.value[0].id
    load()
  }
})

async function load() {
  if (!selectedPeriodId.value) return
  loading.value = true
  try {
    const r = await axios.get(`/payroll/periods/${selectedPeriodId.value}`)
    items.value = r.data.data.items || []
  } finally { loading.value = false }
}

function statusLabel(s) {
  return { draft: 'Draft', processing: 'Diproses', approved: 'Disetujui', paid: 'Dibayar' }[s] || s
}

function exportExcel() {
  toast.info('Fitur export Excel akan segera tersedia')
}
</script>
