<template>
  <div>
    <!-- Filter & Actions -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small fw-semibold mb-1">Tipe</label>
            <select v-model="filters.type" class="form-select form-select-sm">
              <option value="">Semua Tipe</option>
              <option value="price_spike">Lonjakan Harga</option>
              <option value="consecutive_increase">Kenaikan Berturut-turut</option>
              <option value="po_vs_receive">Selisih PO vs Terima</option>
              <option value="budget_exceeded">Budget Terlampaui</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Severity</label>
            <select v-model="filters.severity" class="form-select form-select-sm">
              <option value="">Semua</option>
              <option value="critical">Kritis</option>
              <option value="warning">Waspada</option>
              <option value="info">Info</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select v-model="filters.unread" class="form-select form-select-sm">
              <option value="">Semua</option>
              <option value="1">Belum Dibaca</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100" @click="load">
              <i class="bi bi-search me-1"></i>Filter
            </button>
          </div>
          <div class="col-md-3 text-end">
            <button class="btn btn-outline-secondary btn-sm" @click="markAllRead" :disabled="acting">
              <i class="bi bi-check2-all me-1"></i>Tandai Semua Dibaca
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- List Anomali -->
    <div class="csm-card">
      <div class="csm-card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Daftar Anomali</h6>
        <span class="badge bg-secondary">{{ meta.total ?? 0 }} total</span>
      </div>
      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center py-4">
          <div class="spinner-border text-primary spinner-border-sm"></div>
        </div>
        <div v-else-if="!anomalies.length" class="text-center text-muted py-5">
          <i class="bi bi-check-circle fs-2 d-block mb-2 opacity-25 text-success"></i>
          Tidak ada anomali
        </div>
        <table v-else class="table csm-table mb-0">
          <thead>
            <tr>
              <th>Waktu</th>
              <th>Tipe</th>
              <th>Severity</th>
              <th>Barang</th>
              <th class="text-end">Nilai Sebelum</th>
              <th class="text-end">Nilai Sesudah</th>
              <th class="text-end">Perubahan</th>
              <th>Supplier</th>
              <th>Referensi</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in anomalies" :key="a.id"
              :class="!a.is_read ? 'fw-semibold' : 'text-muted'">
              <td><small>{{ a.created_at }}</small></td>
              <td>
                <span :class="typeClass(a.anomaly_type)" class="badge" style="font-size:0.65rem;">
                  {{ typeLabel(a.anomaly_type) }}
                </span>
              </td>
              <td>
                <span :class="severityClass(a.severity)" class="badge" style="font-size:0.65rem;">
                  {{ severityLabel(a.severity) }}
                </span>
              </td>
              <td>
                <div class="small">{{ a.item?.name ?? 'N/A' }}</div>
                <code class="text-muted" style="font-size:0.65rem;">{{ a.item?.part_number ?? '-' }}</code>
              </td>
              <td class="text-end small">{{ a.value_before > 0 ? $formatCurrency(a.value_before) : '—' }}</td>
              <td class="text-end small">{{ a.value_after > 0 ? $formatCurrency(a.value_after) : '—' }}</td>
              <td class="text-end">
                <span v-if="a.change_pct"
                  :class="a.change_pct > 0 ? 'text-danger' : 'text-success'"
                  class="small fw-semibold">
                  {{ a.change_pct > 0 ? '▲' : '▼' }} {{ Math.abs(a.change_pct).toFixed(1) }}%
                </span>
              </td>
              <td><small class="text-muted">{{ a.supplier_name || '-' }}</small></td>
              <td><code class="text-muted" style="font-size:0.65rem;">{{ a.reference_no || '-' }}</code></td>
              <td class="text-center">
                <button v-if="!a.is_read" class="btn btn-xs btn-outline-success"
                  @click="markRead(a)" title="Tandai dibaca">
                  <i class="bi bi-check"></i>
                </button>
                <span v-else class="text-muted" style="font-size:0.7rem;">✓</span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="d-flex justify-content-center py-3 gap-2">
          <button class="btn btn-sm btn-outline-secondary" :disabled="meta.page <= 1" @click="page--; load()">‹</button>
          <span class="btn btn-sm btn-light disabled">{{ meta.page }} / {{ meta.last_page }}</span>
          <button class="btn btn-sm btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="page++; load()">›</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const emit    = defineEmits(['read'])
const toast   = useToast()
const loading = ref(false)
const acting  = ref(false)
const anomalies = ref([])
const meta    = ref({})
const page    = ref(1)

const filters = reactive({ type: '', severity: '', unread: '' })

function typeLabel(t) {
  return { price_spike: 'Lonjakan Harga', consecutive_increase: 'Kenaikan Berturut', po_vs_receive: 'PO vs Terima', budget_exceeded: 'Budget Terlampaui' }[t] ?? t
}
function typeClass(t) {
  return { price_spike: 'bg-danger', consecutive_increase: 'bg-warning text-dark', po_vs_receive: 'bg-info text-dark', budget_exceeded: 'bg-orange text-white' }[t] ?? 'bg-secondary'
}
function severityLabel(s) { return { critical: 'Kritis', warning: 'Waspada', info: 'Info' }[s] ?? s }
function severityClass(s) { return { critical: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info text-dark' }[s] ?? 'bg-secondary' }

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/price-intelligence/anomalies', {
      params: { ...filters, page: page.value, per_page: 20 }
    })
    anomalies.value = r.data.data
    meta.value      = r.data.meta
  } catch {} finally { loading.value = false }
}

async function markRead(a) {
  try {
    await axios.post(`/price-intelligence/anomalies/${a.id}/read`)
    a.is_read = true
    emit('read')
  } catch {}
}

async function markAllRead() {
  acting.value = true
  try {
    await axios.post('/price-intelligence/anomalies/read-all')
    anomalies.value.forEach(a => a.is_read = true)
    toast.success('Semua anomali ditandai dibaca')
    emit('read')
  } catch {} finally { acting.value = false }
}

onMounted(load)
</script>
