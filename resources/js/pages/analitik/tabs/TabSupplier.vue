<template>
  <div>
    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-5">
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
            <button class="btn btn-primary btn-sm w-100" @click="load" :disabled="!selectedItem || loading">
              <span v-if="loading" class="csm-spinner me-1"></span>
              <i v-else class="bi bi-search me-1"></i>Tampilkan
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="!selectedItem" class="csm-card">
      <div class="csm-card-body text-center py-5 text-muted">
        <i class="bi bi-building fs-2 d-block mb-2 opacity-25"></i>
        Pilih barang untuk membandingkan harga antar supplier
      </div>
    </div>

    <template v-else-if="loaded">
      <!-- Tidak ada data supplier -->
      <div v-if="!suppliers.length" class="csm-card">
        <div class="csm-card-body text-center py-5 text-muted">
          <i class="bi bi-building fs-2 d-block mb-2 opacity-25"></i>
          <div>Belum ada data perbandingan supplier untuk barang ini.</div>
          <small>Data akan muncul setelah ada riwayat pembelian dari beberapa supplier berbeda.</small>
        </div>
      </div>

      <template v-else>
      <!-- Kartu per supplier -->
      <div class="row g-3 mb-3">
        <div v-for="s in suppliers" :key="s.supplier" class="col-md-4">
          <div class="csm-card h-100"
            :style="s.is_cheapest ? 'border:2px solid #16a34a;' : ''">
            <div class="csm-card-body">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0 fw-bold small">{{ s.supplier }}</h6>
                <span v-if="s.is_cheapest" class="badge bg-success" style="font-size:0.65rem;">
                  <i class="bi bi-star-fill me-1"></i>Termurah
                </span>
              </div>
              <div class="fs-5 fw-bold" :class="s.is_cheapest ? 'text-success' : 'text-primary'">
                {{ $formatCurrency(s.latest_price) }}
              </div>
              <div class="text-muted small">Harga terakhir — {{ $formatDate(s.last_date) }}</div>
              <hr class="my-2">
              <div class="row g-1 text-center">
                <div class="col-4">
                  <div class="small text-success fw-semibold">{{ $formatCurrency(s.min_price) }}</div>
                  <div style="font-size:0.65rem;" class="text-muted">Terendah</div>
                </div>
                <div class="col-4">
                  <div class="small fw-semibold">{{ $formatCurrency(s.avg_price) }}</div>
                  <div style="font-size:0.65rem;" class="text-muted">Rata-rata</div>
                </div>
                <div class="col-4">
                  <div class="small text-danger fw-semibold">{{ $formatCurrency(s.max_price) }}</div>
                  <div style="font-size:0.65rem;" class="text-muted">Tertinggi</div>
                </div>
              </div>
              <div class="mt-2 text-muted" style="font-size:0.7rem;">
                {{ s.total_orders }} kali pembelian
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Grafik perbandingan -->
      <div class="csm-card">
        <div class="csm-card-header">
          <h6 class="mb-0">Tren Harga per Supplier</h6>
        </div>
        <div class="csm-card-body">
          <canvas ref="chartCanvas" height="100"></canvas>
        </div>
      </div>
      </template>
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
const loading  = ref(false)
const loaded   = ref(false)
const suppliers = ref([])
const chartCanvas = ref(null)
let chartInstance = null

const COLORS = ['#1a3a5c','#16a34a','#dc2626','#f59e0b','#8b5cf6','#0891b2']

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
  selectedItem.value = i.id
  itemSearch.value   = `${i.part_number} — ${i.name}`
  showDrop.value     = false
}

function hideDrop() { setTimeout(() => showDrop.value = false, 150) }

async function load() {
  if (!selectedItem.value) return
  loading.value = true
  try {
    const r = await axios.get('/price-intelligence/supplier-comparison', {
      params: { item_id: selectedItem.value }
    })
    suppliers.value = r.data.data.suppliers
    loaded.value    = true
    await nextTick()
    renderChart()
  } catch {} finally { loading.value = false }
}

function renderChart() {
  if (chartInstance) { chartInstance.destroy(); chartInstance = null }
  if (!chartCanvas.value || !suppliers.value.length) return

  // Kumpulkan semua tanggal unik
  const allDates = [...new Set(
    suppliers.value.flatMap(s => s.data.map(d => d.date))
  )].sort()

  const datasets = suppliers.value.map((s, i) => {
    const dataMap = Object.fromEntries(s.data.map(d => [d.date, d.price]))
    return {
      label: s.supplier,
      data: allDates.map(d => dataMap[d] ?? null),
      borderColor: COLORS[i % COLORS.length],
      backgroundColor: COLORS[i % COLORS.length] + '22',
      fill: false,
      tension: 0.3,
      pointRadius: 4,
      spanGaps: true,
    }
  })

  chartInstance = new Chart(chartCanvas.value, {
    type: 'line',
    data: { labels: allDates, datasets },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.dataset.label}: Rp ${new Intl.NumberFormat('id-ID').format(ctx.raw)}`,
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
</script>