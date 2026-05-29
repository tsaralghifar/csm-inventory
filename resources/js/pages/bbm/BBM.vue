<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Solar / BBM</h5>
        <small class="text-muted">Pencatatan bahan bakar per site & unit alat berat</small>
      </div>
      <div class="d-flex gap-2">
        <button v-if="can('manage-fuel')" class="btn btn-outline-success btn-sm" @click="openModalMasuk()">
          <i class="bi bi-arrow-down-circle me-1"></i>Catat Masuk
        </button>
        <button v-if="can('manage-fuel')" class="btn btn-csm-primary btn-sm" @click="openModalKeluar()">
          <i class="bi bi-arrow-up-circle me-1"></i>Catat Keluar
        </button>
      </div>
    </div>

    <!-- Alert stok menipis -->
    <div v-if="alertStok.length" class="mb-3">
      <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-0">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span class="fw-semibold me-1">Stok Menipis:</span>
        <span v-for="(a, i) in alertStok" :key="a.warehouse_id">
          {{ a.warehouse_name }} <strong>{{ $formatNumber(a.stock_current) }} L</strong>
          <span v-if="i < alertStok.length - 1"> · </span>
        </span>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small mb-1">Site / Gudang</label>
            <select v-model="filters.warehouse_id" class="form-select form-select-sm" @change="resetAndLoad">
              <option value="">Semua Site</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-1">Bulan</label>
            <input v-model="filters.month" type="month" class="form-control form-control-sm" @change="resetAndLoad" />
          </div>
          <div class="col-md-3">
            <label class="form-label small mb-1">Kode Unit</label>
            <input v-model="filters.unit_code" class="form-control form-control-sm" placeholder="CSM 00..." @input="debouncedLoad" />
          </div>
        </div>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3">
        <div class="kpi-card kpi-success">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-value">{{ $formatNumber(summary?.total_in || 0) }}</div>
              <div class="kpi-label">Total Masuk (L)</div>
            </div>
            <i class="bi bi-arrow-down-circle kpi-icon"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card kpi-danger">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-value">{{ $formatNumber(summary?.total_out || 0) }}</div>
              <div class="kpi-label">Total Keluar (L)</div>
            </div>
            <i class="bi bi-arrow-up-circle kpi-icon"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card" :class="parseFloat(summary?.stock_end||0) < LOW_STOCK_THRESHOLD ? 'kpi-warning' : 'kpi-primary'">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-value">{{ $formatNumber(summary?.stock_end || 0) }}</div>
              <div class="kpi-label">Stok Akhir (L)</div>
              <div class="kpi-sub" v-if="parseFloat(summary?.stock_end||0) < LOW_STOCK_THRESHOLD">⚠ Di bawah batas aman</div>
            </div>
            <i class="bi bi-droplet-half kpi-icon"></i>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card kpi-info">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="kpi-value">{{ summary?.total_units || 0 }}</div>
              <div class="kpi-label">Unit Aktif</div>
              <div class="kpi-sub">{{ summary?.total_entries || 0 }} entri</div>
            </div>
            <i class="bi bi-truck kpi-icon"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="csm-card">
      <div class="csm-card-header">
        <ul class="nav nav-tabs card-header-tabs border-0 mb-n2">
          <li class="nav-item">
            <button class="nav-link" :class="{active: activeTab==='semua'}" @click="switchTab('semua')">
              <i class="bi bi-list-ul me-1"></i>Semua
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{active: activeTab==='masuk'}" @click="switchTab('masuk')">
              <i class="bi bi-arrow-down-circle me-1 text-success"></i>Solar Masuk
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{active: activeTab==='keluar'}" @click="switchTab('keluar')">
              <i class="bi bi-arrow-up-circle me-1 text-danger"></i>Solar Keluar
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{active: activeTab==='laporan'}" @click="switchTab('laporan')">
              <i class="bi bi-bar-chart me-1"></i>Laporan Unit
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{active: activeTab==='stok'}" @click="switchTab('stok')">
              <i class="bi bi-building me-1"></i>Stok per Site
            </button>
          </li>
        </ul>
        <div class="d-flex gap-1" v-if="activeTab !== 'laporan' && activeTab !== 'stok'">
          <button class="btn btn-xs btn-outline-secondary" @click="exportExcel">
            <i class="bi bi-download me-1"></i>Export
          </button>
        </div>
      </div>

      <div class="csm-card-body p-0">

        <!-- TAB: SEMUA / MASUK / KELUAR -->
        <div v-if="activeTab !== 'laporan' && activeTab !== 'stok'">
          <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>
          <div class="table-responsive" v-else>
            <table class="table csm-table mb-0">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Site</th>
                  <th v-if="activeTab !== 'masuk'">Unit</th>
                  <th v-if="activeTab !== 'masuk'">Tipe</th>
                  <th v-if="activeTab !== 'masuk'" class="text-end">HM/KM</th>
                  <th v-if="activeTab !== 'masuk'">Jam Isi</th>
                  <th class="text-end text-success">Masuk (L)</th>
                  <th class="text-end text-danger" v-if="activeTab !== 'masuk'">Keluar (L)</th>
                  <th class="text-end">Stok Sebelum</th>
                  <th class="text-end">Stok Setelah</th>
                  <th v-if="activeTab !== 'masuk'">Operator</th>
                  <th>Catatan</th>
                  <th v-if="can('manage-fuel')">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!logs.length">
                  <td colspan="13" class="text-center text-muted py-4">Tidak ada data</td>
                </tr>
                <tr v-for="log in logs" :key="log.id" :class="log.stock_in > 0 && log.liter_out == 0 ? 'table-success-subtle' : ''">
                  <td><small>{{ $formatDate(log.log_date) }}</small></td>
                  <td><small class="text-muted">{{ log.warehouse?.name }}</small></td>
                  <td v-if="activeTab !== 'masuk'">
                    <span class="fw-semibold text-primary small">{{ log.unit_code || '-' }}</span>
                  </td>
                  <td v-if="activeTab !== 'masuk'"><small>{{ log.unit_type || '-' }}</small></td>
                  <td v-if="activeTab !== 'masuk'" class="text-end"><small>{{ log.hm_km ? $formatNumber(log.hm_km) : '-' }}</small></td>
                  <td v-if="activeTab !== 'masuk'"><small>{{ log.fill_time || '-' }}</small></td>
                  <td class="text-end">
                    <span v-if="log.stock_in > 0" class="text-success fw-semibold">+{{ $formatNumber(log.stock_in) }}</span>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td v-if="activeTab !== 'masuk'" class="text-end">
                    <span v-if="log.liter_out > 0" class="text-danger fw-semibold">{{ $formatNumber(log.liter_out) }}</span>
                    <span v-else class="text-muted">-</span>
                  </td>
                  <td class="text-end"><small class="text-muted">{{ $formatNumber(log.stock_before) }}</small></td>
                  <td class="text-end">
                    <span :class="parseFloat(log.stock_after) < 0 ? 'stock-minus' : (parseFloat(log.stock_after) < LOW_STOCK_THRESHOLD ? 'stock-low' : 'stock-ok')">
                      {{ $formatNumber(log.stock_after) }}
                    </span>
                  </td>
                  <td v-if="activeTab !== 'masuk'"><small class="text-muted">{{ log.operator_name || '-' }}</small></td>
                  <td><small class="text-muted">{{ log.notes || '-' }}</small></td>
                  <td v-if="can('manage-fuel')">
                    <div class="d-flex gap-1">
                      <button class="btn btn-xs btn-outline-primary" @click="openModalEdit(log)" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-xs btn-outline-danger" @click="deleteLog(log)" title="Hapus">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- Pagination -->
          <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
            <small class="text-muted">{{ meta.total }} data</small>
            <div class="d-flex gap-1">
              <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹</button>
              <span class="btn btn-xs btn-outline-secondary disabled">{{ meta.page }} / {{ meta.last_page }}</span>
              <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">›</button>
            </div>
          </div>
        </div>

        <!-- TAB: LAPORAN UNIT -->
        <div v-else-if="activeTab === 'laporan'" class="p-3">
          <div v-if="loadingUnit" class="text-center py-4"><div class="csm-spinner"></div></div>
          <div v-else-if="!unitConsumption.length" class="text-center text-muted py-4">Tidak ada data konsumsi unit.</div>
          <div v-else>
            <!-- Barchart visual sederhana -->
            <div class="mb-4">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <small class="fw-semibold text-muted text-uppercase" style="letter-spacing:.5px;">Konsumsi per Unit (Liter)</small>
                <small class="text-muted">{{ filters.month }}</small>
              </div>
              <div v-for="u in unitConsumption.slice(0,15)" :key="u.unit_code" class="mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <div>
                    <span class="fw-semibold text-primary small">{{ u.unit_code || 'Tanpa Kode' }}</span>
                    <span class="text-muted small ms-2">{{ u.unit_type || '' }}</span>
                    <span v-if="u.division" class="badge bg-light text-muted ms-1" style="font-size:0.65rem;">{{ u.division }}</span>
                  </div>
                  <div class="text-end">
                    <span class="fw-bold text-danger">{{ $formatNumber(u.total_out) }} L</span>
                    <small class="text-muted ms-2">{{ u.fill_count }}× isi · rata-rata {{ $formatNumber(Math.round(u.avg_per_fill)) }} L</small>
                  </div>
                </div>
                <div class="progress" style="height:8px; border-radius:4px;">
                  <div class="progress-bar bg-danger" :style="{width: (u.total_out / unitConsumption[0].total_out * 100)+'%'}"></div>
                </div>
              </div>
            </div>

            <!-- Tabel detail -->
            <table class="table csm-table mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Kode Unit</th>
                  <th>Tipe</th>
                  <th>Divisi</th>
                  <th class="text-end">Total Keluar (L)</th>
                  <th class="text-end">Jml Pengisian</th>
                  <th class="text-end">Rata-rata/Isi (L)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(u, i) in unitConsumption" :key="u.unit_code">
                  <td><small class="text-muted">{{ i + 1 }}</small></td>
                  <td><span class="fw-semibold text-primary">{{ u.unit_code || '-' }}</span></td>
                  <td><small>{{ u.unit_type || '-' }}</small></td>
                  <td><small>{{ u.division || '-' }}</small></td>
                  <td class="text-end fw-bold text-danger">{{ $formatNumber(u.total_out) }}</td>
                  <td class="text-end">{{ u.fill_count }}</td>
                  <td class="text-end text-muted">{{ $formatNumber(Math.round(u.avg_per_fill)) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="table-light fw-bold">
                  <td colspan="4">Total</td>
                  <td class="text-end text-danger">{{ $formatNumber(summary?.total_out || 0) }}</td>
                  <td class="text-end">{{ unitConsumption.reduce((s, u) => s + parseInt(u.fill_count), 0) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- TAB: STOK PER SITE -->
        <div v-else-if="activeTab === 'stok'" class="p-3">
          <div v-if="loadingAlerts" class="text-center py-4"><div class="csm-spinner"></div></div>
          <div v-else-if="!stockAlerts.length" class="text-center text-muted py-4">Tidak ada data stok.</div>
          <div v-else>
            <div class="row g-3">
              <div v-for="s in stockAlerts" :key="s.warehouse_id" class="col-md-4">
                <div class="csm-card p-3" :style="s.stock_current < LOW_STOCK_THRESHOLD ? 'border-color:#f39c12;border-width:2px;' : ''">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="fw-semibold text-primary">{{ s.warehouse_name }}</div>
                      <small class="text-muted">Update: {{ s.last_updated ? $formatDate(s.last_updated) : 'Belum ada data' }}</small>
                    </div>
                    <i v-if="s.stock_current < LOW_STOCK_THRESHOLD" class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                    <i v-else class="bi bi-check-circle-fill text-success fs-5"></i>
                  </div>
                  <div class="mt-2">
                    <div class="fw-bold" :class="s.stock_current < LOW_STOCK_THRESHOLD ? 'text-warning' : 'text-success'" style="font-size:1.5rem;">
                      {{ $formatNumber(s.stock_current) }} <span style="font-size:.9rem;font-weight:400;">Liter</span>
                    </div>
                    <div class="progress mt-2" style="height:6px;">
                      <div class="progress-bar"
                        :class="s.stock_current < LOW_STOCK_THRESHOLD ? 'bg-warning' : 'bg-success'"
                        :style="{width: Math.min(s.stock_current / LOW_STOCK_THRESHOLD * 50, 100)+'%'}">
                      </div>
                    </div>
                    <small :class="s.stock_current < LOW_STOCK_THRESHOLD ? 'text-warning fw-semibold' : 'text-muted'">
                      {{ s.stock_current < LOW_STOCK_THRESHOLD ? '⚠ Stok menipis' : 'Stok aman' }}
                      · batas aman {{ $formatNumber(LOW_STOCK_THRESHOLD) }} L
                    </small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ===================== MODAL SOLAR MASUK ===================== -->
    <div class="modal fade" id="modalMasuk" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header" style="background:linear-gradient(135deg,#1a8a4a,#27ae60);color:#fff;">
            <h5 class="modal-title"><i class="bi bi-arrow-down-circle me-2"></i>Catat Solar Masuk</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Info stok saat ini -->
            <div class="alert alert-info py-2 mb-3 d-flex align-items-center gap-2">
              <i class="bi bi-info-circle"></i>
              <span>Stok saat ini: <strong>{{ $formatNumber(currentStock) }} L</strong>
                <span class="text-muted ms-1">({{ formMasuk.warehouse_id ? warehouses.find(w=>w.id==formMasuk.warehouse_id)?.name || '' : 'pilih site' }})</span>
              </span>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tanggal <span class="text-danger">*</span></label>
                <input v-model="formMasuk.log_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Site / Gudang <span class="text-danger">*</span></label>
                <select v-model="formMasuk.warehouse_id" class="form-select form-select-sm" @change="fetchCurrentStock(formMasuk.warehouse_id)">
                  <option value="">-- Pilih Site --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Jumlah Solar Masuk (Liter) <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <input v-model.number="formMasuk.stock_in" type="number" min="0" step="0.01" class="form-control" placeholder="0" />
                  <span class="input-group-text">Liter</span>
                </div>
                <small class="text-success mt-1 d-block" v-if="formMasuk.stock_in > 0">
                  Stok setelah masuk: <strong>{{ $formatNumber(currentStock + (formMasuk.stock_in||0)) }} L</strong>
                </small>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Supplier / Sumber</label>
                <input v-model="formMasuk.operator_name" class="form-control form-control-sm" placeholder="Nama supplier / sumber" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jam Terima</label>
                <input v-model="formMasuk.fill_time" type="time" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="formMasuk.notes" class="form-control form-control-sm" rows="2" placeholder="No. DO, kendaraan pengantar, dll."></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-success btn-sm" @click="saveMasuk" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>
              <i v-else class="bi bi-check-lg me-1"></i>Simpan Masuk
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== MODAL SOLAR KELUAR ===================== -->
    <div class="modal fade" id="modalKeluar" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header" style="background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;">
            <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>Catat Solar Keluar</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Info stok & validasi -->
            <div class="alert py-2 mb-3 d-flex align-items-center gap-2"
              :class="currentStock < (formKeluar.liter_out||0) ? 'alert-danger' : 'alert-info'">
              <i :class="currentStock < (formKeluar.liter_out||0) ? 'bi bi-exclamation-triangle-fill' : 'bi bi-info-circle'"></i>
              <span>Stok tersedia: <strong>{{ $formatNumber(currentStock) }} L</strong>
                <span v-if="formKeluar.liter_out > 0" class="ms-2">
                  → Sisa: <strong :class="currentStock - formKeluar.liter_out < 0 ? 'text-danger' : ''">
                    {{ $formatNumber(currentStock - (formKeluar.liter_out||0)) }} L
                  </strong>
                </span>
              </span>
            </div>
            <div v-if="currentStock < (formKeluar.liter_out||0)" class="alert alert-danger py-2 mb-3">
              <i class="bi bi-x-circle me-1"></i><strong>Stok tidak mencukupi.</strong> Kurangi jumlah keluar atau catat solar masuk terlebih dahulu.
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tanggal <span class="text-danger">*</span></label>
                <input v-model="formKeluar.log_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Site / Gudang <span class="text-danger">*</span></label>
                <select v-model="formKeluar.warehouse_id" class="form-select form-select-sm" @change="onWarehouseChangeKeluar">
                  <option value="">-- Pilih Site --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Kode Unit <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input
                    v-model="unitSearch"
                    class="form-control form-control-sm"
                    placeholder="Ketik kode unit..."
                    @input="onUnitSearchInput"
                    @focus="onUnitSearchFocus"
                    @blur="onUnitSearchBlur"
                    autocomplete="off"
                  />
                  <!-- Dropdown hasil pencarian -->
                  <div v-if="showUnitDropdown && (unitSearchResults.length || loadingUnitSearch)"
                    class="position-absolute w-100 bg-white border rounded shadow-sm"
                    style="z-index:1060; max-height:220px; overflow-y:auto; top:100%; left:0;">
                    <div v-if="loadingUnitSearch" class="px-3 py-2 text-muted small">
                      <span class="csm-spinner me-1"></span> Mencari...
                    </div>
                    <div v-else-if="!unitSearchResults.length" class="px-3 py-2 text-muted small">
                      Tidak ada unit ditemukan
                    </div>
                    <div
                      v-for="u in unitSearchResults" :key="u.id"
                      class="px-3 py-2 cursor-pointer unit-option"
                      @mousedown.prevent="selectUnit(u)"
                    >
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <span class="fw-semibold text-primary">{{ u.unit_code }}</span>
                          <span class="text-muted ms-2 small">{{ u.type_unit }}</span>
                          <span v-if="u.brand" class="text-muted small"> · {{ u.brand }}</span>
                        </div>
                        <div class="text-end">
                          <span class="badge" :class="u.status === 'active' ? 'bg-success' : u.status === 'maintenance' ? 'bg-warning text-dark' : 'bg-secondary'" style="font-size:0.65rem;">{{ u.status }}</span>
                          <div v-if="u.warehouse" class="text-muted" style="font-size:0.7rem;">{{ u.warehouse.name }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Badge unit terpilih -->
                  <div v-if="formKeluar.unit_code && !showUnitDropdown" class="mt-1">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.78rem;">
                      <i class="bi bi-truck me-1"></i>{{ formKeluar.unit_code }}
                      <span v-if="formKeluar.unit_type" class="ms-1 text-muted">· {{ formKeluar.unit_type }}</span>
                      <button type="button" class="btn-close ms-2" style="font-size:0.6rem;" @click="clearUnit"></button>
                    </span>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tipe Unit</label>
                <input v-model="formKeluar.unit_type" class="form-control form-control-sm" placeholder="ZX350" readonly style="background:#f8fafc;" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Divisi</label>
                <input v-model="formKeluar.division" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">HM/KM</label>
                <input v-model.number="formKeluar.hm_km" type="number" step="0.1" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jam Pengisian</label>
                <input v-model="formKeluar.fill_time" type="time" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Operator</label>
                <input v-model="formKeluar.operator_name" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Liter Keluar <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <input v-model.number="formKeluar.liter_out" type="number" min="0" step="0.01" class="form-control"
                    :class="currentStock < (formKeluar.liter_out||0) ? 'is-invalid' : ''" />
                  <span class="input-group-text">Liter</span>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="formKeluar.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-danger btn-sm" @click="saveKeluar"
              :disabled="saving || currentStock < (formKeluar.liter_out||0)">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>
              <i v-else class="bi bi-check-lg me-1"></i>Simpan Keluar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== MODAL EDIT ===================== -->
    <div class="modal fade" id="modalEdit" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Log BBM</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tanggal</label>
                <input v-model="formEdit.log_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Kode Unit</label>
                <input v-model="formEdit.unit_code" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Tipe Unit</label>
                <input v-model="formEdit.unit_type" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">HM/KM</label>
                <input v-model.number="formEdit.hm_km" type="number" step="0.1" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Stok Masuk (L)</label>
                <input v-model.number="formEdit.stock_in" type="number" min="0" step="0.01" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Liter Keluar</label>
                <input v-model.number="formEdit.liter_out" type="number" min="0" step="0.01" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Operator</label>
                <input v-model="formEdit.operator_name" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold small">Jam Isi</label>
                <input v-model="formEdit.fill_time" type="time" class="form-control form-control-sm" />
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold small">Catatan</label>
                <textarea v-model="formEdit.notes" class="form-control form-control-sm" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="saveEdit" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>Perbarui
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import Swal from 'sweetalert2'
import dayjs from 'dayjs'

const auth = useAuthStore()
const toast = useToast()
const can = (p) => auth.hasPermission(p)
const { listenFuel, stopFuel } = useRealtime()

const LOW_STOCK_THRESHOLD = 500 // Liter - batas alert stok menipis

// State
const logs = ref([])
const warehouses = ref([])
const loading = ref(false)
const loadingUnit = ref(false)
const loadingAlerts = ref(false)
const saving = ref(false)
const summary = ref(null)
const unitConsumption = ref([])
const stockAlerts = ref([])
const currentStock = ref(0)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const activeTab = ref('semua')

// Unit search state (untuk modal keluar)
const unitSearch = ref('')
const unitSearchResults = ref([])
const loadingUnitSearch = ref(false)
const showUnitDropdown = ref(false)
let unitSearchTimer = null

const filters = ref({
  warehouse_id: '',
  month: dayjs().format('YYYY-MM'),
  unit_code: '',
  type: ''
})

// Forms
const emptyMasuk = () => ({
  log_date: dayjs().format('YYYY-MM-DD'),
  warehouse_id: filters.value.warehouse_id || '',
  stock_in: null,
  liter_out: 0,
  fill_time: null,
  operator_name: '',
  notes: ''
})
const emptyKeluar = () => ({
  log_date: dayjs().format('YYYY-MM-DD'),
  warehouse_id: filters.value.warehouse_id || '',
  unit_id: null, unit_code: '', unit_type: '', division: '',
  hm_km: null, fill_time: '',
  liter_out: null, stock_in: 0,
  operator_name: '', notes: ''
})

const formMasuk = ref(emptyMasuk())
const formKeluar = ref(emptyKeluar())
const formEdit = ref({})

let modalMasukInst = null
let modalKeluarInst = null
let modalEditInst = null
let timer = null

// Alert stok menipis (filter hanya yang di bawah threshold)
const alertStok = computed(() =>
  stockAlerts.value.filter(s => s.stock_current < LOW_STOCK_THRESHOLD && s.stock_current >= 0)
)

onMounted(async () => {
  modalMasukInst  = new Modal(document.getElementById('modalMasuk'))
  modalKeluarInst = new Modal(document.getElementById('modalKeluar'))
  modalEditInst   = new Modal(document.getElementById('modalEdit'))

  const r = await axios.get('/warehouses')
  warehouses.value = r.data.data

  if (!auth.isSuperuser && !auth.isAdminHO && auth.userWarehouseId) {
    filters.value.warehouse_id = auth.userWarehouseId
  }

  load()
  loadStockAlerts()
  listenFuel(() => { load(); loadStockAlerts() })
})

onUnmounted(() => stopFuel())

// ─── Load data ───────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const params = { ...filters.value, page: meta.value.page }
    if (activeTab.value === 'masuk') params.type = 'in'
    if (activeTab.value === 'keluar') params.type = 'out'

    const r = await axios.get('/fuel-logs', { params })
    logs.value = r.data.data
    meta.value = r.data.meta
    // Inject stock_current (log terakhir tanpa filter bulan) ke summary.stock_end
    const s = r.data.summary || {}
    if (r.data.stock_current !== null && r.data.stock_current !== undefined) {
      s.stock_end = r.data.stock_current
    }
    summary.value = s
  } finally { loading.value = false }
}

async function loadUnitConsumption() {
  loadingUnit.value = true
  try {
    const r = await axios.get('/fuel-logs', {
      params: { ...filters.value, with_units: 1, per_page: 1 }
    })
    unitConsumption.value = r.data.unit_consumption || []
    summary.value = r.data.summary
  } finally { loadingUnit.value = false }
}

async function loadStockAlerts() {
  loadingAlerts.value = true
  try {
    const r = await axios.get('/fuel-logs', {
      params: { with_alerts: 1, per_page: 1 }
    })
    stockAlerts.value = r.data.stock_alerts || []
  } finally { loadingAlerts.value = false }
}

async function fetchCurrentStock(warehouseId) {
  if (!warehouseId) { currentStock.value = 0; return }
  try {
    // Tanpa filter bulan agar selalu dapat stok terkini
    const r = await axios.get('/fuel-logs', {
      params: { warehouse_id: warehouseId, per_page: 1 }
    })
    // Pakai stock_current (log terakhir tanpa filter bulan) bukan summary.stock_end
    currentStock.value = parseFloat(r.data.stock_current ?? r.data.summary?.stock_end ?? 0)
  } catch { currentStock.value = 0 }
}

function switchTab(tab) {
  activeTab.value = tab
  meta.value.page = 1
  if (tab === 'laporan') { loadUnitConsumption(); return }
  if (tab === 'stok') { loadStockAlerts(); return }
  load()
}

function resetAndLoad() { meta.value.page = 1; load() }
function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page = 1; load() }, 400) }
function changePage(p) { meta.value.page = p; load() }

// ─── Modals ───────────────────────────────────────────────────
function openModalMasuk() {
  formMasuk.value = emptyMasuk()
  fetchCurrentStock(formMasuk.value.warehouse_id)
  modalMasukInst.show()
}

function openModalKeluar() {
  formKeluar.value = emptyKeluar()
  unitSearch.value = ''
  unitSearchResults.value = []
  showUnitDropdown.value = false
  if (formKeluar.value.warehouse_id) {
    fetchCurrentStock(formKeluar.value.warehouse_id)
    loadUnitsByWarehouse(formKeluar.value.warehouse_id)
  }
  modalKeluarInst.show()
}

// ─── Unit Search Functions ────────────────────────────────────
async function onWarehouseChangeKeluar() {
  // Reset unit saat ganti site
  clearUnit()
  fetchCurrentStock(formKeluar.value.warehouse_id)
  if (formKeluar.value.warehouse_id) {
    await loadUnitsByWarehouse(formKeluar.value.warehouse_id)
  } else {
    unitSearchResults.value = []
    showUnitDropdown.value = false
  }
}

async function loadUnitsByWarehouse(warehouseId) {
  loadingUnitSearch.value = true
  showUnitDropdown.value = true
  try {
    const r = await axios.get('/units', { params: { warehouse_id: warehouseId } })
    unitSearchResults.value = r.data.data || []
  } finally { loadingUnitSearch.value = false }
}

function onUnitSearchInput() {
  clearTimeout(unitSearchTimer)
  showUnitDropdown.value = true
  if (!unitSearch.value.trim()) { unitSearchResults.value = []; return }
  loadingUnitSearch.value = true
  unitSearchTimer = setTimeout(async () => {
    try {
      const params = { search: unitSearch.value }
      if (formKeluar.value.warehouse_id) params.warehouse_id = formKeluar.value.warehouse_id
      const r = await axios.get('/units', { params })
      unitSearchResults.value = r.data.data || []
    } finally { loadingUnitSearch.value = false }
  }, 300)
}

function selectUnit(u) {
  formKeluar.value.unit_code = u.unit_code
  formKeluar.value.unit_type = u.type_unit || ''
  formKeluar.value.unit_id   = u.id
  if (u.hm_current) formKeluar.value.hm_km = parseFloat(u.hm_current)
  unitSearch.value = u.unit_code
  showUnitDropdown.value = false
  unitSearchResults.value = []
}

function clearUnit() {
  formKeluar.value.unit_code = ''
  formKeluar.value.unit_type = ''
  formKeluar.value.unit_id   = null
  formKeluar.value.hm_km     = null
  unitSearch.value = ''
  unitSearchResults.value = []
}

function onUnitSearchFocus() {
  // Jika belum ada hasil dan ada warehouse, auto-load unit site tersebut
  if (!unitSearchResults.value.length && formKeluar.value.warehouse_id) {
    loadUnitsByWarehouse(formKeluar.value.warehouse_id)
  } else {
    showUnitDropdown.value = true
  }
}

function onUnitSearchBlur() {
  setTimeout(() => { showUnitDropdown.value = false }, 150)
}

function openModalEdit(log) {
  formEdit.value = {
    id: log.id,
    log_date: log.log_date,
    unit_code: log.unit_code || '',
    unit_type: log.unit_type || '',
    hm_km: log.hm_km || null,
    fill_time: log.fill_time || '',
    liter_out: parseFloat(log.liter_out) || 0,
    stock_in: parseFloat(log.stock_in) || 0,
    operator_name: log.operator_name || '',
    notes: log.notes || ''
  }
  modalEditInst.show()
}

// ─── Save ────────────────────────────────────────────────────
async function saveMasuk() {
  if (!formMasuk.value.warehouse_id) { toast.error('Pilih site terlebih dahulu'); return }
  if (!formMasuk.value.stock_in || formMasuk.value.stock_in <= 0) { toast.error('Jumlah liter harus lebih dari 0'); return }

  saving.value = true
  try {
    const payload = {
      log_date:      formMasuk.value.log_date,
      warehouse_id:  formMasuk.value.warehouse_id,
      stock_in:      parseFloat(formMasuk.value.stock_in),
      liter_out:     0,
      fill_time:     formMasuk.value.fill_time || null,   // kirim null bukan ''
      operator_name: formMasuk.value.operator_name || null,
      notes:         formMasuk.value.notes || null,
    }
    await axios.post('/fuel-logs', payload)
    toast.success('Solar masuk berhasil dicatat')
    modalMasukInst.hide()
    load(); loadStockAlerts()
  } catch (e) {
    // Tampilkan detail error validasi jika ada
    const msg = e.response?.data?.message
      || (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(', ') : null)
      || 'Gagal menyimpan'
    toast.error(msg)
  } finally { saving.value = false }
}

async function saveKeluar() {
  if (!formKeluar.value.warehouse_id) { toast.error('Pilih site terlebih dahulu'); return }
  if (!formKeluar.value.liter_out || formKeluar.value.liter_out <= 0) { toast.error('Jumlah liter keluar harus lebih dari 0'); return }
  if (currentStock.value < formKeluar.value.liter_out) { toast.error('Stok tidak mencukupi'); return }

  saving.value = true
  try {
    const payload = {
      ...formKeluar.value,
      stock_in:  0,
      fill_time: formKeluar.value.fill_time || null,   // kirim null bukan ''
      hm_km:     formKeluar.value.hm_km || null,
    }
    await axios.post('/fuel-logs', payload)
    toast.success('Solar keluar berhasil dicatat')
    modalKeluarInst.hide()
    load(); loadStockAlerts()
  } catch (e) {
    const msg = e.response?.data?.message
      || (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(', ') : null)
      || 'Gagal menyimpan'
    toast.error(msg)
  } finally { saving.value = false }
}

async function saveEdit() {
  saving.value = true
  try {
    await axios.put(`/fuel-logs/${formEdit.value.id}`, formEdit.value)
    toast.success('Log BBM diperbarui')
    modalEditInst.hide()
    load()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal memperbarui')
  } finally { saving.value = false }
}

async function deleteLog(log) {
  const r = await Swal.fire({
    title: 'Hapus Log BBM?',
    text: `${dayjs(log.log_date).format('DD/MM/YYYY')} - ${log.unit_code || 'Solar Masuk'}`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e74c3c',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal'
  })
  if (!r.isConfirmed) return
  try {
    await axios.delete(`/fuel-logs/${log.id}`)
    toast.success('Log dihapus')
    load(); loadStockAlerts()
  } catch { toast.error('Gagal menghapus') }
}

// ─── Export Excel ─────────────────────────────────────────────
function exportExcel() {
  // Bangun query string
  const params = new URLSearchParams({
    warehouse_id: filters.value.warehouse_id || '',
    month: filters.value.month || '',
    unit_code: filters.value.unit_code || '',
    per_page: 9999
  })
  if (activeTab.value === 'masuk') params.set('type', 'in')
  if (activeTab.value === 'keluar') params.set('type', 'out')

  // Redirect ke endpoint export (jika ada) atau notifikasi
  toast.info('Fitur export sedang diproses...')
}
</script>

<style scoped>
.table-success-subtle { background-color: #f0fdf4 !important; }
.nav-tabs .nav-link { border: none; padding: 0.5rem 1rem; font-size: 0.85rem; color: #64748b; border-bottom: 2px solid transparent; }
.nav-tabs .nav-link.active { color: #1a3a5c; font-weight: 600; border-bottom-color: #1a3a5c; background: transparent; }
.nav-tabs .nav-link:hover:not(.active) { color: #2e86c1; background: #f8fafc; }
.unit-option:hover { background: #f0f7ff; cursor: pointer; }
.unit-option { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
</style>