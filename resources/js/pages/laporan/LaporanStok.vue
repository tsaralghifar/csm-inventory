<template>
  <div>

    <!-- PAGE HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Laporan Stok Persediaan</h5>
        <small class="text-muted">Pantau dan export data stok di seluruh gudang</small>
      </div>
      <div v-if="loaded" class="d-flex gap-2">
        <button class="btn btn-success btn-sm" @click="exportExcel" :disabled="exportingExcel">
          <span v-if="exportingExcel" class="spinner-border spinner-border-sm me-1" style="width:12px;height:12px;border-width:2px;"></span>
          <i v-else class="bi bi-file-earmark-excel me-1"></i>Excel
        </button>
        <button class="btn btn-danger btn-sm" @click="onClickExportPdf" :disabled="exportingPdf">
          <span v-if="exportingPdf" class="spinner-border spinner-border-sm me-1" style="width:12px;height:12px;border-width:2px;"></span>
          <i v-else class="bi bi-file-earmark-pdf me-1"></i>PDF
        </button>
      </div>
    </div>

    <!-- FILTER -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-3">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold mb-1">Gudang / Site</label>
            <select v-model="params.warehouse_id" class="form-select form-select-sm">
              <option value="">Semua Gudang</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select v-model="params.filter" class="form-select form-select-sm">
              <option value="">Semua</option>
              <option value="critical">Kritis</option>
              <option value="minus">Minus</option>
            </select>
          </div>
          <div class="col-6 col-md-4">
            <label class="form-label small fw-semibold mb-1">Cari Barang</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input v-model="searchQuery" type="text" class="form-control"
                placeholder="Nama / part number..." />
              <button v-if="searchQuery" class="btn btn-outline-secondary" @click="searchQuery=''">
                <i class="bi bi-x"></i>
              </button>
            </div>
          </div>
          <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-csm-primary btn-sm flex-grow-1" @click="load" :disabled="loading">
              <span v-if="loading" class="spinner-border spinner-border-sm me-1" style="width:12px;height:12px;border-width:2px;"></span>
              <i v-else class="bi bi-funnel me-1"></i>Tampilkan
            </button>
            <button v-if="loaded" class="btn btn-outline-secondary btn-sm px-2" @click="resetFilter" title="Reset">
              <i class="bi bi-arrow-counterclockwise"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- KPI CARDS -->
    <div v-if="loaded" class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="kpi-card kpi-primary">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-label">Jenis Barang</div>
              <div class="kpi-value">{{ summary.total_items.toLocaleString('id-ID') }}</div>
            </div>
            <i class="bi bi-boxes kpi-icon" style="font-size:1.6rem;opacity:.7;"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card kpi-success">
          <div class="d-flex justify-content-between align-items-start">
            <div style="min-width:0;flex:1;">
              <div class="kpi-label">Nilai Stok</div>
              <div class="fw-bold" style="font-size:0.92rem;line-height:1.4;word-break:break-all;">
                {{ $formatCurrency(summary.total_value) }}
              </div>
            </div>
            <i class="bi bi-cash-stack kpi-icon ms-2" style="font-size:1.6rem;opacity:.7;flex-shrink:0;"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3" style="cursor:pointer" @click="setFilter('critical')">
        <div class="kpi-card kpi-warning">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-label">Stok Kritis</div>
              <div class="kpi-value">{{ summary.critical }}</div>
            </div>
            <i class="bi bi-exclamation-triangle kpi-icon" style="font-size:1.6rem;opacity:.7;"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3" style="cursor:pointer" @click="setFilter('minus')">
        <div class="kpi-card kpi-danger">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-label">Stok Minus</div>
              <div class="kpi-value">{{ summary.minus }}</div>
            </div>
            <i class="bi bi-arrow-down-circle kpi-icon" style="font-size:1.6rem;opacity:.7;"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- TABLE -->
    <div v-if="loaded" class="csm-card">
      <div class="csm-card-header">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <h6 class="mb-0 fw-bold">Daftar Stok</h6>
          <span class="badge rounded-pill bg-primary">{{ filteredStocks.length }} item</span>
          <span v-if="params.filter==='critical'" class="badge bg-warning text-dark">Kritis</span>
          <span v-if="params.filter==='minus'" class="badge bg-danger">Minus</span>
          <span v-if="searchQuery" class="badge bg-light text-dark border">
            <i class="bi bi-search me-1"></i>{{ searchQuery }}
          </span>
        </div>
        <small class="text-muted d-none d-md-inline">
          {{ filteredStocks.length }} / {{ stocks.length }} item
        </small>
      </div>

      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center py-5">
          <div class="csm-spinner mx-auto mb-2"></div>
          <small class="text-muted">Memuat data...</small>
        </div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th class="ps-3" style="width:36px">#</th>
                <th style="width:140px">Part Number</th>
                <th>Nama Barang</th>
                <th style="width:100px">Kategori</th>
                <th v-if="!params.warehouse_id" style="width:180px">Gudang</th>
                <th class="text-center" style="width:60px">Sat.</th>
                <th class="text-end" style="width:70px">Stok</th>
                <th class="text-end" style="width:60px">Min</th>
                <th class="text-end" style="width:100px">Tgl. Masuk</th>
                <th class="text-end" style="width:80px">Qty Layer</th>
                <th class="text-end" style="width:130px">Harga Beli</th>
                <th class="text-end" style="width:140px">Nilai Layer</th>
                <th class="text-center" style="width:75px">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredStocks.length">
                <td :colspan="params.warehouse_id ? 12 : 13" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                  <span v-if="searchQuery">Tidak ada hasil untuk "<strong>{{ searchQuery }}</strong>"</span>
                  <span v-else>Tidak ada data stok</span>
                </td>
              </tr>

              <template v-for="(s, i) in filteredStocks" :key="s.id">
                <!-- Baris item utama — hanya muncul sekali, tanpa kolom layer -->
                <tr :class="{
                    'table-danger':  parseFloat(s.qty) < 0,
                    'table-warning': parseFloat(s.qty) >= 0 && parseFloat(s.qty) <= parseFloat(s.item?.min_stock) && parseFloat(s.item?.min_stock) > 0
                  }"
                  style="cursor:pointer;background:rgba(26,58,92,0.04);"
                  @click="s._expanded = !s._expanded">
                  <td class="ps-3 text-muted small fw-bold">{{ i+1 }}</td>
                  <td>
                    <code class="small" style="color:#1a3a5c;font-size:0.75rem;white-space:nowrap;">
                      {{ s.item?.part_number || '—' }}
                    </code>
                  </td>
                  <td>
                    <div class="small fw-semibold d-flex align-items-center gap-1"
                      style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;">
                      <i :class="s._expanded ? 'bi bi-chevron-down' : 'bi bi-chevron-right'"
                        class="text-muted" style="font-size:0.65rem;"></i>
                      {{ s.item?.name }}
                    </div>
                    <small class="text-muted" v-if="s.item?.brand">{{ s.item.brand }}</small>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border" style="font-size:0.7rem;white-space:nowrap;">
                      {{ s.item?.category?.name || '—' }}
                    </span>
                  </td>
                  <td v-if="!params.warehouse_id">
                    <div class="d-flex flex-wrap gap-1">
                      <span v-for="g in (s.gudang || [])" :key="g.id"
                        class="badge bg-secondary bg-opacity-75"
                        style="font-size:0.68rem;white-space:nowrap;">
                        {{ g.name.replace('Gudang ','') }} ({{ g.qty }})
                      </span>
                    </div>
                  </td>
                  <td class="text-center small text-muted">{{ s.item?.unit || '—' }}</td>
                  <td class="text-end">
                    <span class="fw-bold small"
                      :class="parseFloat(s.qty)<0 ? 'stock-minus' : parseFloat(s.qty)<=parseFloat(s.item?.min_stock) && parseFloat(s.item?.min_stock)>0 ? 'stock-low' : 'stock-ok'">
                      {{ $formatNumber(s.qty) }}
                    </span>
                  </td>
                  <td class="text-end small text-muted">{{ s.item?.min_stock || '0' }}</td>
                  <!-- Kolom layer kosong di baris utama -->
                  <td class="text-end small text-muted">
                    <span class="badge bg-info text-dark" style="font-size:0.6rem;">
                      {{ (s.layers||[]).length }} batch
                    </span>
                  </td>
                  <td class="text-end small text-muted fw-semibold">{{ $formatNumber(s.qty) }}</td>
                  <td class="text-end small text-muted">—</td>
                  <td class="text-end small fw-bold text-primary">
                    {{ $formatCurrency((s.layers||[]).reduce((a,l)=>a+l.nilai,0)) }}
                  </td>
                  <td class="text-center">
                    <span v-if="parseFloat(s.qty)<0" class="badge bg-danger" style="font-size:0.68rem;">Minus</span>
                    <span v-else-if="parseFloat(s.qty)<=parseFloat(s.item?.min_stock) && parseFloat(s.item?.min_stock)>0"
                      class="badge bg-warning text-dark" style="font-size:0.68rem;">Kritis</span>
                    <span v-else class="badge bg-success" style="font-size:0.68rem;">Normal</span>
                  </td>
                </tr>

                <!-- Baris detail per layer FIFO -->
                <template v-if="s._expanded !== false && s.layers?.length">
                  <tr v-for="(layer, li) in s.layers" :key="'layer-'+layer.id"
                    style="background:rgba(26,58,92,0.015);">
                    <td class="ps-3 text-muted" style="font-size:0.68rem;">
                      <span class="ms-3 text-muted">└</span>
                    </td>
                    <td colspan="2">
                      <div class="d-flex align-items-center gap-2 ps-3">
                        <span class="badge" style="font-size:0.6rem;background:#1a3a5c;">
                          Batch {{ li + 1 }}
                        </span>
                        <code class="text-muted" style="font-size:0.68rem;">{{ layer.reference_no || '—' }}</code>
                        <span class="badge bg-light text-muted border" style="font-size:0.6rem;">
                          {{ sourceLabel(layer.source_type) }}
                        </span>
                      </div>
                    </td>
                    <td></td>
                    <td v-if="!params.warehouse_id"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Tgl Masuk -->
                    <td class="text-end small text-muted">{{ $formatDate(layer.tanggal_masuk) }}</td>
                    <!-- Qty Layer -->
                    <td class="text-end">
                      <span class="fw-semibold small text-success">{{ $formatNumber(layer.qty_sisa) }}</span>
                      <span class="text-muted small ms-1" v-if="layer.qty_awal !== layer.qty_sisa">
                        / {{ $formatNumber(layer.qty_awal) }}
                      </span>
                    </td>
                    <!-- Harga Beli per batch -->
                    <td class="text-end small">
                      <span class="fw-semibold text-primary">{{ $formatCurrency(layer.harga_satuan) }}</span>
                      <span class="badge bg-info text-dark ms-1" style="font-size:0.55rem;">FIFO</span>
                    </td>
                    <!-- Nilai layer -->
                    <td class="text-end small fw-semibold">{{ $formatCurrency(layer.nilai) }}</td>
                    <td></td>
                  </tr>
                </template>

                <!-- Baris total nilai jika ada lebih dari 1 layer -->
                <tr v-if="s._expanded !== false && s.layers?.length > 1"
                  style="background:rgba(26,58,92,0.03);border-top:1px dashed #dee2e6;">
                  <td :colspan="params.warehouse_id ? 10 : 11" class="text-end small text-muted pe-2">
                    Total Nilai FIFO
                  </td>
                  <td class="text-end small fw-bold text-primary">
                    {{ $formatCurrency((s.layers||[]).reduce((a,l)=>a+l.nilai,0)) }}
                  </td>
                  <td></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Footer -->
        <div v-if="filteredStocks.length && !loading"
          class="d-flex justify-content-between align-items-center px-3 py-2 border-top"
          style="background:#f8fafc;border-radius:0 0 10px 10px;">
          <small class="text-muted">
            <strong>{{ filteredStocks.length }}</strong> dari <strong>{{ stocks.length }}</strong> item unik
            <span class="ms-1">·
              <span class="fw-semibold" style="color:#1a3a5c;">
                {{ params.warehouse_id ? warehouses.find(w=>w.id==params.warehouse_id)?.name : 'Semua Gudang' }}
              </span>
            </span>
          </small>
          <small class="text-muted d-none d-md-inline">
            Nilai ditampilkan:
            <strong class="text-success">
              {{ $formatCurrency(filteredStocks.reduce((a,s)=>a+(s.layers||[]).reduce((b,l)=>b+l.nilai,0),0)) }}
            </strong>
            <span class="badge bg-info text-dark ms-1" style="font-size:0.65rem;">FIFO per Batch</span>
          </small>
        </div>
      </div>
    </div>

    <!-- EMPTY STATE -->
    <div v-else-if="!loading" class="csm-card">
      <div class="csm-card-body text-center py-5">
        <i class="bi bi-bar-chart-line d-block mb-3 text-muted" style="font-size:2.5rem;opacity:.2;"></i>
        <p class="fw-semibold text-muted mb-1">Belum ada data</p>
        <small class="text-muted">Pilih gudang lalu klik <strong>Tampilkan</strong></small>
      </div>
    </div>

  </div>
  <!-- Modal Pilih Penandatangan -->
  <SignerPickerModal
    v-model="showSignerModal"
    :slots="slots"
    :is-finalized="isFinalized"
    :finalized-at="finalizedAt"
    :loading="signerLoading"
    :action-loading="signerActionLoading"
    @add-slot="handleAddSlot"
    @finalize="handleFinalize"
    @print="handlePrint"
  />
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { useSignerPicker } from '@/composables/useSignerPicker'
import SignerPickerModal from '@/components/SignerPickerModal.vue'

