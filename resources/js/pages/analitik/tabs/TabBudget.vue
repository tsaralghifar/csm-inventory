<template>
  <div>
    <!-- Summary Cards -->
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="csm-card text-center" :style="summary.is_exceeded ? 'border-left:4px solid #dc2626' : 'border-left:4px solid #16a34a'">
          <div class="csm-card-body py-3">
            <div class="fw-bold" :class="summary.is_exceeded ? 'text-danger' : 'text-success'" style="font-size:1.2rem;">
              {{ $formatCurrency(summary.this_month ?? 0) }}
            </div>
            <div class="small text-muted">Total Pembelian Bulan Ini</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="csm-card text-center">
          <div class="csm-card-body py-3">
            <div class="fw-bold text-primary" style="font-size:1.2rem;">
              {{ $formatCurrency(summary.avg_previous ?? 0) }}
            </div>
            <div class="small text-muted">Rata-rata Bulan Sebelumnya</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="csm-card text-center" :style="summary.is_exceeded ? 'border-left:4px solid #dc2626' : ''">
          <div class="csm-card-body py-3">
            <div class="fw-bold" :class="diffClass" style="font-size:1.2rem;">
              {{ summary.diff_pct > 0 ? '▲' : '▼' }} {{ Math.abs(summary.diff_pct ?? 0).toFixed(1) }}%
            </div>
            <div class="small text-muted">
              vs rata-rata
              <span v-if="summary.is_exceeded" class="badge bg-danger ms-1" style="font-size:0.6rem;">Melampaui Batas</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Alert jika exceeded -->
    <div v-if="summary.is_exceeded" class="alert alert-danger d-flex align-items-center gap-2 mb-3">
      <i class="bi bi-exclamation-triangle-fill fs-5"></i>
      <div>
        <strong>Budget Alert!</strong> Total pembelian bulan ini sudah
        <strong>{{ Math.abs(summary.diff_pct).toFixed(1) }}%</strong>
        di atas rata-rata {{ summary.avg_months }} bulan sebelumnya.
        Batas threshold: {{ summary.threshold }}%.
      </div>
    </div>

    <!-- Filter jumlah bulan -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2 d-flex align-items-center gap-3">
        <small class="text-muted fw-semibold">Tampilkan:</small>
        <div class="btn-group btn-group-sm">
          <button v-for="m in [6,12,24]" :key="m"
            class="btn" :class="months === m ? 'btn-primary' : 'btn-outline-secondary'"
            @click="months = m; load()">
            {{ m }} Bulan
          </button>
        </div>
      </div>
    </div>

    <!-- Chart -->
    <div class="csm-card mb-3">
      <div class="csm-card-header">
        <h6 class="mb-0">Total Pembelian per Bulan</h6>
      </div>
      <div class="csm-card-body">
        <div v-if="loading" class="text-center py-4">
          <div class="spinner-border text-primary spinner-border-sm"></div>
        </div>
        <canvas v-else ref="chartCanvas" height="100"></canvas>
      </div>
    </div>

    <!-- Tabel detail per bulan -->
    <div class="csm-card">
      <div class="csm-card-header"><h6 class="mb-0">Detail per Bulan</h6></div>
      <div class="csm-card-body p-0">
        <table class="table table-sm csm-table mb-0">
          <thead>
            <tr><th>Bulan</th><th class="text-end">Total Pembelian</th><th class="text-end">Rata-rata Prev</th><th class="text-end">Selisih</th></tr>
          </thead>
          <tbody>
            <tr v-for="row in chartRows" :key="row.ym"
              :class="row.is_current ? 'table-primary bg-opacity-25 fw-semibold' : ''">
              <td>
                {{ row.label }}
                <span v-if="row.is_current" class="badge bg-primary ms-1" style="font-size:0.6rem;">Bulan ini</span>
              </td>
              <td class="text-end">{{ row.total > 0 ? $formatCurrency(row.total) : '—' }}</td>
              <td class="text-end text-muted small">{{ row.avg_prev > 0 ? $formatCurrency(row.avg_prev) : '—' }}</td>
              <td class="text-end">
                <span v-if="row.avg_prev > 0 && row.total > 0"
                  :class="row.total > row.avg_prev ? 'text-danger' : 'text-success'"
                  class="small fw-semibold">
                  {{ row.total > row.avg_prev ? '▲' : '▼' }}
                  {{ Math.abs(((row.total - row.avg_prev) / row.avg_prev) * 100).toFixed(1) }}%
                </span>
                <span v-else class="text-muted small">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import axios from 'axios'
import Chart from 'chart.js/auto'

const loading   = ref(true)
const months    = ref(12)
const chartData = ref([])
const summary   = ref({})
const chartCanvas = ref(null)
let chartInstance = null

const thisYM = new Date().toISOString().slice(0, 7)

const chartRows = computed(() =>
  (chartData.value || []).map(r => ({ ...r, is_current: r.ym === thisYM }))
    .slice().reverse()
)

const diffClass = computed(() => {
  const d = summary.value.diff_pct ?? 0
  if (d > (summary.value.threshold ?? 20)) return 'text-danger'
  if (d > 0) return 'text-warning'
  return 'text-success'
})

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/price-intelligence/budget', { params: { months: months.value } })
    chartData.value = r.data.data?.chart ?? []
    summary.value   = r.data.data?.summary ?? {}
    await nextTick()
    renderChart()
  } catch (e) {
    console.error('Budget monitor error:', e)
  } finally {
    loading.value = false
  }
}

function renderChart() {
  if (chartInstance) { chartInstance.destroy(); chartInstance = null }
  if (!chartCanvas.value || !chartData.value.length) return

  const labels    = chartData.value.map(d => d.label)
  const totals    = chartData.value.map(d => d.total)
  const avgs      = chartData.value.map(d => d.avg_prev)
  const threshold = summary.value.threshold ?? 20
  const bgColors  = chartData.value.map(d => {
    if (d.ym === thisYM) return 'rgba(26,58,92,0.9)'
    if (d.avg_prev > 0 && ((d.total - d.avg_prev) / d.avg_prev * 100) >= threshold)
      return 'rgba(220,38,38,0.7)'
    return 'rgba(26,58,92,0.5)'
  })

  chartInstance = new Chart(chartCanvas.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Total Pembelian',
          data: totals,
          backgroundColor: bgColors,
          borderRadius: 4,
          order: 2,
        },
        {
          label: 'Rata-rata Sebelumnya',
          data: avgs,
          type: 'line',
          borderColor: '#f59e0b',
          borderWidth: 2,
          borderDash: [6, 3],
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
            label: (ctx) => `${ctx.dataset.label}: Rp ${new Intl.NumberFormat('id-ID').format(Math.round(ctx.raw))}`,
          },
        },
      },
      scales: {
        y: { ticks: { callback: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v), font: { size: 10 } } },
        x: { ticks: { font: { size: 10 } } },
      },
    },
  })
}

onMounted(load)
</script>