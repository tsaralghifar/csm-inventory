<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Histori Mutasi Barang</h5>
        <small class="text-muted">Riwayat lengkap semua pergerakan barang</small>
      </div>
    </div>

    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small mb-1">Gudang</label>
            <select v-model="filters.warehouse_id" class="form-select form-select-sm" @change="load">
              <option value="">Semua Gudang</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Jenis</label>
            <select v-model="filters.type" class="form-select form-select-sm" @change="load">
              <option value="">Semua Jenis</option>
              <option value="in">Masuk</option>
              <option value="out">Keluar</option>
              <option value="transfer_out">Transfer Keluar</option>
              <option value="transfer_in">Transfer Masuk</option>
              <option value="opname">Opname</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Dari Tanggal</label>
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="load" />
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Sampai</label>
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="load" />
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary btn-sm" @click="resetFilters">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>Ref No</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Barang</th>
                <th>Dari Gudang</th>
                <th>Ke Gudang</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Stok Sebelum</th>
                <th class="text-end">Stok Sesudah</th>
                <th>Oleh</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!movements.length"><td colspan="11" class="text-center text-muted py-4">Tidak ada data</td></tr>
              <tr v-for="m in movements" :key="m.id">
                <td><code class="text-primary small">{{ m.reference_no }}</code></td>
                <td><small>{{ $formatDate(m.movement_date) }}</small></td>
                <td>
                  <span :class="typeClass(m.type)" class="badge">{{ typeLabel(m.type) }}</span>
                </td>
                <td>
                  <div class="fw-semibold small">{{ m.item?.name }}</div>
                  <small class="text-muted">{{ m.item?.part_number }}</small>
                </td>
                <td><small class="text-muted">{{ m.from_warehouse?.name || '-' }}</small></td>
                <td><small class="text-muted">{{ m.to_warehouse?.name || '-' }}</small></td>
                <td class="text-end fw-bold">{{ $formatNumber(m.qty) }}</td>
                <td class="text-end text-muted small">{{ $formatNumber(m.qty_before) }}</td>
                <td class="text-end">
                  <span :class="parseFloat(m.qty_after) < 0 ? 'stock-minus' : 'fw-semibold'">{{ $formatNumber(m.qty_after) }}</span>
                </td>
                <td><small class="text-muted">{{ m.creator?.name }}</small></td>
                <td class="text-muted small" style="max-width:150px;">{{ m.notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">{{ meta.total }} record, hal. {{ meta.page }}/{{ meta.last_page }}</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { useAuthStore } from '@/store/auth'
import dayjs from 'dayjs'

const auth = useAuthStore()
const { listenStok, stopStok } = useRealtime()
const movements = ref([]); const warehouses = ref([]); const loading = ref(false)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ warehouse_id: '', type: '', date_from: dayjs().startOf('month').format('YYYY-MM-DD'), date_to: dayjs().format('YYYY-MM-DD') })

onMounted(async () => {
  const r = await axios.get('/warehouses'); warehouses.value = r.data.data
  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) {
    filters.value.warehouse_id = auth.userWarehouseId
  }
  load()
  listenStok(() => load())
})
onUnmounted(() => stopStok())

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/stock-movements', { params: { ...filters.value, page: meta.value.page, per_page: 20 } })
    movements.value = r.data.data; meta.value = r.data.meta
  } finally { loading.value = false }
}
function changePage(p) { meta.value.page = p; load() }
function resetFilters() {
  filters.value = { warehouse_id: '', type: '', date_from: dayjs().startOf('month').format('YYYY-MM-DD'), date_to: dayjs().format('YYYY-MM-DD') }
  meta.value.page = 1; load()
}
function typeLabel(t) {
  const m = { in:'Stok Masuk', out:'Stok Keluar', transfer_out:'Transfer Out', transfer_in:'Transfer In', adjustment:'Penyesuaian', opname:'Opname' }
  return m[t] || t
}
function typeClass(t) {
  if (t === 'in' || t === 'transfer_in') return 'bg-success'
  if (t === 'out' || t === 'transfer_out') return 'bg-warning text-dark'
  return 'bg-secondary'
}
</script>