const toast = useToast()
const auth  = useAuthStore()

// ── Signer Picker ────────────────────────────────────────────────────
const {
  showSignerModal, slots, isFinalized, finalizedAt,
  loading: signerLoading, actionLoading: signerActionLoading,
  openSignerPicker, addMySlot, finalizeDoc, closeModal,
} = useSignerPicker()

// ── State ────────────────────────────────────────────────────────────
const warehouses     = ref([])
const stocks         = ref([])
const summary        = ref({ total_items: 0, total_value: 0, critical: 0, minus: 0 })
const loading        = ref(false)
const loaded         = ref(false)
const exportingExcel = ref(false)
const exportingPdf   = ref(false)
const searchQuery    = ref('')

const params = ref({
  warehouse_id: '',
  filter: '',
})

// ── Computed ─────────────────────────────────────────────────────────
const filteredStocks = computed(() => {
  let list = stocks.value
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter(s =>
      s.item?.name?.toLowerCase().includes(q) ||
      s.item?.part_number?.toLowerCase().includes(q)
    )
  }
  return list
})

// ── Lifecycle ────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const r = await axios.get('/warehouses')
    warehouses.value = r.data.data || []
    // Jika bukan superuser/adminHO, default ke gudang user
    if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) {
      params.value.warehouse_id = auth.userWarehouseId
    }
  } catch {
    toast.error('Gagal memuat daftar gudang')
  }
})

