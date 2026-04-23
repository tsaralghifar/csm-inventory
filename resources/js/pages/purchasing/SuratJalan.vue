<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Tanda Terima Pembelian</h5>
        <small class="text-muted">Tanda terima barang yang dibeli dari vendor</small>
      </div>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. TTP..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="received">Diterima</option>
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
                <th>No. TTP</th>
                <th>No. PO</th>
                <th>Vendor</th>
                <th>Gudang</th>
                <th>Item</th>
                <th>Driver</th>
                <th>Penerima Barang</th>
                <th>Tgl. Terima</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="10" class="text-center text-muted py-5">Belum ada Tanda Terima Pembelian</td>
              </tr>
              <tr v-for="sj in list" :key="sj.id">
                <td class="fw-semibold" style="color:#1a3a5c;">{{ sj.sj_number }}</td>
                <td><small class="text-muted">{{ sj.purchase_order?.po_number || '-' }}</small></td>
                <td><small>{{ sj.vendor_name || '-' }}</small></td>
                <td><small>{{ sj.warehouse?.name }}</small></td>
                <td><span class="badge bg-secondary rounded-pill">{{ sj.items_count }} item</span></td>
                <td><small>{{ sj.driver_name || '-' }}</small></td>
                <td>
                  <small v-if="sj.received_by_name" class="fw-semibold text-dark">
                    <i class="bi bi-person-check text-success me-1"></i>{{ sj.received_by_name }}
                  </small>
                  <small v-else class="text-muted">-</small>
                </td>
                <td><small class="text-muted">{{ sj.received_date ? $formatDate(sj.received_date) : '-' }}</small></td>
                <td>
                  <span class="badge" :class="sj.status === 'received' ? 'bg-success' : 'bg-warning text-dark'">
                    {{ sj.status === 'received' ? '✓ Diterima' : 'Draft' }}
                  </span>
                </td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="Detail" @click="openDetail(sj)">
                      <i class="bi bi-eye"></i>
                    </button>
                    <!-- Tombol Print hanya muncul jika sudah diterima -->
                    <button v-if="sj.status === 'received'" class="btn btn-outline-danger" title="Print PDF" @click="printSJDirect(sj)">
                      <i class="bi bi-printer"></i>
                    </button>
                    <button v-if="sj.status === 'draft' && can('receive-sj')"
                      class="btn btn-outline-success" title="Konfirmasi Terima Barang" @click="openConfirmModal(sj)">
                      <i class="bi bi-check-circle"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">Total {{ meta.total }} tanda terima pembelian</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail TTP -->
    <div class="modal fade" id="modalDetailSJ" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedSJ">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-file-earmark-check me-2"></i>{{ selectedSJ.sj_number }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted w-40">No. TTP</td><td class="fw-semibold">{{ selectedSJ.sj_number }}</td></tr>
                  <tr><td class="text-muted">No. PO</td><td>{{ selectedSJ.purchase_order?.po_number || '-' }}</td></tr>
                  <tr><td class="text-muted">Vendor</td><td>{{ selectedSJ.vendor_name || '-' }}</td></tr>
                  <tr><td class="text-muted">Driver</td><td>{{ selectedSJ.driver_name || '-' }}</td></tr>
                  <tr><td class="text-muted">No. Kendaraan</td><td>{{ selectedSJ.vehicle_plate || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                  <tr><td class="text-muted w-40">Gudang</td><td>{{ selectedSJ.warehouse?.name }}</td></tr>
                  <tr><td class="text-muted">Status</td>
                    <td><span class="badge" :class="selectedSJ.status === 'received' ? 'bg-success' : 'bg-warning text-dark'">
                      {{ selectedSJ.status === 'received' ? 'Diterima' : 'Draft' }}
                    </span></td>
                  </tr>
                  <tr><td class="text-muted">Tgl. Terima</td><td>{{ selectedSJ.received_date ? $formatDate(selectedSJ.received_date) : '-' }}</td></tr>
                  <tr>
                    <td class="text-muted">Penerima Barang</td>
                    <td>
                      <span v-if="selectedSJ.received_by_name" class="fw-semibold">
                        <i class="bi bi-person-check text-success me-1"></i>{{ selectedSJ.received_by_name }}
                      </span>
                      <span v-else class="text-muted">-</span>
                    </td>
                  </tr>
                  <tr><td class="text-muted">Catatan</td><td>{{ selectedSJ.notes || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm csm-table mb-0">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Part Number</th>
                    <th>Nama Barang</th>
                    <th class="text-end">Qty PO</th>
                    <th class="text-end">Qty Diterima</th>
                    <th>Satuan</th>
                    <th>Masuk Stok</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in selectedSJ.items" :key="item.id">
                    <td class="text-muted">{{ idx+1 }}</td>
                    <td>
                      <code v-if="item.item?.part_number" class="small text-primary fw-semibold">{{ item.item.part_number }}</code>
                      <span v-else class="text-muted small">-</span>
                    </td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td class="text-end text-muted">{{ item.qty_ordered }}</td>
                    <td class="text-end fw-bold">{{ item.qty_received }}</td>
                    <td>{{ item.satuan }}</td>
                    <td>
                      <span class="badge" :class="item.masuk_stok ? 'bg-success' : 'bg-secondary'">
                        {{ item.masuk_stok ? '✓ Ya' : 'Tidak' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="selectedSJ.status === 'draft'" class="alert alert-info mt-3 small py-2 mb-0">
              <i class="bi bi-info-circle me-1"></i>
              Klik <strong>Konfirmasi Terima</strong> untuk mengkonfirmasi penerimaan barang dan isi nama penerima.
              Barang yang bertanda <strong>Masuk Stok = Ya</strong> akan otomatis masuk ke stok gudang.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <!-- Print hanya muncul jika sudah diterima -->
            <button v-if="selectedSJ.status === 'received'" type="button" class="btn btn-outline-danger btn-sm" @click="printSJ(selectedSJ)">
              <i class="bi bi-printer me-1"></i>Print PDF
            </button>
            <button v-if="selectedSJ.status === 'draft' && can('receive-sj')"
              type="button" class="btn btn-success btn-sm" @click="openConfirmFromDetail" :disabled="acting">
              <i class="bi bi-check-circle me-1"></i>Konfirmasi Terima Barang
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Konfirmasi Terima Barang -->
    <div class="modal fade" id="modalKonfirmasiTerima" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-success bg-opacity-10">
            <h6 class="modal-title text-success">
              <i class="bi bi-check-circle me-2"></i>Konfirmasi Terima Barang
            </h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-success small py-2 mb-3">
              <i class="bi bi-info-circle me-1"></i>
              Barang dari <strong>{{ confirmTarget?.sj_number }}</strong> ({{ confirmTarget?.vendor_name || 'vendor' }})
              akan dikonfirmasi diterima dan otomatis masuk ke stok gudang.
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold small">
                <i class="bi bi-person-check me-1"></i>Nama Penerima Barang <span class="text-danger">*</span>
              </label>
              <input
                v-model="confirmForm.received_by_name"
                type="text"
                class="form-control"
                placeholder="Nama lengkap orang yang menerima barang..."
              />
              <div class="form-text">Nama orang yang secara fisik menerima dan mengecek barang dari vendor/driver.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button
              type="button"
              class="btn btn-success btn-sm"
              @click="doConfirmReceive"
              :disabled="acting || !confirmForm.received_by_name.trim()">
              <span v-if="acting" class="csm-spinner me-1"></span>
              <i class="bi bi-check-circle me-1"></i>Konfirmasi Diterima
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
const { listenSJ, stopSJ } = useRealtime()
const can = (p) => auth.hasPermission(p)

const list = ref([])
const loading = ref(false)
const acting = ref(false)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ search: '', status: '', date_from: '', date_to: '' })
const selectedSJ = ref(null)
const confirmTarget = ref(null)
const confirmForm = ref({ received_by_name: '' })
let timer = null

onMounted(() => { loadData(); listenSJ(() => loadData()) })
onUnmounted(() => stopSJ())

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/surat-jalan', { params: { ...filters.value, page: meta.value.page, per_page: 15 } })
    list.value = res.data.data
    meta.value = res.data.meta
  } catch (e) {
    toast.error('Gagal memuat data')
  } finally {
    loading.value = false
    window.clearModalBackdrop?.()
  }
}

function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400) }
function changePage(p) { meta.value.page = p; loadData() }
function resetFilters() { filters.value = { search: '', status: '', date_from: '', date_to: '' }; meta.value.page = 1; loadData() }

async function openDetail(sj) {
  try {
    const res = await axios.get(`/surat-jalan/${sj.id}`)
    selectedSJ.value = res.data.data
    new Modal('#modalDetailSJ').show()
  } catch { toast.error('Gagal memuat detail') }
}

function openConfirmModal(sj) {
  confirmTarget.value = sj
  confirmForm.value = { received_by_name: '' }
  new Modal('#modalKonfirmasiTerima').show()
}

function openConfirmFromDetail() {
  confirmTarget.value = selectedSJ.value
  confirmForm.value = { received_by_name: '' }
  Modal.getInstance('#modalDetailSJ')?.hide()
  setTimeout(() => new Modal('#modalKonfirmasiTerima').show(), 300)
}

async function doConfirmReceive() {
  if (!confirmForm.value.received_by_name.trim()) return toast.error('Nama penerima wajib diisi')
  acting.value = true
  try {
    await axios.post(`/surat-jalan/${confirmTarget.value.id}/receive`, {
      received_by_name: confirmForm.value.received_by_name.trim(),
    })
    toast.success(`Barang berhasil diterima oleh ${confirmForm.value.received_by_name}. Stok gudang bertambah.`)
    const modalEl = document.getElementById('modalKonfirmasiTerima')
    modalEl?.addEventListener('hidden.bs.modal', () => loadData(), { once: true })
    Modal.getInstance(modalEl)?.hide()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal konfirmasi penerimaan')
  } finally { acting.value = false }
}

// ── Print PDF ─────────────────────────────────────────────────────────────────
async function printSJDirect(sj) {
  try {
    const res = await axios.get(`/surat-jalan/${sj.id}`)
    printSJ(res.data.data)
  } catch { toast.error('Gagal memuat data') }
}

function printSJ(sj) {
  const fmtD = (v) => v ? new Date(v).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'}) : '-'
  const poNum = sj.purchase_order?.po_number || '-'

  const rows = (sj.items||[]).map((item,i) =>
    '<tr style="background:' + (i%2?'#f8fafc':'#fff') + '">' +
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;color:#64748b">'+(i+1)+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 8px;font-family:monospace;font-weight:700;color:#1a3a5c;font-size:9pt">'+(item.item?.part_number||'-')+'</td>'+
    '<td style="border:1px solid #e2e8f0;padding:6px 10px;font-weight:600;color:#1f2937">'+(item.nama_barang||'-')+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;color:#64748b">'+(item.qty_ordered||'-')+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px;font-weight:700;color:#16a34a">'+(item.qty_received||'-')+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px">'+(item.satuan||'-')+'</td>'+
    '<td style="text-align:center;border:1px solid #e2e8f0;padding:6px 8px">'+(item.masuk_stok?'<span style="background:#16a34a;color:#fff;padding:2px 8px;border-radius:12px;font-size:8pt;font-weight:700">✓ Ya</span>':'<span style="background:#6b7280;color:#fff;padding:2px 8px;border-radius:12px;font-size:8pt">Tidak</span>')+'</td>'+
    '</tr>'
  ).join('')

  const css =
    '*{margin:0;padding:0;box-sizing:border-box}'+
    'body{font-family:Arial,sans-serif;font-size:10pt;color:#1f2937;padding:20px}'+
    '@media print{body{padding:0}@page{margin:15mm 12mm;size:A4}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}}'+
    '.hdr{background:#1a3a5c;color:#fff;padding:14px 20px;border-radius:8px 8px 0 0}'+
    '.hdr h1{font-size:15pt;font-weight:800}'+
    '.hdr2{background:#16a34a;color:#fff;padding:7px 20px;display:flex;align-items:center;gap:12px;border-radius:0}'+
    '.igrid{display:grid;grid-template-columns:1fr 1fr;border:1px solid #e2e8f0;border-top:none}'+
    '.isec{padding:12px 16px}.isec:first-child{border-right:1px solid #e2e8f0}'+
    '.ititle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #e8edf4}'+
    '.irow{display:flex;margin-bottom:5px;font-size:9pt}'+
    '.ilbl{color:#64748b;width:140px;flex-shrink:0}'+
    '.ival{font-weight:600;color:#1a3a5c}.ival2{color:#374151}'+
    'table.it{width:100%;border-collapse:collapse;margin-top:14px}'+
    'table.it th{background:#1a3a5c;color:#fff;padding:8px 10px;font-size:9pt;font-weight:700}'+
    '.sgrid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:24px}'+
    '.sbox{border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 12px}'+
    '.stitle{font-size:8pt;font-weight:700;color:#1a3a5c;text-transform:uppercase;text-align:center;background:#e8edf4;margin:-8px -12px 8px;padding:6px;border-radius:4px 4px 0 0}'+
    '.sspace{height:45px;border-bottom:1.5px solid #e2e8f0;margin-bottom:6px}'+
    '.sname{font-size:9pt;font-weight:600;color:#1a3a5c;text-align:center}'

  const o = '<', c = '>'
  const stO = o+'style'+c, stC = o+'/style'+c
  const htO = o+'html'+c,  htC = o+'/html'+c
  const hdC = o+'/head'+c, bdC = o+'/body'+c

  const html =
    '<!DOCTYPE html>'+htO+
    o+'head'+c+o+'meta charset="UTF-8"/'+c+o+'title'+'>'+'TTP-'+sj.sj_number+o+'/title'+c+
    stO+css+stC+hdC+
    o+'body'+c+
    '<div class="hdr"><h1>PT. CIPTA SARANA MAKMUR</h1></div>'+
    '<div class="hdr2">'+
      '<span style="font-size:11pt;font-weight:700">TANDA TERIMA PEMBELIAN</span>'+
      '<span style="font-size:11pt;font-weight:800;background:#fff;color:#16a34a;padding:2px 12px;border-radius:4px">'+sj.sj_number+'</span>'+
      '<span style="background:#fff;color:#16a34a;padding:2px 10px;border-radius:12px;font-size:8pt;font-weight:700">✓ DITERIMA</span>'+
    '</div>'+
    '<div class="igrid">'+
      '<div class="isec">'+
        '<div class="ititle">Informasi Pengiriman</div>'+
        '<div class="irow"><span class="ilbl">No. TTP</span><span class="ival">'+sj.sj_number+'</span></div>'+
        '<div class="irow"><span class="ilbl">No. PO</span><span class="ival2">'+poNum+'</span></div>'+
        '<div class="irow"><span class="ilbl">Vendor</span><span class="ival">'+(sj.vendor_name||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Driver</span><span class="ival2">'+(sj.driver_name||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">No. Kendaraan</span><span class="ival2">'+(sj.vehicle_plate||'-')+'</span></div>'+
      '</div>'+
      '<div class="isec">'+
        '<div class="ititle">Penerimaan</div>'+
        '<div class="irow"><span class="ilbl">Gudang Tujuan</span><span class="ival">'+(sj.warehouse?.name||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Tgl. Terima</span><span class="ival2">'+fmtD(sj.received_date)+'</span></div>'+
        '<div class="irow"><span class="ilbl">Penerima Barang</span><span class="ival" style="color:#16a34a">'+(sj.received_by_name||'-')+'</span></div>'+
        '<div class="irow"><span class="ilbl">Dibuat Oleh</span><span class="ival2">'+(sj.creator?.name||'-')+'</span></div>'+
        (sj.notes ? '<div class="irow"><span class="ilbl">Catatan</span><span class="ival2">'+sj.notes+'</span></div>' : '')+
      '</div>'+
    '</div>'+
    '<table class="it">'+
      '<thead><tr>'+
        '<th style="text-align:center;width:36px">#</th>'+
        '<th style="text-align:center;width:110px">Part Number</th>'+
        '<th style="text-align:left">Nama Barang</th>'+
        '<th style="text-align:center;width:65px">Qty PO</th>'+
        '<th style="text-align:center;width:75px">Qty Terima</th>'+
        '<th style="text-align:center;width:65px">Satuan</th>'+
        '<th style="text-align:center;width:75px">Masuk Stok</th>'+
      '</tr></thead>'+
      '<tbody>'+rows+'</tbody>'+
    '</table>'+
    '<div class="sgrid">'+
      '<div class="sbox"><div class="stitle">Dibuat Oleh</div><div class="sspace"></div><div class="sname">'+(sj.creator?.name||'')+'</div></div>'+
      '<div class="sbox"><div class="stitle">Penerima Barang</div><div class="sspace"></div><div class="sname">'+(sj.received_by_name||'')+'</div></div>'+
      '<div class="sbox"><div class="stitle">Mengetahui</div><div class="sspace"></div><div class="sname"></div></div>'+
    '</div>'+
    bdC+htC

  const win = window.open('', '_blank', 'width=900,height=700')
  win.document.write(html)
  win.document.close()
  win.onload = () => { win.focus(); win.print() }
}
</script>