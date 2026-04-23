<template>
  <div>
    <h5 class="fw-bold mb-3" style="color:#1a3a5c;">Laporan Mutasi / Pergerakan Barang</h5>
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <select v-model="params.warehouse_id" class="form-select form-select-sm">
              <option value="">Semua Gudang</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="params.type" class="form-select form-select-sm">
              <option value="">Semua Jenis</option>
              <option value="in">Masuk</option>
              <option value="out">Keluar</option>
              <option value="transfer_out">Transfer Keluar</option>
              <option value="transfer_in">Transfer Masuk</option>
            </select>
          </div>
          <div class="col-md-2"><input v-model="params.date_from" type="date" class="form-control form-control-sm" /></div>
          <div class="col-md-2"><input v-model="params.date_to" type="date" class="form-control form-control-sm" /></div>
          <div class="col-md-2">
            <button class="btn btn-csm-primary btn-sm" @click="load" :disabled="loading">
              <span v-if="loading"><span class="csm-spinner me-1"></span></span>Tampilkan
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="csm-card" v-if="loaded">
      <div class="csm-card-header">
        <h6>{{ data.length }} Transaksi</h6>
        <button class="btn btn-sm btn-outline-success" @click="exportExcel"><i class="bi bi-file-earmark-excel me-1"></i>Export</button>
      </div>
      <div class="csm-card-body p-0">
        <div class="table-responsive">
          <table class="table csm-table mb-0">
            <thead><tr><th>Tanggal</th><th>Ref No</th><th>Jenis</th><th>Barang</th><th>Dari</th><th>Ke</th><th class="text-end">Qty</th><th class="text-end">Stok Sesudah</th><th>Oleh</th></tr></thead>
            <tbody>
              <tr v-if="!data.length"><td colspan="9" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="m in data" :key="m.id">
                <td><small>{{ $formatDate(m.movement_date) }}</small></td>
                <td><code class="text-primary" style="font-size:0.7rem;">{{ m.reference_no }}</code></td>
                <td><span :class="typeClass(m.type)" class="badge small">{{ typeLabel(m.type) }}</span></td>
                <td>
                  <div class="fw-semibold small">{{ m.item?.name }}</div>
                  <small class="text-muted">{{ m.item?.part_number }}</small>
                </td>
                <td><small class="text-muted">{{ m.from_warehouse?.name || '-' }}</small></td>
                <td><small class="text-muted">{{ m.to_warehouse?.name || '-' }}</small></td>
                <td class="text-end fw-semibold">{{ $formatNumber(m.qty) }}</td>
                <td class="text-end"><span :class="parseFloat(m.qty_after)<0?'stock-minus':'fw-semibold'">{{ $formatNumber(m.qty_after) }}</span></td>
                <td><small class="text-muted">{{ m.creator?.name }}</small></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">{{ meta.total }} data, hal. {{ meta.page }}/{{ meta.last_page }}</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page<=1" @click="changePage(meta.page-1)">‹</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page>=meta.last_page" @click="changePage(meta.page+1)">›</button>
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
const meta = ref({ total: 0, page: 1, last_page: 1 })
const params = ref({ warehouse_id: '', type: '', date_from: dayjs().startOf('month').format('YYYY-MM-DD'), date_to: dayjs().format('YYYY-MM-DD') })

onMounted(async () => {
  const r = await axios.get('/warehouses'); warehouses.value = r.data.data
  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) params.value.warehouse_id = auth.userWarehouseId
})

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/movements', { params: { ...params.value, page: meta.value.page, per_page: 50 } })
    data.value = r.data.data; meta.value = r.data.meta; loaded.value = true
  } finally { loading.value = false }
}
function changePage(p) { meta.value.page = p; load() }
function typeLabel(t) { const m = {in:'Masuk',out:'Keluar',transfer_out:'Transfer Out',transfer_in:'Transfer In',opname:'Opname',adjustment:'Adj'}; return m[t]||t }
function typeClass(t) { if(t==='in'||t==='transfer_in') return 'bg-success'; if(t==='out'||t==='transfer_out') return 'bg-warning text-dark'; return 'bg-secondary' }
function exportExcel() {
  const headers = ['Tanggal','Ref No','Jenis','Part Number','Nama Barang','Dari Gudang','Ke Gudang','Qty','Stok Sesudah','Oleh']
  const rows = data.value.map(m => [m.movement_date, m.reference_no, typeLabel(m.type), m.item?.part_number, m.item?.name, m.from_warehouse?.name||'', m.to_warehouse?.name||'', m.qty, m.qty_after, m.creator?.name])
  const csv = [headers, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n')
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\uFEFF'+csv],{type:'text/csv'})); a.download = 'laporan_mutasi.csv'; a.click()
  toast.success('Export berhasil')
}
</script>