// ── Load Data ────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/stock', {
      params: {
        warehouse_id: params.value.warehouse_id || undefined,
        filter:       params.value.filter       || undefined,
      }
    })
    // Tandai setiap item agar bisa expand/collapse layer
    stocks.value  = (r.data.data || []).map(s => ({ ...s, _expanded: false }))
    summary.value = r.data.summary || { total_items: 0, total_value: 0, critical: 0, minus: 0 }
    loaded.value  = true
    searchQuery.value = ''
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal memuat laporan stok')
  } finally {
    loading.value = false
  }
}

// ── Filter Helpers ────────────────────────────────────────────────────
function setFilter(f) {
  params.value.filter = params.value.filter === f ? '' : f
  if (loaded.value) load()
}

function resetFilter() {
  params.value = { warehouse_id: '', filter: '' }
  searchQuery.value = ''
  stocks.value  = []
  summary.value = { total_items: 0, total_value: 0, critical: 0, minus: 0 }
  loaded.value  = false
}

// ── Source Label ─────────────────────────────────────────────────────
function sourceLabel(type) {
  return {
    purchase_order:     'PO',
    surat_jalan:        'Surat Jalan',
    bon_pengeluaran:    'Bon Keluar',
    transfer:           'Transfer',
    opname:             'Opname',
    saldo_awal:         'Saldo Awal',
    adjustment:         'Adj',
  }[type] || type || '—'
}

