<template>
  <div>
    <h5 class="fw-bold mb-3" style="color:#1a3a5c;">Laporan Pengeluaran Barang</h5>
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small mb-1">Gudang <span class="text-danger">*</span></label>
            <select v-model="params.warehouse_id" class="form-select form-select-sm">
              <option value="">-- Pilih Gudang --</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Dari Tanggal</label>
            <input v-model="params.date_from" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Sampai</label>
            <input v-model="params.date_to" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-md-2">
            <button class="btn btn-csm-primary btn-sm" @click="load" :disabled="!params.warehouse_id || loading">
              <span v-if="loading"><span class="csm-spinner me-1"></span></span>Tampilkan
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loaded">
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="csm-card text-center py-3"><div class="fw-bold text-primary" style="font-size:1.5rem;">{{ summary.total_records }}</div><small class="text-muted">Total Transaksi</small></div></div>
        <div class="col-md-4"><div class="csm-card text-center py-3"><div class="fw-bold text-danger" style="font-size:1.5rem;">{{ $formatNumber(summary.total_qty) }}</div><small class="text-muted">Total Kuantitas</small></div></div>
        <div class="col-md-4"><div class="csm-card text-center py-3"><div class="fw-bold text-success" style="font-size:1.4rem;">{{ $formatCurrency(summary.total_value) }}</div><small class="text-muted">Total Nilai</small></div></div>
      </div>

      <div class="csm-card">
        <div class="csm-card-header">
          <h6>Laporan Pengeluaran</h6>
          <button class="btn btn-sm btn-outline-success" @click="exportExcel"><i class="bi bi-file-earmark-excel me-1"></i>Export</button>
        </div>
        <div class="csm-card-body p-0">
          <div class="table-responsive">
            <table class="table csm-table mb-0">
              <thead>
                <tr><th>Tanggal</th><th>Ref No</th><th>Part Number</th><th>Nama Barang</th><th>Unit</th><th>Type</th><th>HM</th><th>Mekanik</th><th>Site</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Total</th></tr>
              </thead>
              <tbody>
                <tr v-if="!data.length"><td colspan="13" class="text-center text-muted py-4">Tidak ada data</td></tr>
                <tr v-for="m in data" :key="m.id">
                  <td><small>{{ $formatDate(m.movement_date) }}</small></td>
                  <td><code class="text-primary" style="font-size:0.7rem;">{{ m.reference_no }}</code></td>
                  <td><code class="small">{{ m.item?.part_number }}</code></td>
                  <td class="fw-semibold small">{{ m.item?.name }}</td>
                  <td><small>{{ m.unit_code || '-' }}</small></td>
                  <td><small>{{ m.unit_type || '-' }}</small></td>
                  <td class="text-end"><small>{{ m.hm_km ? $formatNumber(m.hm_km) : '-' }}</small></td>
                  <td><small>{{ m.mechanic || '-' }}</small></td>
                  <td><small>{{ m.site_name || m.from_warehouse?.name || '-' }}</small></td>
                  <td class="text-end fw-semibold">{{ $formatNumber(m.qty) }}</td>
                  <td><small>{{ m.item?.unit }}</small></td>
                  <td class="text-end small">{{ m.price > 0 ? $formatCurrency(m.price) : '-' }}</td>
                  <td class="text-end small">{{ m.price > 0 ? $formatCurrency(m.qty * m.price) : '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import dayjs from 'dayjs'

const auth = useAuthStore(); const toast = useToast()
const warehouses = ref([]); const data = ref([]); const loading = ref(false); const loaded = ref(false)
const summary = ref({ total_records: 0, total_qty: 0, total_value: 0 })
const params = ref({ warehouse_id: '', date_from: dayjs().startOf('month').format('YYYY-MM-DD'), date_to: dayjs().format('YYYY-MM-DD') })

onMounted(async () => {
  const r = await axios.get('/warehouses'); warehouses.value = r.data.data
  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) params.value.warehouse_id = auth.userWarehouseId
})

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/pengeluaran', { params: params.value })
    data.value = r.data.data; summary.value = r.data.summary; loaded.value = true
  } finally { loading.value = false }
}

function exportExcel() {
  const headers = ['Tanggal','Ref No','Part Number','Nama Barang','Unit','Type Unit','HM/KM','Mekanik','Site','Qty','Satuan','Harga','Total']
  const rows = data.value.map(m => [m.movement_date, m.reference_no, m.item?.part_number, m.item?.name, m.unit_code||'', m.unit_type||'', m.hm_km||'', m.mechanic||'', m.site_name||'', m.qty, m.item?.unit, m.price||0, m.qty*(m.price||0)])
  const csv = [headers, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n')
  const blob = new Blob(['\uFEFF'+csv], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'laporan_pengeluaran.csv'; a.click()
  toast.success('Export berhasil')
}
</script>
