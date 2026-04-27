<template>
  <div>

    <!-- PAGE HEADER -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Laporan Stok Persediaan</h5>
        <small class="text-muted">Pantau dan export data stok di seluruh gudang</small>
      </div>
      <button v-if="loaded" class="btn btn-success btn-sm" @click="exportExcel">
        <i class="bi bi-file-earmark-excel me-1"></i>Export
      </button>
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
                <th class="text-end" style="width:130px">Harga</th>
                <th class="text-end" style="width:140px">Nilai</th>
                <th class="text-center" style="width:75px">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredStocks.length">
                <td :colspan="params.warehouse_id ? 10 : 11" class="text-center text-muted py-5">
                  <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                  <span v-if="searchQuery">Tidak ada hasil untuk "<strong>{{ searchQuery }}</strong>"</span>
                  <span v-else>Tidak ada data stok</span>
                </td>
              </tr>
              <tr v-for="(s, i) in filteredStocks" :key="s.id"
                :class="{
                  'table-danger':  parseFloat(s.qty) < 0,
                  'table-warning': parseFloat(s.qty) >= 0 && parseFloat(s.qty) <= parseFloat(s.item?.min_stock) && parseFloat(s.item?.min_stock) > 0
                }">
                <td class="ps-3 text-muted small">{{ i+1 }}</td>
                <td>
                  <code class="small" style="color:#1a3a5c;font-size:0.75rem;white-space:nowrap;">
                    {{ s.item?.part_number || '—' }}
                  </code>
                </td>
                <td>
                  <div class="small fw-semibold" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;">
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
                      style="font-size:0.68rem;white-space:nowrap;"
                      :title="g.name + ': ' + g.qty">
                      {{ g.name.replace('Gudang ','') }}
                      <span class="ms-1 fw-normal opacity-75">({{ g.qty }})</span>
                    </span>
                    <span v-if="!s.gudang?.length" class="text-muted small">—</span>
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
                <td class="text-end small">{{ s.avg_price>0 ? $formatCurrency(s.avg_price) : '—' }}</td>
                <td class="text-end small fw-semibold">
                  {{ s.avg_price>0 ? $formatCurrency(Math.max(0,parseFloat(s.qty))*s.avg_price) : '—' }}
                </td>
                <td class="text-center">
                  <span v-if="parseFloat(s.qty)<0" class="badge bg-danger" style="font-size:0.68rem;">Minus</span>
                  <span v-else-if="parseFloat(s.qty)<=parseFloat(s.item?.min_stock) && parseFloat(s.item?.min_stock)>0"
                    class="badge bg-warning text-dark" style="font-size:0.68rem;">Kritis</span>
                  <span v-else class="badge bg-success" style="font-size:0.68rem;">Normal</span>
                </td>
              </tr>
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
              {{ $formatCurrency(filteredStocks.reduce((a,s)=>a+(s.avg_price>0?Math.max(0,parseFloat(s.qty))*s.avg_price:0),0)) }}
            </strong>
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
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'

const auth = useAuthStore(); const toast = useToast()
const warehouses = ref([]); const stocks = ref([]); const loading = ref(false); const loaded = ref(false)
const summary = ref({ total_items: 0, total_value: 0, critical: 0, minus: 0 })
const params = ref({ warehouse_id: '', filter: '' })
const searchQuery = ref('')

const filteredStocks = computed(() => {
  if (!searchQuery.value.trim()) return stocks.value
  const q = searchQuery.value.toLowerCase()
  return stocks.value.filter(s =>
    s.item?.name?.toLowerCase().includes(q) ||
    s.item?.part_number?.toLowerCase().includes(q) ||
    s.item?.brand?.toLowerCase().includes(q)
  )
})

onMounted(async () => {
  const r = await axios.get('/warehouses'); warehouses.value = r.data.data
  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) params.value.warehouse_id = auth.userWarehouseId
})