// ── Export Excel (CSV) ───────────────────────────────────────────────
async function exportExcel() {
  if (!stocks.value.length) { toast.warning('Tidak ada data untuk diekspor'); return }
  exportingExcel.value = true
  try {
    const warehouseName = params.value.warehouse_id
      ? warehouses.value.find(w => w.id == params.value.warehouse_id)?.name || 'Gudang'
      : 'Semua Gudang'

    const headers = ['No', 'Part Number', 'Nama Barang', 'Brand', 'Kategori', 'Satuan',
      'Stok Total', 'Stok Min', 'Status',
      'Batch', 'Ref No', 'Sumber', 'Tgl Masuk', 'Qty Batch', 'Harga Satuan (FIFO)', 'Nilai Batch']

    const rows = []
    filteredStocks.value.forEach((s, i) => {
      const status = parseFloat(s.qty) < 0 ? 'Minus'
        : parseFloat(s.qty) <= parseFloat(s.item?.min_stock) && parseFloat(s.item?.min_stock) > 0
          ? 'Kritis' : 'Normal'

      if (!s.layers?.length) {
        rows.push([
          i + 1,
          s.item?.part_number || '',
          s.item?.name || '',
          s.item?.brand || '',
          s.item?.category?.name || '',
          s.item?.unit || '',
          s.qty,
          s.item?.min_stock || 0,
          status,
          '', '', '', '', '', '', '',
        ])
      } else {
        s.layers.forEach((layer, li) => {
          rows.push([
            li === 0 ? i + 1 : '',
            li === 0 ? (s.item?.part_number || '') : '',
            li === 0 ? (s.item?.name || '') : '',
            li === 0 ? (s.item?.brand || '') : '',
            li === 0 ? (s.item?.category?.name || '') : '',
            li === 0 ? (s.item?.unit || '') : '',
            li === 0 ? s.qty : '',
            li === 0 ? (s.item?.min_stock || 0) : '',
            li === 0 ? status : '',
            li + 1,
            layer.reference_no || '',
            sourceLabel(layer.source_type),
            layer.tanggal_masuk || '',
            layer.qty_sisa,
            layer.harga_satuan,
            layer.nilai,
          ])
        })
      }
    })

    const csv = [headers, ...rows]
      .map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(','))
      .join('\n')

    const filename = `laporan_stok_${warehouseName.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0,10)}.csv`
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }))
    a.download = filename
    a.click()
    toast.success('Export Excel berhasil')
  } catch {
    toast.error('Gagal export Excel')
  } finally {
    exportingExcel.value = false
  }
}

