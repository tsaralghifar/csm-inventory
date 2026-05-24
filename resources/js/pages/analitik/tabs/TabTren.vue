<template>
  <div>
    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Cari Barang <span class="text-danger">*</span></label>
            <div class="position-relative">
              <input v-model="itemSearch" type="text" class="form-control form-control-sm"
                placeholder="Ketik nama / part number..."
                @input="searchItems" @focus="showDrop = true" @blur="hideDrop" />
              <ul v-if="showDrop && itemResults.length"
                class="list-group position-absolute w-100 shadow-sm" style="z-index:999;max-height:200px;overflow-y:auto;top:100%">
                <li v-for="i in itemResults" :key="i.id"
                  class="list-group-item list-group-item-action py-1 px-2 small"
                  style="cursor:pointer" @mousedown.prevent="selectItem(i)">
                  <strong>{{ i.part_number }}</strong> — {{ i.name }}
                </li>
              </ul>
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Dari</label>
            <input v-model="dateFrom" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Sampai</label>
            <input v-model="dateTo" type="date" class="form-control form-control-sm" />
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100" @click="load" :disabled="!selectedItem || loading">
              <span v-if="loading" class="csm-spinner me-1"></span>
              <i v-else class="bi bi-search me-1"></i>Tampilkan
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tidak ada item dipilih -->
    <div v-if="!selectedItem" class="csm-card">
      <div class="csm-card-body text-center py-5 text-muted">
        <i class="bi bi-search fs-2 d-block mb-2 opacity-25"></i>
        Pilih barang untuk melihat tren harga
      </div>
    </div>

    <template v-else-if="loaded">
      <!-- Stats Cards -->
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="fw-bold text-primary" style="font-size:1.1rem;">{{ $formatCurrency(stats.latest) }}</div>
              <div class="small text-muted">Harga Terakhir</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="fw-bold" style="font-size:1.1rem;">{{ $formatCurrency(stats.avg) }}</div>
              <div class="small text-muted">Rata-rata</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="fw-bold text-success" style="font-size:1.1rem;">{{ $formatCurrency(stats.min) }}</div>
              <div class="small text-muted">Harga Terendah</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="csm-card text-center">
            <div class="csm-card-body py-3">
              <div class="fw-bold text-danger" style="font-size:1.1rem;">{{ $formatCurrency(stats.max) }}</div>
              <div class="small text-muted">Harga Tertinggi</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart -->
      <div class="csm-card mb-3">
        <div class="csm-card-header">
          <h6 class="mb-0">Tren Harga — {{ selectedItemName }}</h6>
        </div>
        <div class="csm-card-body">
          <canvas ref="chartCanvas" height="100"></canvas>
        </div>
      </div>

      <!-- Tabel detail -->
      <div class="csm-card">
        <div class="csm-card-header"><h6 class="mb-0">Detail Riwayat Harga</h6></div>
        <div class="csm-card-body p-0">
          <table class="table table-sm csm-table mb-0">
            <thead>
              <tr><th>Tanggal</th><th>Harga Beli</th><th>Harga Sebelumnya</th><th class="text-end">Perubahan</th><th>Supplier</th><th>Referensi</th></tr>
            </thead>
            <tbody>
              <tr v-for="h in chartData" :key="h.date + h.supplier">
                <td><small>{{ $formatDate(h.date) }}</small></td>
                <td class="fw-semibold small">{{ $formatCurrency(h.price) }}</td>
                <td class="text-muted small">{{ h.change_pct !== 0 ? $formatCurrency(h.price / (1 + h.change_pct/100)) : '-' }}</td>
                <td class="text-end">
                  <span v-if="h.change_pct !== 0"
                    :class="h.change_pct > 0 ? 'text-danger' : 'text-success'"
                    class="small fw-semibold">
                    {{ h.change_pct > 0 ? '▲' : '▼' }} {{ Math.abs(h.change_pct).toFixed(1) }}%
                  </span>
                  <span v-else class="text-muted small">—</span>
                </td>
                <td><small class="text-muted">{{ h.supplier || '-' }}</small></td>
                <td><code class="text-muted" style="font-size:0.68rem;">{{ h.reference_no || '-' }}</code></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import axios from 'axios'
import Chart from 'chart.js/auto'

const itemSearch   = ref('')
const itemResults  = ref([])
const showDrop     = ref(false)
const selectedItem = ref(null)
const selectedItemName = ref('')
const dateFrom = ref(new Date(Date.now() - 180*24*60*60*1000).toISOString().slice(0,10))
const dateTo   = ref(new Date().toISOString().slice(0,10))
const loading  = ref(false)
const loaded   = ref(false)
const chartData = ref([])
const stats     = ref({})
const chartCanvas = ref(null)
let chartInstance = null

async function searchItems() {
  const q = itemSearch.value.trim()
  if (q.length < 1) { itemResults.value = []; return }
  try {
    const r = await axios.get('/items', { params: { search: q, per_page: 15 } })
    itemResults.value = r.data.data ?? r.data ?? []
    showDrop.value = itemResults.value.length > 0
  } catch {}
}

function selectItem(i) {
  selectedItem.value    = i.id
  selectedItemName.value = `${i.part_number} — ${i.name}`
  itemSearch.value      = selectedItemName.value
  showDrop.value        = false
}

function hideDrop() { setTimeout(() => showDrop.value = false, 150) }

async function load() {
  if (!selectedItem.value) return
  loading.value = true
  try {
    const r = await axios.get('/price-intelligence/trend', {
      params: { item_id: selectedItem.value, date_from: dateFrom.value, date_to: dateTo.value }
    })
    chartData.value = r.data.data.chart
    stats.value     = r.data.data.stats
    loaded.value    = true
    await nextTick()
    renderChart()
  } catch {} finally { loading.value = false }
}

function renderChart() {
  if (chartInstance) { chartInstance.destroy(); chartInstance = null }
  if (!chartCanvas.value || !chartData.value.length) return

  const labels = chartData.value.map(d => d.date)
  const prices = chartData.value.map(d => d.price)
  const avgs   = chartData.value.map(d => d.avg_price)

  const bgColors = chartData.value.map(d => {
    if (d.change_pct > 50)  return 'rgba(220,38,38,0.8)'
    if (d.change_pct > 20)  return 'rgba(249,115,22,0.8)'
    if (d.change_pct > 5)   return 'rgba(234,179,8,0.8)'
    if (d.change_pct < 0)   return 'rgba(34,197,94,0.8)'
    return 'rgba(26,58,92,0.7)'
  })

  chartInstance = new Chart(chartCanvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Harga Beli',
          data: prices,
          backgroundColor: bgColors,
          borderRadius: 4,
          order: 2,
        },
        {
          label: 'Harga Rata-rata',
          data: avgs,
          type: 'line',
          borderColor: '#1a3a5c',
          borderWidth: 2,
          borderDash: [4, 4],
          fill: false,
          pointRadius: 3,
          tension: 0.3,
          order: 1,
        },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const val = 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.raw)
              const point = chartData.value[ctx.dataIndex]
              if (ctx.datasetIndex === 0 && point?.change_pct) {
                const arah = point.change_pct > 0 ? '▲' : '▼'
                return `${val} (${arah} ${Math.abs(point.change_pct).toFixed(1)}%)`
              }
              return val
            },
          },
        },
      },
      scales: {
        y: {
          ticks: {
            callback: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v),
            font: { size: 10 },
          },
        },
        x: { ticks: { font: { size: 10 } } },
      },
    },
  })
}
</script>