async function load() {
  loading.value = true
  try {
    const r = await axios.get('/reports/stock', { params: params.value })
    stocks.value = r.data.data; summary.value = r.data.summary; loaded.value = true
  } finally { loading.value = false }
}

function resetFilter() {
  searchQuery.value = ''
  params.value.filter = ''
}

function setFilter(type) {
  params.value.filter = params.value.filter === type ? '' : type
}

async function exportExcel() {
  // Load xlsx-js-style (supports cell styling unlike free SheetJS)
  if (!window._XLSXLoaded) {
    await new Promise((resolve, reject) => {
      const script = document.createElement('script')
      script.src = 'https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js'
      script.onload = () => { window._XLSXLoaded = true; resolve() }
      script.onerror = reject
      document.head.appendChild(script)
    })
  }
  const XLSX = window.XLSX

  const warehouseName = warehouses.value.find(w => w.id == params.value.warehouse_id)?.name || 'Semua Gudang'
  const now = new Date()
  const dateStr = now.toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' })
  const timeStr = now.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' })
  const fmtCurrency = v => v > 0 ? 'Rp ' + Number(v).toLocaleString('id-ID') : '-'

  const wb = XLSX.utils.book_new()
  const ws = {}
  ws['!merges'] = []

  ws['!cols'] = [
    { wch: 4 },  { wch: 16 }, { wch: 24 }, { wch: 14 }, { wch: 12 }, { wch: 18 },
    { wch: 8 },  { wch: 8 },  { wch: 9 },  { wch: 16 }, { wch: 18 }, { wch: 10 }
  ]

  const B = (style = 'thin', color = 'D0D9E8') => ({ style, color: { rgb: color } })
  const border = (t='thin',b='thin',l='thin',r='thin') => ({ top: B(t), bottom: B(b), left: B(l), right: B(r) })

  const sc = (r, c, v, s) => {
    const addr = XLSX.utils.encode_cell({ r, c })
    ws[addr] = { v: v ?? '', t: typeof v === 'number' ? 'n' : 's', s: s || {} }
  }
  const mg = (r1,c1,r2,c2) => ws['!merges'].push({ s:{r:r1,c:c1}, e:{r:r2,c:c2} })

  const S = {
    h1: { font:{bold:true,sz:15,color:{rgb:'FFFFFF'},name:'Calibri'}, fill:{fgColor:{rgb:'1A3A5C'}}, alignment:{vertical:'center',indent:1} },
    h2: { font:{sz:10,color:{rgb:'BFD0E8'},name:'Calibri'}, fill:{fgColor:{rgb:'243F6A'}}, alignment:{vertical:'center',indent:1} },
    infoLbl: { font:{bold:true,sz:9,color:{rgb:'1A3A5C'}}, fill:{fgColor:{rgb:'D6E4F5'}}, alignment:{vertical:'center',indent:1}, border:border() },
    infoVal: { font:{sz:9,color:{rgb:'1A3A5C'}}, fill:{fgColor:{rgb:'EBF3FD'}}, alignment:{vertical:'center',indent:1}, border:border() },
    sumLblBlue:   { font:{bold:true,sz:8,color:{rgb:'1E40AF'}}, fill:{fgColor:{rgb:'DBEAFE'}}, alignment:{horizontal:'center',vertical:'center'}, border:{top:B('medium','93C5FD'),left:B('medium','93C5FD'),right:B('medium','93C5FD'),bottom:B('thin','BFD7FF')} },
    sumLblGreen:  { font:{bold:true,sz:8,color:{rgb:'065F46'}}, fill:{fgColor:{rgb:'D1FAE5'}}, alignment:{horizontal:'center',vertical:'center'}, border:{top:B('medium','6EE7B7'),left:B('medium','6EE7B7'),right:B('medium','6EE7B7'),bottom:B('thin','A7F3D0')} },
    sumLblYellow: { font:{bold:true,sz:8,color:{rgb:'92400E'}}, fill:{fgColor:{rgb:'FEF3C7'}}, alignment:{horizontal:'center',vertical:'center'}, border:{top:B('medium','FCD34D'),left:B('medium','FCD34D'),right:B('medium','FCD34D'),bottom:B('thin','FDE68A')} },
    sumLblRed:    { font:{bold:true,sz:8,color:{rgb:'991B1B'}}, fill:{fgColor:{rgb:'FEE2E2'}}, alignment:{horizontal:'center',vertical:'center'}, border:{top:B('medium','FCA5A5'),left:B('medium','FCA5A5'),right:B('medium','FCA5A5'),bottom:B('thin','FECACA')} },
    sumValBlue:   { font:{bold:true,sz:16,color:{rgb:'1D4ED8'}}, fill:{fgColor:{rgb:'EFF6FF'}}, alignment:{horizontal:'center',vertical:'center'}, border:{bottom:B('medium','93C5FD'),left:B('medium','93C5FD'),right:B('medium','93C5FD'),top:B('thin','BFD7FF')} },
    sumValGreen:  { font:{bold:true,sz:12,color:{rgb:'047857'}}, fill:{fgColor:{rgb:'ECFDF5'}}, alignment:{horizontal:'center',vertical:'center'}, border:{bottom:B('medium','6EE7B7'),left:B('medium','6EE7B7'),right:B('medium','6EE7B7'),top:B('thin','A7F3D0')} },
    sumValYellow: { font:{bold:true,sz:16,color:{rgb:'D97706'}}, fill:{fgColor:{rgb:'FFFBEB'}}, alignment:{horizontal:'center',vertical:'center'}, border:{bottom:B('medium','FCD34D'),left:B('medium','FCD34D'),right:B('medium','FCD34D'),top:B('thin','FDE68A')} },
    sumValRed:    { font:{bold:true,sz:16,color:{rgb:'DC2626'}}, fill:{fgColor:{rgb:'FFF5F5'}}, alignment:{horizontal:'center',vertical:'center'}, border:{bottom:B('medium','FCA5A5'),left:B('medium','FCA5A5'),right:B('medium','FCA5A5'),top:B('thin','FECACA')} },
    th: { font:{bold:true,sz:10,color:{rgb:'FFFFFF'},name:'Calibri'}, fill:{fgColor:{rgb:'1A3A5C'}}, alignment:{horizontal:'center',vertical:'center',wrapText:true}, border:{top:B('medium','FFFFFF'),bottom:B('medium','4A7DB5'),left:B('thin','4A7DB5'),right:B('thin','4A7DB5')} },
    rowEven: (x={}) => ({ font:{sz:10}, fill:{fgColor:{rgb:'FFFFFF'}}, border:border(), ...x }),
    rowOdd:  (x={}) => ({ font:{sz:10}, fill:{fgColor:{rgb:'F0F5FF'}}, border:border(), ...x }),
    totalLbl: { font:{bold:true,sz:10,color:{rgb:'1A3A5C'}}, fill:{fgColor:{rgb:'D6E4F5'}}, alignment:{horizontal:'right',vertical:'center',indent:1}, border:{top:B('medium','1A3A5C'),bottom:B('medium','1A3A5C'),left:B('medium','1A3A5C'),right:B('thin','D0D9E8')} },
    totalVal: { font:{bold:true,sz:11,color:{rgb:'047857'}}, fill:{fgColor:{rgb:'DCFCE7'}}, alignment:{horizontal:'right',vertical:'center'}, border:{top:B('medium','1A3A5C'),bottom:B('medium','1A3A5C'),left:B('thin','D0D9E8'),right:B('medium','1A3A5C')} },
    totalMid: { fill:{fgColor:{rgb:'D6E4F5'}}, border:{top:B('medium','1A3A5C'),bottom:B('medium','1A3A5C'),left:B('thin','D0D9E8'),right:B('thin','D0D9E8')} },
    totalEnd: { fill:{fgColor:{rgb:'D6E4F5'}}, border:{top:B('medium','1A3A5C'),bottom:B('medium','1A3A5C'),left:B('thin','D0D9E8'),right:B('medium','1A3A5C')} },
    footer: { font:{sz:8,color:{rgb:'AABBCC'},italic:true}, fill:{fgColor:{rgb:'F1F5F9'}}, alignment:{horizontal:'right',vertical:'center'} },
  }

  let R = 0

  // Row 0: Company header
  for (let c=0;c<=10;c++) sc(R,c,'',S.h1)
  sc(R,0,'PT. CIPTA SARANA MAKMUR',S.h1); mg(R,0,R,11)
  R++

  // Row 1: Title
  for (let c=0;c<=11;c++) sc(R,c,'',S.h2)
  sc(R,0,'LAPORAN STOK PERSEDIAAN',S.h2); mg(R,0,R,11)
  R++

  // Row 2: Info bar
  sc(R,0,'Gudang / Site',S.infoLbl); sc(R,1,'',S.infoLbl); mg(R,0,R,1)
  sc(R,2,warehouseName,S.infoVal); sc(R,3,'',S.infoVal); sc(R,4,'',S.infoVal); mg(R,2,R,4)
  sc(R,5,'Tanggal Export',S.infoLbl); sc(R,6,'',S.infoLbl); mg(R,5,R,6)
  sc(R,7,`${dateStr}  ${timeStr}`,S.infoVal); sc(R,8,'',S.infoVal); sc(R,9,'',S.infoVal); mg(R,7,R,9)
  sc(R,10,'',S.infoVal); sc(R,11,stocks.value.length,{...S.infoVal,font:{bold:true,sz:12,color:{rgb:'1A3A5C'}},alignment:{horizontal:'center',vertical:'center'}})
  R++

  // Row 3: Summary labels
  sc(R,0,'Total Jenis Barang',S.sumLblBlue);   sc(R,1,'',S.sumLblBlue);   sc(R,2,'',S.sumLblBlue);   mg(R,0,R,2)
  sc(R,3,'Nilai Stok',        S.sumLblGreen);  sc(R,4,'',S.sumLblGreen);  sc(R,5,'',S.sumLblGreen);  mg(R,3,R,5)
  sc(R,6,'Stok Kritis',       S.sumLblYellow); sc(R,7,'',S.sumLblYellow);                             mg(R,6,R,7)
  sc(R,8,'Stok Minus',        S.sumLblRed);    sc(R,9,'',S.sumLblRed);    sc(R,10,'',S.sumLblRed);   mg(R,8,R,10)
  R++

  // Row 4: Summary values
  sc(R,0,summary.value.total_items,             S.sumValBlue);   sc(R,1,'',S.sumValBlue);   sc(R,2,'',S.sumValBlue);   mg(R,0,R,2)
  sc(R,3,fmtCurrency(summary.value.total_value),S.sumValGreen);  sc(R,4,'',S.sumValGreen);  sc(R,5,'',S.sumValGreen);  mg(R,3,R,5)
  sc(R,6,summary.value.critical,                S.sumValYellow); sc(R,7,'',S.sumValYellow);                             mg(R,6,R,7)
  sc(R,8,summary.value.minus,                   S.sumValRed);    sc(R,9,'',S.sumValRed);    sc(R,10,'',S.sumValRed);   mg(R,8,R,10)
  R++

  // Row 5: Spacer
  R++

  // Row 6: Table header
  ;['#','Part Number','Nama Barang','Kategori','Brand','Gudang','Stok','Satuan','Stok Min','Harga','Nilai','Status']
    .forEach((h,c) => sc(R,c,h,S.th))
  R++

  const totalNilai = stocks.value.reduce((acc,s) => acc + (s.avg_price>0 ? Math.max(0,s.qty)*s.avg_price : 0), 0)

  stocks.value.forEach((s,i) => {
    const qty=s.qty, minStock=s.item?.min_stock||0
    const isMinus=qty<0, isKritis=!isMinus&&minStock>0&&qty<=minStock
    const nilai=s.avg_price>0?Math.max(0,qty)*s.avg_price:0
    const row=i%2===0?S.rowEven:S.rowOdd
    const qtyColor=isMinus?'DC2626':isKritis?'D97706':'15803D'
    const sLbl=isMinus?'Minus':isKritis?'Kritis':'Normal'
    const sFill=isMinus?'FEE2E2':isKritis?'FEF3C7':'DCFCE7'
    const sFont=isMinus?'DC2626':isKritis?'92400E':'15803D'

    sc(R,0,  i+1,                          row({font:{sz:10,color:{rgb:'94A3B8'}},alignment:{horizontal:'center',vertical:'center'}}))
    sc(R,1,  s.item?.part_number||'',      row({font:{bold:true,sz:10,color:{rgb:'1E3A5F'},name:'Courier New'},alignment:{vertical:'center'}}))
    sc(R,2,  s.item?.name||'',             row({font:{bold:true,sz:10},alignment:{vertical:'center'}}))
    sc(R,3,  s.item?.category?.name||'-',  row({font:{sz:9,color:{rgb:'475569'}},alignment:{horizontal:'center',vertical:'center'}}))
    sc(R,4,  s.item?.brand||'-',           row({font:{sz:9,color:{rgb:'64748B'}},alignment:{vertical:'center'}}))
    sc(R,5,  s.warehouse?.name||'-',       row({font:{sz:9,color:{rgb:'334155'}},alignment:{horizontal:'center',vertical:'center'}}))
    sc(R,6,  qty,                          row({font:{bold:true,sz:10,color:{rgb:qtyColor}},alignment:{horizontal:'center',vertical:'center'}}))
    sc(R,7,  s.item?.unit||'',             row({font:{sz:9,color:{rgb:'64748B'}},alignment:{horizontal:'center',vertical:'center'}}))
    sc(R,8,  minStock,                     row({font:{sz:9,color:{rgb:'64748B'}},alignment:{horizontal:'center',vertical:'center'}}))
    sc(R,9,  fmtCurrency(s.avg_price),     row({font:{sz:9},alignment:{horizontal:'right',vertical:'center'}}))
    sc(R,10, nilai>0?fmtCurrency(nilai):'-', row({font:{bold:true,sz:10},alignment:{horizontal:'right',vertical:'center'}}))
    sc(R,11, sLbl,                         row({font:{bold:true,sz:9,color:{rgb:sFont}},fill:{fgColor:{rgb:sFill}},alignment:{horizontal:'center',vertical:'center'}}))
    R++
  })

  // Total row
  sc(R,0,'TOTAL NILAI STOK',S.totalLbl); mg(R,0,R,9)
  for(let c=1;c<=9;c++) sc(R,c,'',S.totalMid)
  sc(R,10,fmtCurrency(totalNilai),S.totalVal)
  sc(R,11,'',S.totalEnd)
  R++

  // Footer
  sc(R,0,`Dicetak pada ${dateStr} pukul ${timeStr}  —  CSM Inventory System`,S.footer)
  mg(R,0,R,11)

  const rowCount = R+1
  ws['!rows'] = Array(rowCount).fill(null).map((_,i) => {
    if(i===0) return {hpt:32}; if(i===1) return {hpt:20}; if(i===2) return {hpt:22}
    if(i===3) return {hpt:16}; if(i===4) return {hpt:28}; if(i===5) return {hpt:8}
    if(i===6) return {hpt:24}; if(i===rowCount-2) return {hpt:22}; if(i===rowCount-1) return {hpt:16}
    return {hpt:19}
  })

  ws['!ref'] = XLSX.utils.encode_range({s:{r:0,c:0},e:{r:R,c:11}})
  XLSX.utils.book_append_sheet(wb, ws, 'Laporan Stok')
  XLSX.writeFile(wb, `Laporan_Stok_${warehouseName.replace(/\s+/g,'_')}_${now.toISOString().slice(0,10)}.xlsx`)
  toast.success('Export Excel (.xlsx) berhasil')
}
</script>