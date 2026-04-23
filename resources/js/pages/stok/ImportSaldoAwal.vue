<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-1" style="color:#1a3a5c;">Import Saldo Awal Stok</h5>
        <small class="text-muted">Import data stok awal dari file Excel laporan persediaan</small>
      </div>
    </div>

    <!-- Step 1: Form -->
    <div class="csm-card mb-3">
      <div class="csm-card-body">
        <h6 class="fw-semibold mb-3"><span class="badge bg-primary me-2">1</span>Pilih File & Konfigurasi</h6>
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">File Excel <span class="text-danger">*</span></label>
            <input ref="fileInput" type="file" class="form-control form-control-sm"
              accept=".xlsx,.xls,.csv" @change="onFileChange" />
            <div class="form-text">Format: .xlsx, .xls — Kolom N(Stok Akhir), O(Harga), P(Total)</div>
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold">Nama Sheet <span class="text-danger">*</span></label>
            <input v-model="form.sheet_name" type="text" class="form-control form-control-sm" placeholder="Contoh: JAN" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Gudang <span class="text-danger">*</span></label>
            <select v-model="form.warehouse_id" class="form-select form-select-sm">
              <option value="">-- Pilih Gudang --</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold">Tanggal Saldo <span class="text-danger">*</span></label>
            <input v-model="form.tanggal_saldo" type="date" class="form-control form-control-sm" />
          </div>

          <!-- Auto create option -->
          <div class="col-12">
            <div class="p-3 rounded border" :class="form.auto_create ? 'border-success bg-success bg-opacity-10' : 'border-secondary bg-light'">
              <div class="form-check form-switch mb-0">
                <input v-model="form.auto_create" class="form-check-input" type="checkbox" id="autoCreateCheck" />
                <label class="form-check-label fw-semibold" for="autoCreateCheck">
                  <i class="bi bi-plus-circle me-1"></i>
                  Buat barang baru otomatis jika belum ada di database
                </label>
              </div>
              <div v-if="form.auto_create" class="mt-2">
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold mb-1">
                      Kategori Default <span class="text-danger">*</span>
                      <span class="text-muted fw-normal">(untuk barang baru)</span>
                    </label>
                    <select v-model="form.category_id" class="form-select form-select-sm">
                      <option value="">-- Pilih Kategori --</option>
                      <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-8">
                    <div class="alert alert-info py-2 mb-0 small">
                      <i class="bi bi-info-circle me-1"></i>
                      Barang baru akan dibuat dengan: <strong>satuan PCS</strong>, <strong>stok minimum 0</strong>.
                      Data bisa dilengkapi di menu <strong>Barang / Sparepart</strong> setelah import.
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Overwrite option -->
          <div class="col-12">
            <div class="form-check form-switch">
              <input v-model="form.overwrite" class="form-check-input" type="checkbox" id="overwriteCheck" />
              <label class="form-check-label small" for="overwriteCheck">
                <span class="fw-semibold text-warning">Timpa stok yang sudah ada</span>
                <span class="text-muted ms-1">(jika tidak dicentang, barang yang sudah punya stok akan dilewati)</span>
              </label>
            </div>
          </div>

          <div class="col-12">
            <button class="btn btn-outline-primary btn-sm" :disabled="!canPreview || previewing" @click="doPreview">
              <span v-if="previewing" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-eye me-1"></i>
              Preview Data
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 2: Preview -->
    <div v-if="previewData" class="csm-card mb-3">
      <div class="csm-card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="fw-semibold mb-0"><span class="badge bg-primary me-2">2</span>Hasil Preview</h6>
          <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-secondary">Total: {{ previewData.total_rows }} baris</span>
            <span class="badge bg-success">Ditemukan: {{ previewData.found }}</span>
            <span v-if="previewData.will_create > 0" class="badge bg-primary">Akan dibuat: {{ previewData.will_create }}</span>
            <span v-if="previewData.not_found > 0" class="badge bg-danger">Dilewati: {{ previewData.not_found }}</span>
            <span class="badge bg-info text-dark">Sheet: {{ previewData.sheet_used }}</span>
          </div>
        </div>

        <!-- Info box -->
        <div v-if="form.auto_create && previewData.will_create > 0" class="alert alert-success py-2 mb-3">
          <i class="bi bi-check-circle me-1"></i>
          <strong>{{ previewData.will_create }} barang baru</strong> akan dibuat otomatis saat import dengan kategori yang dipilih.
        </div>
        <div v-if="previewData.errors?.length" class="alert alert-warning py-2 mb-3">
          <div class="fw-semibold small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Barang yang akan dilewati ({{ previewData.errors.length }}):</div>
          <ul class="mb-0 small">
            <li v-for="(e, i) in previewData.errors.slice(0, 5)" :key="i">{{ e }}</li>
            <li v-if="previewData.errors.length > 5" class="text-muted">... dan {{ previewData.errors.length - 5 }} lainnya</li>
          </ul>
        </div>

        <!-- Tabs -->
        <div class="d-flex gap-2 mb-2 flex-wrap">
          <button v-for="tab in visibleTabs" :key="tab.value"
            class="btn btn-sm"
            :class="activeTab === tab.value ? 'btn-primary' : 'btn-outline-secondary'"
            @click="activeTab = tab.value">
            {{ tab.label }} ({{ tabCount(tab.value) }})
          </button>
        </div>

        <!-- Table -->
        <div class="table-responsive">
          <table class="table csm-table table-sm mb-0">
            <thead>
              <tr>
                <th>Baris</th>
                <th>Part Number</th>
                <th>Nama Barang (Excel)</th>
                <th>Nama di DB</th>
                <th class="text-end">Stok Excel</th>
                <th class="text-end">Stok Saat Ini</th>
                <th class="text-end">Harga</th>
                <th class="text-end">Total</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="filteredPreview.length">
                <tr v-for="row in filteredPreview" :key="row.row" :class="rowClass(row)">
                  <td class="text-muted small">{{ row.row }}</td>
                  <td><code class="small">{{ row.part_number || '-' }}</code></td>
                  <td class="small">{{ row.nama_barang }}</td>
                  <td class="small text-success">{{ row.item_name_db || (row.status === 'will_create' ? '(akan dibuat)' : '-') }}</td>
                  <td class="text-end fw-semibold">{{ formatNumber(row.stok_akhir) }}</td>
                  <td class="text-end text-muted small">{{ row.current_stock !== null ? formatNumber(row.current_stock) : '-' }}</td>
                  <td class="text-end small">{{ formatRupiah(row.harga) }}</td>
                  <td class="text-end small">{{ formatRupiah(row.total_harga) }}</td>
                  <td class="text-center">
                    <span v-if="row.status === 'found'" class="badge bg-success">✓ Ada</span>
                    <span v-else-if="row.status === 'will_create'" class="badge bg-primary">+ Baru</span>
                    <span v-else class="badge bg-danger">✗ Lewati</span>
                  </td>
                </tr>
              </template>
              <tr v-else>
                <td colspan="9" class="text-center text-muted py-3">Tidak ada data</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Import button -->
        <div class="mt-3 pt-3 border-top d-flex align-items-center gap-3 flex-wrap">
          <button class="btn btn-success"
            :disabled="(previewData.found + previewData.will_create) === 0 || importing"
            @click="doImport">
            <span v-if="importing" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="bi bi-upload me-1"></i>
            Mulai Import ({{ previewData.found + (previewData.will_create || 0) }} barang)
          </button>
          <div class="small text-muted">
            <span v-if="form.auto_create">
              ✅ {{ previewData.found }} diperbarui &nbsp;|&nbsp;
              🆕 {{ previewData.will_create }} dibuat baru
              <span v-if="previewData.not_found > 0"> &nbsp;|&nbsp; ⏭ {{ previewData.not_found }} dilewati</span>
            </span>
            <span v-else>
              {{ form.overwrite ? '⚠️ Mode overwrite aktif' : '🔒 Mode aman: stok lama tidak ditimpa' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 3: Result -->
    <div v-if="importResult" class="csm-card">
      <div class="csm-card-body">
        <h6 class="fw-semibold mb-3"><span class="badge bg-success me-2">✓</span>Import Selesai</h6>
        <div class="d-flex gap-3 flex-wrap mb-3">
          <div class="text-center px-4 py-3 rounded border border-success bg-success bg-opacity-10">
            <div class="fs-3 fw-bold text-success">{{ importResult.imported }}</div>
            <div class="small text-muted">Stok diimport</div>
          </div>
          <div v-if="importResult.created > 0" class="text-center px-4 py-3 rounded border border-primary bg-primary bg-opacity-10">
            <div class="fs-3 fw-bold text-primary">{{ importResult.created }}</div>
            <div class="small text-muted">Barang baru dibuat</div>
          </div>
          <div v-if="importResult.skipped > 0" class="text-center px-4 py-3 rounded border border-warning bg-warning bg-opacity-10">
            <div class="fs-3 fw-bold text-warning">{{ importResult.skipped }}</div>
            <div class="small text-muted">Dilewati</div>
          </div>
          <div v-if="importResult.failed?.length" class="text-center px-4 py-3 rounded border border-danger bg-danger bg-opacity-10">
            <div class="fs-3 fw-bold text-danger">{{ importResult.failed.length }}</div>
            <div class="small text-muted">Gagal</div>
          </div>
        </div>
        <div v-if="importResult.created > 0" class="alert alert-info py-2 small mb-3">
          <i class="bi bi-info-circle me-1"></i>
          <strong>{{ importResult.created }} barang baru</strong> telah ditambahkan ke database dengan satuan PCS.
          Lengkapi data seperti brand, satuan, dan stok minimum di menu <strong>Barang / Sparepart</strong>.
        </div>
        <div v-if="importResult.failed?.length" class="alert alert-warning py-2 mb-3">
          <div class="small fw-semibold mb-1">Detail gagal:</div>
          <ul class="mb-0 small"><li v-for="(f, i) in importResult.failed" :key="i">{{ f }}</li></ul>
        </div>
        <button class="btn btn-outline-primary btn-sm" @click="resetAll">
          <i class="bi bi-arrow-repeat me-1"></i>Import File Lain
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const fileInput    = ref(null)
const selectedFile = ref(null)
const warehouses   = ref([])
const categories   = ref([])
const previewData  = ref(null)
const importResult = ref(null)
const previewing   = ref(false)
const importing    = ref(false)
const activeTab    = ref('all')

const form = ref({
  sheet_name:    'JAN',
  warehouse_id:  '',
  tanggal_saldo: new Date().toISOString().slice(0, 10),
  overwrite:     false,
  auto_create:   false,
  category_id:   '',
})

const allTabs = [
  { value: 'all',          label: 'Semua' },
  { value: 'found',        label: '✓ Ditemukan' },
  { value: 'will_create',  label: '+ Akan Dibuat' },
  { value: 'not_found',    label: '✗ Dilewati' },
]

const visibleTabs = computed(() => {
  if (!previewData.value) return allTabs
  return allTabs.filter(t => {
    if (t.value === 'will_create' && !previewData.value.will_create) return false
    if (t.value === 'not_found'  && !previewData.value.not_found)   return false
    return true
  })
})

const canPreview = computed(() => {
  if (!selectedFile.value || !form.value.warehouse_id || !form.value.sheet_name) return false
  if (form.value.auto_create && !form.value.category_id) return false
  return true
})

const filteredPreview = computed(() => {
  if (!previewData.value?.preview) return []
  if (activeTab.value === 'all') return previewData.value.preview
  return previewData.value.preview.filter(r => r.status === activeTab.value)
})

function tabCount(tab) {
  if (!previewData.value?.preview) return 0
  if (tab === 'all') return previewData.value.preview.length
  return previewData.value.preview.filter(r => r.status === tab).length
}

async function loadData() {
  const [wRes, cRes] = await Promise.all([
    axios.get('/warehouses'),
    axios.get('/categories'),
  ])
  warehouses.value = wRes.data.data || []
  categories.value = cRes.data.data || []
}

function onFileChange(e) {
  selectedFile.value = e.target.files[0] || null
  previewData.value  = null
  importResult.value = null
}

async function doPreview() {
  previewing.value  = true
  previewData.value = null
  try {
    const fd = buildFormData()
    const res = await axios.post('/import-saldo-awal/preview', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    previewData.value = res.data.data
    activeTab.value   = 'all'
  } catch (err) {
    Swal.fire('Gagal', err.response?.data?.message || 'Terjadi kesalahan saat preview', 'error')
  } finally {
    previewing.value = false
  }
}

async function doImport() {
  const total = previewData.value.found + (previewData.value.will_create || 0)
  const confirm = await Swal.fire({
    title: 'Konfirmasi Import',
    html: `
      Akan memproses <b>${previewData.value.total_rows}</b> baris data:<br><br>
      ${previewData.value.found > 0 ? `✅ <b>${previewData.value.found}</b> barang diperbarui<br>` : ''}
      ${previewData.value.will_create > 0 ? `🆕 <b>${previewData.value.will_create}</b> barang baru dibuat<br>` : ''}
      ${previewData.value.not_found > 0 ? `⏭ <b>${previewData.value.not_found}</b> dilewati<br>` : ''}
      ${form.value.overwrite ? '<br><span class="text-warning">⚠️ Mode overwrite aktif</span>' : ''}
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Import!',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#198754',
  })
  if (!confirm.isConfirmed) return

  importing.value = true
  try {
    const fd = buildFormData(true)
    const res = await axios.post('/import-saldo-awal/import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    importResult.value = res.data.data
    previewData.value  = null
    Swal.fire({ icon: 'success', title: 'Import Berhasil!', text: res.data.message, timer: 4000, showConfirmButton: false })
  } catch (err) {
    Swal.fire('Gagal', err.response?.data?.message || 'Terjadi kesalahan saat import', 'error')
  } finally {
    importing.value = false
  }
}

function buildFormData(withTanggal = false) {
  const fd = new FormData()
  fd.append('file', selectedFile.value)
  fd.append('sheet_name', form.value.sheet_name)
  fd.append('warehouse_id', form.value.warehouse_id)
  fd.append('auto_create', form.value.auto_create ? '1' : '0')
  fd.append('overwrite', form.value.overwrite ? '1' : '0')
  if (form.value.auto_create && form.value.category_id) {
    fd.append('category_id', form.value.category_id)
  }
  if (withTanggal) fd.append('tanggal_saldo', form.value.tanggal_saldo)
  return fd
}

function resetAll() {
  selectedFile.value  = null
  previewData.value   = null
  importResult.value  = null
  form.value.overwrite    = false
  form.value.auto_create  = false
  form.value.category_id  = ''
  if (fileInput.value) fileInput.value.value = ''
}

function rowClass(row) {
  if (row.status === 'not_found')    return 'table-danger'
  if (row.status === 'will_create')  return 'table-primary'
  if (row.current_stock > 0 && form.value.overwrite) return 'table-warning'
  return ''
}

function formatNumber(val) {
  if (val === null || val === undefined) return '-'
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(val)
}

function formatRupiah(val) {
  if (!val && val !== 0) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val)
}

onMounted(loadData)
</script>