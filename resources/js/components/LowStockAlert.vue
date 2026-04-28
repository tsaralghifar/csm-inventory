<template>
  <!-- Banner stok menipis di atas dashboard — hanya tampil jika ada alert -->
  <transition name="alert-slide">
    <div v-if="visible && summary.counts.total > 0" class="low-stock-alert-banner mb-3">

      <!-- Header bar -->
      <div class="alert-bar d-flex align-items-center justify-content-between px-3 py-2">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:1.1rem;"></i>
          <span class="fw-semibold" style="color:#fff; font-size:0.9rem;">
            Peringatan Stok
          </span>

          <!-- Badge counts -->
          <span v-if="summary.counts.minus > 0" class="badge bg-danger ms-1">
            {{ summary.counts.minus }} Minus
          </span>
          <span v-if="summary.counts.critical > 0" class="badge bg-warning text-dark ms-1">
            {{ summary.counts.critical }} Kritis
          </span>
          <span v-if="summary.counts.low > 0" class="badge bg-info text-dark ms-1">
            {{ summary.counts.low }} Menipis
          </span>
        </div>

        <div class="d-flex align-items-center gap-2">
          <button
            class="btn btn-sm btn-outline-light py-0"
            style="font-size:0.75rem;"
            @click="expanded = !expanded"
          >
            <i :class="expanded ? 'bi-chevron-up' : 'bi-chevron-down'" class="bi me-1"></i>
            {{ expanded ? 'Sembunyikan' : 'Detail' }}
          </button>
          <button class="btn btn-link p-0 text-white-50" @click="visible = false">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>

      <!-- Expandable detail table -->
      <transition name="expand">
        <div v-if="expanded" class="alert-body">
          <div v-if="loading" class="text-center py-3">
            <div class="csm-spinner"></div>
          </div>
          <div v-else class="table-responsive">
            <table class="table table-sm mb-0" style="font-size:0.8rem;">
              <thead class="table-light">
                <tr>
                  <th>Barang</th>
                  <th>Gudang</th>
                  <th class="text-end">Stok</th>
                  <th class="text-end">Min</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in summary.items" :key="`${item.item_id}-${item.warehouse_id}`">
                  <td>
                    <div class="fw-semibold">{{ item.item_name }}</div>
                    <small class="text-muted">{{ item.part_number }}</small>
                  </td>
                  <td><small>{{ item.warehouse }}</small></td>
                  <td class="text-end">
                    <span :class="qtyClass(item.alert_level)">
                      {{ formatNum(item.qty) }} {{ item.unit }}
                    </span>
                  </td>
                  <td class="text-end text-muted">
                    <small>{{ formatNum(item.min_stock) }}</small>
                  </td>
                  <td>
                    <span class="badge rounded-pill" :class="badgeClass(item.alert_level)">
                      {{ levelLabel(item.alert_level) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Footer actions -->
          <div class="d-flex justify-content-end gap-2 p-2 border-top bg-light">
            <router-link to="/stok/ho" class="btn btn-sm btn-primary">
              <i class="bi bi-box-seam me-1"></i>Kelola Stok
            </router-link>
            <router-link to="/permintaan-material" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-clipboard-plus me-1"></i>Buat PM
            </router-link>
          </div>
        </div>
      </transition>
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const visible  = ref(true)
const expanded = ref(false)
const loading  = ref(false)

const summary = ref({
  items: [],
  counts: { minus: 0, critical: 0, low: 0, total: 0 },
})

async function fetchLowStock() {
  loading.value = true
  try {
    const res = await axios.get('/notifications/low-stock')
    summary.value = res.data.data ?? { items: [], counts: { minus: 0, critical: 0, low: 0, total: 0 } }
  } catch (e) {
    console.error('[LowStockAlert] fetch error:', e)
  } finally {
    loading.value = false
  }
}

function formatNum(val) {
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(val ?? 0)
}

function qtyClass(level) {
  return { minus: 'text-danger fw-bold', critical: 'text-warning fw-bold', low: 'text-info fw-semibold' }[level] ?? ''
}

function badgeClass(level) {
  return { minus: 'bg-danger', critical: 'bg-warning text-dark', low: 'bg-info text-dark' }[level] ?? 'bg-secondary'
}

function levelLabel(level) {
  return { minus: '⛔ Minus', critical: '🔴 Kritis', low: '⚠️ Menipis' }[level] ?? level
}

onMounted(() => fetchLowStock())
</script>

<style scoped>
.low-stock-alert-banner {
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid #dc354560;
  box-shadow: 0 2px 8px rgba(220,53,69,.15);
}

.alert-bar {
  background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
  min-height: 44px;
}

.alert-body {
  background: #fff;
  border-top: 1px solid #f5c6cb;
}

/* Transitions */
.alert-slide-enter-active, .alert-slide-leave-active { transition: all .25s ease; }
.alert-slide-enter-from, .alert-slide-leave-to { opacity: 0; transform: translateY(-10px); }

.expand-enter-active, .expand-leave-active { transition: all .2s ease; overflow: hidden; }
.expand-enter-from, .expand-leave-to { max-height: 0; opacity: 0; }
.expand-enter-to, .expand-leave-from { max-height: 500px; opacity: 1; }
</style>