// ── Export PDF ───────────────────────────────────────────────────────
async function onClickExportPdf() {
  if (!stocks.value.length) { toast.warning('Tidak ada data untuk diekspor'); return }
  openSignerPicker('report_stock', Date.now(), exportPdf)
}

async function exportPdf(resolvedSlots = []) {
  exportingPdf.value = true
  try {
    const response = await axios.get('/reports/export-pdf', {
      params: {
        warehouse_id: params.value.warehouse_id || undefined,
        filter:       params.value.filter       || undefined,
        signer_ids:   undefined,
      },
      responseType: 'blob',
    })
    const warehouseName = params.value.warehouse_id
      ? warehouses.value.find(w => w.id == params.value.warehouse_id)?.name || 'Gudang'
      : 'Semua_Gudang'
    const filename = `laporan_stok_${warehouseName.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0,10)}.pdf`
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    a.download = filename
    a.click()
    toast.success('Export PDF berhasil')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal export PDF')
  } finally {
    exportingPdf.value = false
  }
}

// ── SignerPicker handlers ──────────────────────────────────────────────────
async function handleAddSlot(slot) {
  const { success, message } = await addMySlot(slot)
  if (!success) toast.error(message)
  else toast.success(message)
}

async function handleFinalize() {
  const { success, message } = await finalizeDoc()
  if (!success) toast.error(message)
  else toast.success(message)
}

function handlePrint() {
  closeModal()
  exportPdf(slots.value)
}

</script>

<style scoped>
.kpi-card {
  border-radius: 10px;
  padding: 14px 16px;
  color: #fff;
}
.kpi-primary  { background: linear-gradient(135deg,#1a3a5c,#2563eb); }
.kpi-success  { background: linear-gradient(135deg,#065f46,#10b981); }
.kpi-warning  { background: linear-gradient(135deg,#92400e,#f59e0b); }
.kpi-danger   { background: linear-gradient(135deg,#7f1d1d,#ef4444); }
.kpi-label    { font-size: 0.72rem; opacity: .85; margin-bottom: 2px; }
.kpi-value    { font-size: 1.5rem; font-weight: 800; line-height: 1.1; }
.kpi-icon     { opacity: .6; }
.stock-ok     { color: #16a34a; }
.stock-low    { color: #d97706; }
.stock-minus  { color: #dc2626; }
</style>