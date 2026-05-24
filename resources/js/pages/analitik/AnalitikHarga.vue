<template>
  <div>
    <!-- PAGE HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">
          <i class="bi bi-graph-up-arrow me-2"></i>Analitik Harga
        </h5>
        <small class="text-muted">Monitor perubahan harga, tren, dan budget pembelian</small>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <span v-if="unreadAnomalies > 0" class="badge bg-danger">
          {{ unreadAnomalies }} anomali belum dibaca
        </span>
      </div>
    </div>

    <!-- TABS -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-0">
        <ul class="nav nav-tabs border-0" style="gap:4px;">
          <li class="nav-item" v-for="tab in tabs" :key="tab.key">
            <button
              class="nav-link px-3 py-3 border-0"
              :class="{ active: activeTab === tab.key }"
              @click="activeTab = tab.key"
              style="font-size:0.85rem;font-weight:600;border-radius:0;">
              <i :class="tab.icon + ' me-1'"></i>{{ tab.label }}
              <span v-if="tab.key === 'anomali' && unreadAnomalies > 0"
                class="badge bg-danger ms-1" style="font-size:0.6rem;">{{ unreadAnomalies }}</span>
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- TAB CONTENT -->
    <TabDashboard v-if="activeTab === 'dashboard'" @go-tab="activeTab = $event" />
    <TabTren      v-if="activeTab === 'tren'" />
    <TabSupplier  v-if="activeTab === 'supplier'" />
    <TabBudget    v-if="activeTab === 'budget'" />
    <TabAnomalies v-if="activeTab === 'anomali'" @read="fetchUnread" />
    <TabKonfigurasi v-if="activeTab === 'konfigurasi'" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import TabDashboard   from './tabs/TabDashboard.vue'
import TabTren        from './tabs/TabTren.vue'
import TabSupplier    from './tabs/TabSupplier.vue'
import TabBudget      from './tabs/TabBudget.vue'
import TabAnomalies   from './tabs/TabAnomalies.vue'
import TabKonfigurasi from './tabs/TabKonfigurasi.vue'

const activeTab = ref('dashboard')
const unreadAnomalies = ref(0)

const tabs = [
  { key: 'dashboard',    label: 'Dashboard',          icon: 'bi bi-speedometer2' },
  { key: 'tren',         label: 'Tren Harga',          icon: 'bi bi-graph-up' },
  { key: 'supplier',     label: 'Perbandingan Supplier',icon: 'bi bi-building' },
  { key: 'budget',       label: 'Budget Monitor',       icon: 'bi bi-cash-stack' },
  { key: 'anomali',      label: 'Anomali',              icon: 'bi bi-exclamation-triangle' },
  { key: 'konfigurasi',  label: 'Konfigurasi',          icon: 'bi bi-gear' },
]

async function fetchUnread() {
  try {
    const r = await axios.get('/price-intelligence/anomalies', { params: { unread: 1, per_page: 1 } })
    unreadAnomalies.value = r.data.meta?.total ?? 0
  } catch {}
}

onMounted(fetchUnread)
</script>
