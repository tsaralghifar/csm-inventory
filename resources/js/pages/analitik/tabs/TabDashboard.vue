<template>
  <div>
    <!-- Loading -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" style="width:2rem;height:2rem;"></div>
      <div class="text-muted small mt-2">Memuat data...</div>
    </div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
          <div class="csm-card h-100 text-center">
            <div class="csm-card-body py-3">
              <div class="fs-2 fw-bold" style="color:#1a3a5c;">{{ data.total_changes ?? 0 }}</div>
              <div class="small text-muted">Perubahan Harga (30 hari)</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="csm-card h-100 text-center" :style="criticalCount > 0 ? 'border-left:4px solid #dc2626' : ''">
            <div class="csm-card-body py-3">
              <div class="fs-2 fw-bold text-danger">{{ criticalCount }}</div>
              <div class="small text-muted">Kenaikan Kritis</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="csm-card h-100 text-center" :style="data.unread_anomalies > 0 ? 'border-left:4px solid #f59e0b' : ''">
            <div class="csm-card-body py-3">
              <div class="fs-2 fw-bold text-warning">{{ data.unread_anomalies ?? 0 }}</div>
              <div class="small text-muted">Anomali Belum Dibaca</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="csm-card h-100 text-center">
            <div class="csm-card-body py-3">
              <div class="fs-2 fw-bold text-success">{{ (data.by_severity?.down ?? 0) }}</div>
              <div class="small text-muted">Harga Turun (30 hari)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Kenaikan & Penurunan -->
      <div class="row g-3 mb-3">
        <!-- Top Kenaikan -->
        <div class="col-md-6">
          <div class="csm-card h-100">
            <div class="csm-card-header d-flex align-items-center justify-content-between">
              <h6 class="mb-0"><i class="bi bi-arrow-up-circle text-danger me-2"></i>Top 5 Kenaikan Harga Bulan Ini</h6>
            </div>
            <div class="csm-card-body p-0">
              <div v-if="!data.top_increases?.length" class="text-center text-muted py-4 small">
                Tidak ada kenaikan harga bulan ini
              </div>
              <table v-else class="table table-sm csm-table mb-0">
                <thead><tr><th>Barang</th><th class="text-end">Sebelum</th><th class="text-end">Sesudah</th><th class="text-end">Δ%</th></tr></thead>
                <tbody>
                  <tr v-for="item in data.top_increases" :key="item.item_id"
                    style="cursor:pointer" @click="$emit('go-tab','tren'); selectedItem = item.item_id">
                    <td>
                      <div class="small fw-semibold">{{ item.item_name }}</div>
                      <code class="text-muted" style="font-size:0.68rem;">{{ item.part_number }}</code>
                    </td>
                    <td class="text-end small text-muted">{{ $formatCurrency(item.prev_price) }}</td>
                    <td class="text-end small fw-semibold">{{ $formatCurrency(item.new_price) }}</td>
                    <td class="text-end">
                      <span :class="severityBadgeClass(item.severity)" class="badge" style="font-size:0.65rem;">
                        ▲ {{ Math.abs(item.change_pct).toFixed(1) }}%
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Top Penurunan -->
        <div class="col-md-6">
          <div class="csm-card h-100">
            <div class="csm-card-header d-flex align-items-center justify-content-between">
              <h6 class="mb-0"><i class="bi bi-arrow-down-circle text-success me-2"></i>Top 5 Penurunan Harga Bulan Ini</h6>
            </div>
            <div class="csm-card-body p-0">
              <div v-if="!data.top_decreases?.length" class="text-center text-muted py-4 small">
                Tidak ada penurunan harga bulan ini
              </div>
              <table v-else class="table table-sm csm-table mb-0">
                <thead><tr><th>Barang</th><th class="text-end">Sebelum</th><th class="text-end">Sesudah</th><th class="text-end">Δ%</th></tr></thead>
                <tbody>
                  <tr v-for="item in data.top_decreases" :key="item.item_id">
                    <td>
                      <div class="small fw-semibold">{{ item.item_name }}</div>
                      <code class="text-muted" style="font-size:0.68rem;">{{ item.part_number }}</code>
                    </td>
                    <td class="text-end small text-muted">{{ $formatCurrency(item.prev_price) }}</td>
                    <td class="text-end small fw-semibold text-success">{{ $formatCurrency(item.new_price) }}</td>
                    <td class="text-end">
                      <span class="badge bg-success" style="font-size:0.65rem;">
                        ▼ {{ Math.abs(item.change_pct).toFixed(1) }}%
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Anomali Terbaru -->
      <div class="csm-card">
        <div class="csm-card-header d-flex align-items-center justify-content-between">
          <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Anomali Terbaru</h6>
          <button class="btn btn-sm btn-outline-primary" @click="$emit('go-tab','anomali')">
            Lihat Semua
          </button>
        </div>
        <div class="csm-card-body p-0">
          <div v-if="!data.recent_anomalies?.length" class="text-center text-muted py-4 small">
            Tidak ada anomali
          </div>
          <table v-else class="table table-sm csm-table mb-0">
            <thead>
              <tr><th>Waktu</th><th>Tipe</th><th>Barang</th><th>Keterangan</th><th class="text-center">Status</th></tr>
            </thead>
            <tbody>
              <tr v-for="a in data.recent_anomalies" :key="a.id"
                :class="!a.is_read ? 'table-warning bg-opacity-25' : ''">
                <td><small class="text-muted">{{ a.created_at }}</small></td>
                <td>
                  <span :class="anomalyTypeClass(a.type)" class="badge" style="font-size:0.65rem;">
                    {{ a.type_label }}
                  </span>
                </td>
                <td>
                  <div class="small fw-semibold">{{ a.item_name }}</div>
                  <code class="text-muted" style="font-size:0.65rem;">{{ a.part_number }}</code>
                </td>
                <td class="small text-muted">
                  <span v-if="a.change_pct">
                    {{ a.change_pct > 0 ? '▲' : '▼' }} {{ Math.abs(a.change_pct).toFixed(1) }}%
                    {{ a.supplier ? '· ' + a.supplier : '' }}
                  </span>
                </td>
                <td class="text-center">
                  <span v-if="!a.is_read" class="badge bg-warning text-dark" style="font-size:0.6rem;">Baru</span>
                  <span v-else class="text-muted" style="font-size:0.7rem;">Dibaca</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const emit = defineEmits(['go-tab'])
const loading = ref(true)
const data    = ref({})

const criticalCount = computed(() =>
  (data.value.by_severity?.up_critical ?? 0) +
  (data.value.by_severity?.up_high ?? 0)
)

function severityBadgeClass(severity) {
  return {
    'up_critical': 'bg-danger',
    'up_high':     'bg-orange text-white',
    'up_low':      'bg-warning text-dark',
    'down':        'bg-success',
  }[severity] ?? 'bg-secondary'
}

function anomalyTypeClass(type) {
  return {
    'price_spike':          'bg-danger',
    'consecutive_increase': 'bg-warning text-dark',
    'po_vs_receive':        'bg-info text-dark',
    'budget_exceeded':      'bg-orange text-white',
  }[type] ?? 'bg-secondary'
}

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/price-intelligence/dashboard')
    data.value = r.data.data
  } catch {} finally {
    loading.value = false
  }
}

onMounted(load)
</script>
