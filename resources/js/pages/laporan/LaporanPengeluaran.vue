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
                <tr>
                  <th>Tanggal</th>
                  <th>No. BON</th>
                  <th>Part Number</th>
                  <th>Nama Barang</th>
                  <th>Unit</th>
                  <th>Type</th>
                  <th>HM</th>
                  <th>Mekanik</th>
                  <th>Site</th>
                  <th class="text-end">Qty</th>
                  <th>Sat.</th>
                  <th class="text-end">Tgl. Beli</th>
                  <th class="text-end">Harga Beli</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!data.length">
                  <td colspan="14" class="text-center text-muted py-4">Tidak ada data</td>
                </tr>
                <template v-for="(m, idx) in data" :key="idx">
                  <tr :style="m.is_layer_row ? 'background:rgba(26,58,92,0.025)' : ''">
                    <td><small>{{ $formatDate(m.issue_date) }}</small></td>
                    <td>
                      <code class="text-primary" style="font-size:0.7rem;">{{ m.bon_number }}</code>
                    </td>
                    <td><code class="small">{{ m.item?.part_number }}</code></td>
                    <td class="fw-semibold small">
                      {{ m.nama_barang || m.item?.name }}
                      <span v-if="m.is_layer_row && data.filter(x=>x.bon_number===m.bon_number && x.item?.id===m.item?.id).length > 1"
                        class="badge ms-1" style="font-size:0.55rem;background:#1a3a5c;">
                        Batch
                      </span>
                    </td>
                    <td><small>{{ m.unit_code || '-' }}</small></td>
                    <td><small>{{ m.unit_type || '-' }}</small></td>
                    <td class="text-end"><small>{{ m.hm_km ? $formatNumber(m.hm_km) : '-' }}</small></td>
                    <td><small>{{ m.mechanic || '-' }}</small></td>
                    <td><small>{{ m.site_name || '-' }}</small></td>
                    <td class="text-end fw-semibold">{{ $formatNumber(m.qty) }}</td>
                    <td><small>{{ m.satuan }}</small></td>
                    <!-- Tgl. Beli dari layer -->
                    <td class="text-end small text-muted">
                      <span v-if="m.tanggal_masuk">{{ $formatDate(m.tanggal_masuk) }}</span>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <!-- Harga Beli per batch -->
                    <td class="text-end small">
                      <span v-if="m.harga_satuan > 0">
                        {{ $formatCurrency(m.harga_satuan) }}
                        <span v-if="m.is_fifo" class="badge bg-info text-dark ms-1" style="font-size:0.55rem;">FIFO</span>
                        <span v-else class="badge bg-secondary ms-1" style="font-size:0.55rem;">AVG</span>
                      </span>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td class="text-end small fw-semibold">
                      {{ m.nilai > 0 ? $formatCurrency(m.nilai) : '-' }}
                    </td>
                  </tr>
                </template>
              </tbody>
              <tfoot v-if="data.length">
                <tr style="background:rgba(26,58,92,0.06);font-weight:600;">
                  <td colspan="9" class="text-end small text-muted pe-3">Total:</td>
                  <td class="text-end">{{ $formatNumber(summary.total_qty) }}</td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td class="text-end text-success">{{ $formatCurrency(summary.total_value) }}</td>
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
  const headers = ['Tanggal','No. BON','Part Number','Nama Barang','Unit','Type Unit','HM/KM','Mekanik','Site','Qty','Satuan','Tgl. Beli','Harga Beli (FIFO)','Total','Metode']
  const rows = data.value.map(m => [
    m.issue_date,
    m.bon_number,
    m.item?.part_number || '',
    m.nama_barang || m.item?.name || '',
    m.unit_code || '',
    m.unit_type || '',
    m.hm_km || '',
    m.mechanic || '',
    m.site_name || '',
    m.qty,
    m.satuan || '',
    m.tanggal_masuk || '',
    m.harga_satuan || 0,
    m.nilai || 0,
    m.is_fifo ? 'FIFO' : 'AVG',
  ])
  const csv = [headers, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n')
  const blob = new Blob(['\uFEFF'+csv], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'laporan_pengeluaran.csv'; a.click()
  toast.success('Export berhasil')
}
</script>