<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Bon Pengeluaran</h5>
        <small class="text-muted">Pengeluaran barang dari stok gudang</small>
      </div>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. Bon..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="approved">Disetujui</option>
              <option value="issued">Sudah Dikeluarkan</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary btn-sm w-100" @click="resetFilters">Reset</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="csm-card">
      <div class="csm-card-body p-0">
        <div v-if="loading" class="text-center p-5"><div class="csm-spinner"></div></div>
        <div class="table-responsive" v-else>
          <table class="table csm-table mb-0">
            <thead>
              <tr>
                <th>No. Bon</th>
                <th>No. MR / PM</th>
                <th>Gudang</th>
                <th>Item</th>
                <th>Diterima Oleh / Mekanik</th>
                <th>Tgl. Keluar</th>
                <th>Status</th>
                <th>Dibuat Oleh</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="9" class="text-center text-muted py-5">Belum ada Bon Pengeluaran</td>
              </tr>
              <tr v-for="bon in list" :key="bon.id">
                <td class="fw-semibold text-success">{{ bon.bon_number }}</td>
                <td><small class="text-muted">{{ bon.material_request?.mr_number || bon.permintaan_material?.pm_number || '-' }}</small></td>
                <td><small>{{ bon.warehouse?.name }}</small></td>
                <td><span class="badge bg-secondary rounded-pill">{{ bon.items_count }} item</span></td>
                <td><small>{{ bon.received_by || '-' }}</small></td>
                <td><small class="text-muted">{{ bon.issue_date ? $formatDate(bon.issue_date) : '-' }}</small></td>
                <td>
                  <span class="badge" :class="bon.status === 'issued' ? 'bg-success' : bon.status === 'approved' ? 'bg-primary' : 'bg-secondary'">
                    {{ bon.status === 'issued' ? 'Sudah Dikeluarkan' : bon.status === 'approved' ? 'Disetujui' : 'Draft' }}
                  </span>
                </td>
                <td><small>{{ bon.creator?.name }}</small></td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="Detail" @click="openDetail(bon)">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-danger" title="Print PDF" @click="printBonDirect(bon)">
                      <i class="bi bi-printer"></i>
                    </button>
                    <button v-if="bon.status === 'draft' && can('issue-bon')"
                      class="btn btn-outline-success" title="Keluarkan Barang" @click="doIssue(bon)">
                      <i class="bi bi-box-arrow-right"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">Total {{ meta.total }} bon pengeluaran</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail Bon -->
    <div class="modal fade" id="modalDetailBon" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedBon">
          <div class="modal-header">
            <h6 class="modal-title text-success"><i class="bi bi-box-arrow-right me-2"></i>{{ selectedBon.bon_number }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted w-40">No. Bon</td><td class="fw-semibold">{{ selectedBon.bon_number }}</td></tr>
                  <tr><td class="text-muted">No. MR / PM</td><td>{{ selectedBon.material_request?.mr_number || selectedBon.permintaan_material?.pm_number || '-' }}</td></tr>
                  <tr><td class="text-muted">Gudang</td><td>{{ selectedBon.warehouse?.name }}</td></tr>
                  <tr><td class="text-muted">No. PO / WO</td><td>{{ selectedBon.po_number || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted w-40">Status</td>
                    <td><span class="badge" :class="selectedBon.status === 'issued' ? 'bg-success' : 'bg-secondary'">
                      {{ selectedBon.status === 'issued' ? 'Sudah Dikeluarkan' : 'Draft' }}
                    </span></td>
                  </tr>
                  <tr><td class="text-muted">Diterima Oleh / Mekanik</td><td>{{ selectedBon.received_by || selectedBon.mechanic || '-' }}</td></tr>
                  <tr><td class="text-muted">Tgl. Keluar</td><td>{{ selectedBon.issue_date ? $formatDate(selectedBon.issue_date) : '-' }}</td></tr>
                  <tr><td class="text-muted">Kode Unit</td><td>{{ selectedBon.unit_code || '-' }}</td></tr>
                  <tr><td class="text-muted">Tipe Unit</td><td>{{ selectedBon.unit_type || '-' }}</td></tr>
                  <tr><td class="text-muted">HM / KM</td><td>{{ selectedBon.hm_km || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm csm-table mb-0">
                <thead><tr><th>#</th><th>Nama Barang</th><th class="text-end">Qty</th><th>Satuan</th><th>Keterangan</th></tr></thead>
                <tbody>
                  <tr v-for="(item, idx) in selectedBon.items" :key="item.id">
                    <td class="text-muted">{{ idx+1 }}</td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td class="text-end fw-bold">{{ item.qty }}</td>
                    <td>{{ item.satuan }}</td>
                    <td><small class="text-muted">{{ item.keterangan || '-' }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="selectedBon.status === 'draft'" class="alert alert-warning mt-3 small py-2 mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Klik <strong>Keluarkan Barang</strong> untuk mengurangi stok gudang secara otomatis.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button type="button" class="btn btn-outline-danger btn-sm" @click="printBon(selectedBon)">
              <i class="bi bi-printer me-1"></i>Print PDF
            </button>
            <button v-if="selectedBon.status === 'draft' && can('issue-bon')"
              type="button" class="btn btn-success btn-sm" @click="doIssueFromModal" :disabled="acting">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-box-arrow-right me-1"></i>Keluarkan Barang
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'

const toast = useToast()
const auth = useAuthStore()
const { listenBon, stopBon } = useRealtime()
const can = (p) => auth.hasPermission(p)

const list = ref([])
const loading = ref(false)
const acting = ref(false)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ search: '', status: '', date_from: '', date_to: '' })
const selectedBon = ref(null)
let timer = null

onMounted(() => { loadData(); listenBon(() => loadData()) })
onUnmounted(() => stopBon())

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/bon-pengeluaran', { params: { ...filters.value, page: meta.value.page, per_page: 15 } })
    list.value = res.data.data
    meta.value = res.data.meta
  } finally {
    loading.value = false
    window.clearModalBackdrop?.()
  }
}

function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400) }
function changePage(p) { meta.value.page = p; loadData() }
function resetFilters() { filters.value = { search: '', status: '', date_from: '', date_to: '' }; meta.value.page = 1; loadData() }

async function openDetail(bon) {
  try {
    const res = await axios.get(`/bon-pengeluaran/${bon.id}`)
    selectedBon.value = res.data.data
    new Modal('#modalDetailBon').show()
  } catch { toast.error('Gagal memuat detail') }
}

async function doIssue(bon) {
  if (!confirm(`Keluarkan barang dari bon ${bon.bon_number}? Stok gudang akan berkurang.`)) return
  try {
    await axios.post(`/bon-pengeluaran/${bon.id}/issue`)
    toast.success('Barang berhasil dikeluarkan, stok dikurangi')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
}

async function doIssueFromModal() {
  acting.value = true
  try {
    await axios.post(`/bon-pengeluaran/${selectedBon.value.id}/issue`)
    toast.success('Barang berhasil dikeluarkan, stok dikurangi')
    const modalEl = document.getElementById('modalDetailBon')
    modalEl?.addEventListener('hidden.bs.modal', () => loadData(), { once: true })
    Modal.getInstance(modalEl)?.hide()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function printBonDirect(bon) {
  try {
    const res = await axios.get(`/bon-pengeluaran/${bon.id}`)
    printBon(res.data.data)
  } catch { toast.error('Gagal memuat data bon') }
}

function printBon(bon) {
  const fmtD = (v) => v ? new Date(v).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'}) : '-'
  const sLabel = bon.status==='issued'?'Sudah Dikeluarkan':bon.status==='approved'?'Disetujui':'Draft'
  const sColor = bon.status==='issued'?'#16a34a':bon.status==='approved'?'#2563eb':'#6b7280'
  const mrNum  = bon.material_request?.mr_number || bon.permintaan_material?.pm_number || '-'

  const rows = (bon.items||[]).map((item,i) =>
    '<tr style="background:' + (i%2?'#f8fafc':'#fff') + '">' +
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;color:#64748b">'+(i+1)+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 10px;font-weight:600">'+(item.nama_barang||'-')+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;font-weight:700;color:#1a3a5c">'+item.qty+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px">'+(item.satuan||'-')+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 10px;color:#64748b">'+(item.keterangan||'-')+'</td>'+
    '</tr>'
  ).join('')

  const css =
    '*{margin:0;padding:0;box-sizing:border-box}' +
    'body{font-family:Arial,sans-serif;font-size:10pt;color:#1f2937;padding:20px}' +
    '@media print{body{padding:0}@page{margin:15mm 12mm;size:A4}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}}' +
    '.hdr{background:#1a3a5c;color:#fff;padding:14px 20px;border-radius:8px 8px 0 0}' +
    '.hdr h1{font-size:15pt;font-weight:800}' +
    '.hdr2{background:#2563a8;color:#fff;padding:7px 20px;display:flex;align-items:center;gap:10px}' +
    '.bdg{padding:3px 12px;border-radius:20px;font-size:8pt;font-weight:700;color:#fff}' +
    '.igrid{display:grid;grid-template-columns:1fr 1fr;border:1px solid #e2e8f0;border-top:none}' +
    '.isec{padding:12px 16px}.isec:first-child{border-right:1px solid #e2e8f0}' +
    '.ititle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #e8edf4}' +
    '.irow{display:flex;margin-bottom:4px;font-size:9pt}' +
    '.ilbl{color:#64748b;width:130px;flex-shrink:0}' +
    '.ival{font-weight:600;color:#1a3a5c}.ival2{color:#374151}' +
    'table.it{width:100%;border-collapse:collapse;margin-top:14px}' +
    'table.it th{background:#1a3a5c;color:#fff;padding:8px 10px;font-size:9pt;font-weight:700}' +
    '.sgrid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:24px}' +
    '.sbox{border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 12px}' +
    '.stitle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;text-align:center;background:#e8edf4;margin:-8px -12px 8px;padding:6px;border-radius:4px 4px 0 0}' +
    '.sspace{height:45px;border-bottom:1.5px solid #e2e8f0;margin-bottom:6px}' +
    '.sname{font-size:9pt;font-weight:600;color:#1a3a5c;text-align:center}'

  // Gunakan string concat untuk tag HTML — hindari literal closing tag di source Vue
  const o = '<', c = '>'
  const stO = o+'style'+c, stC = o+'/style'+c
  const htO = o+'html'+c,  htC = o+'/html'+c
  const hdC = o+'/head'+c, bdC = o+'/body'+c

  const html =
    '<!DOCTYPE html>'+htO+
    o+'head'+c+o+'meta charset="UTF-8"/'+c+o+'title'+'>'+'BON-'+bon.bon_number+o+'/title'+c+
    stO+css+stC+hdC+
    o+'body'+c+
    '<div class="hdr"><h1>PT. CIPTA SARANA MAKMUR</h1></div>'+
    '<div class="hdr2">'+
      '<span style="font-size:11pt;font-weight:700">BON PENGELUARAN BARANG</span>'+
      '<span style="font-size:11pt;font-weight:800;background:#fff;color:#2563a8;padding:2px 12px;border-radius:4px">'+bon.bon_number+'</span>'+
      '<span class="bdg" style="background:'+sColor+'">'+sLabel.toUpperCase()+'</span>'+
    '</div>'+
    '<div class="igrid">'+
      '<div class="isec">'+
        '<div class="ititle">Informasi Bon</div>'+
        '<div class="irow"><span class="ilbl">No. Bon</span><span class="ival">'+bon.bon_number+'</span></div>'+
        '<div class="irow"><span class="ilbl">No. MR / PM</span><span class="ival2">'+mrNum+'</span></div>'+
        '<div class="irow"><span class="ilbl">Gudang</span><span class="ival">'+(bon.warehouse?.name||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">No. PO / WO</span><span class="ival2">'+(bon.po_number||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Dibuat Oleh</span><span class="ival2">'+(bon.creator?.name||'-')+'</span></div>'+
      '</div>'+
      '<div class="isec">'+
        '<div class="ititle">Detail Pengeluaran</div>'+
        '<div class="irow"><span class="ilbl">Diterima Oleh</span><span class="ival">'+(bon.received_by||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Mekanik</span><span class="ival2">'+(bon.mechanic||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Tgl. Keluar</span><span class="ival2">'+fmtD(bon.issue_date)+'</span></div>'+
        '<div class="irow"><span class="ilbl">Kode Unit</span><span class="ival2">'+(bon.unit_code||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Tipe Unit</span><span class="ival2">'+(bon.unit_type||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">HM / KM</span><span class="ival2">'+(bon.hm_km||'-')+'</span></div>'+
      '</div>'+
    '</div>'+
    '<table class="it">'+
      '<thead><tr>'+
        '<th style="text-align:center;width:36px">#</th>'+
        '<th style="text-align:left">Nama Barang</th>'+
        '<th style="text-align:center;width:60px">Qty</th>'+
        '<th style="text-align:center;width:70px">Satuan</th>'+
        '<th style="text-align:left">Keterangan</th>'+
      '</tr></thead>'+
      '<tbody>'+rows+'</tbody>'+
    '</table>'+
    '<div class="sgrid">'+
      '<div class="sbox"><div class="stitle">Dibuat Oleh</div><div class="sspace"></div><div class="sname">'+(bon.creator?.name||'')+'</div></div>'+
      '<div class="sbox"><div class="stitle">Diterima Oleh</div><div class="sspace"></div><div class="sname">'+(bon.received_by||'')+'</div></div>'+
      '<div class="sbox"><div class="stitle">Mengetahui</div><div class="sspace"></div><div class="sname"></div></div>'+
    '</div>'+
    bdC+htC

  const win = window.open('', '_blank', 'width=900,height=700')
  win.document.write(html)
  win.document.close()
  win.onload = () => { win.focus(); win.print() }
}
</script>