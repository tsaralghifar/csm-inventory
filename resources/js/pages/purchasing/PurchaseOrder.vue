<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Purchase Order</h5>
        <small class="text-muted">Daftar pembelian barang dari vendor</small>
      </div>
      <button v-if="can('manage-po')" class="btn btn-primary btn-sm" @click="openCreateModal">
        <i class="bi bi-plus-lg me-1"></i>Buat PO Baru
      </button>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. PO..." @input="debouncedLoad" />
          </div>
          <div class="col-md-3">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="sent_to_vendor">Dikirim ke Vendor</option>
              <option value="completed">Selesai</option>
              <option value="cancelled">Dibatalkan</option>
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
                <th>No. PO</th>
                <th>No. MR / PM</th>
                <th>Vendor</th>
                <th>Gudang</th>
                <th>Item</th>
                <th>Total</th>
                <th>Status</th>
                <th>Dibuat Oleh</th>
                <th>Tanggal</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="10" class="text-center text-muted py-5">Belum ada Purchase Order</td>
              </tr>
              <tr v-for="po in list" :key="po.id">
                <td class="fw-semibold text-primary">{{ po.po_number }}</td>
                <td>
                  <div v-if="po.permintaan_materials?.length" class="d-flex flex-column gap-1">
                    <small v-for="p in po.permintaan_materials" :key="p.id"
                      class="badge bg-light text-dark border" style="font-size:0.7rem;">{{ p.nomor }}</small>
                  </div>
                  <small v-else class="text-muted">{{ po.material_request?.mr_number || '-' }}</small>
                </td>
                <td><small>{{ po.vendor_name || '-' }}</small></td>
                <td><small>{{ po.warehouse?.name }}</small></td>
                <td><span class="badge bg-secondary rounded-pill">{{ po.items_count }} item</span></td>
                <td><small class="fw-semibold">Rp {{ $formatNumber(po.grand_total || po.total_amount) }}</small></td>
                <td><span class="badge" :class="statusClass(po.status)">{{ statusLabel(po.status) }}</span></td>
                <td><small>{{ po.creator?.name }}</small></td>
                <td><small class="text-muted">{{ $formatDate(po.created_at) }}</small></td>
                <td class="text-center">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="Detail" @click="openDetail(po)">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-danger" title="Print PDF" @click="printPODirect(po)">
                      <i class="bi bi-printer"></i>
                    </button>
                    <button v-if="po.status === 'draft' && can('manage-po')"
                      class="btn btn-outline-info" title="Kirim ke Vendor" @click="doSend(po)">
                      <i class="bi bi-send"></i>
                    </button>
                    <button v-if="po.status === 'sent_to_vendor' && !po.surat_jalan_count && can('create-sj')"
                      class="btn btn-outline-success" title="Buat Surat Jalan" @click="openSJModal(po)">
                      <i class="bi bi-truck"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top" v-if="meta.total > 0">
          <small class="text-muted">Total {{ meta.total }} purchase order</small>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page <= 1" @click="changePage(meta.page-1)">‹ Prev</button>
            <button class="btn btn-xs btn-outline-secondary" :disabled="meta.page >= meta.last_page" @click="changePage(meta.page+1)">Next ›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Detail PO -->
    <div class="modal fade" id="modalDetailPO" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedPO">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>{{ selectedPO.po_number }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                    <tr><td class="text-muted w-40">No. PO</td><td class="fw-semibold">{{ selectedPO.po_number }}</td></tr>
                    <tr>
                      <td class="text-muted">No. MR / PM</td>
                      <td>
                        <div v-if="selectedPO.permintaan_materials?.length" class="d-flex flex-wrap gap-1">
                          <span v-for="p in selectedPO.permintaan_materials" :key="p.id"
                            class="badge bg-light text-dark border small">{{ p.nomor }}</span>
                        </div>
                        <span v-else>{{ selectedPO.material_request?.mr_number || '-' }}</span>
                      </td>
                    </tr>
                    <tr><td class="text-muted">Vendor</td><td>{{ selectedPO.vendor_name || '-' }}</td></tr>
                    <tr><td class="text-muted">Kontak</td><td>{{ selectedPO.vendor_contact || '-' }}</td></tr>
                  </tbody>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless small mb-0">
                  <tbody>
                    <tr><td class="text-muted w-40">Gudang</td><td>{{ selectedPO.warehouse?.name }}</td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="badge" :class="statusClass(selectedPO.status)">{{ statusLabel(selectedPO.status) }}</span></td></tr>
                    <tr><td class="text-muted">Est. Tiba</td><td>{{ selectedPO.expected_date ? $formatDate(selectedPO.expected_date) : '-' }}</td></tr>
                    <tr><td class="text-muted">Total</td><td class="fw-bold text-primary">Rp {{ $formatNumber(selectedPO.grand_total || selectedPO.total_amount) }}</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm csm-table mb-0">
                <thead><tr><th>#</th><th>Part Number</th><th>Nama Barang</th><th>Kode Unit</th><th>Tipe Unit</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga Satuan</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                  <tr v-for="(item, idx) in selectedPO.items" :key="item.id">
                    <td class="text-muted">{{ idx+1 }}</td>
                    <td>
                      <code v-if="item.item?.part_number" class="small text-primary fw-semibold">{{ item.item.part_number }}</code>
                      <span v-else class="text-muted small">-</span>
                    </td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td><code class="small text-secondary">{{ item.kode_unit || '-' }}</code></td>
                    <td><small>{{ item.tipe_unit || '-' }}</small></td>
                    <td class="text-end">{{ item.qty }}</td>
                    <td>{{ item.satuan }}</td>
                    <td class="text-end">Rp {{ $formatNumber(item.harga_satuan) }}</td>
                    <td class="text-end fw-semibold">Rp {{ $formatNumber(item.total_harga) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="8" class="text-end text-muted small">Subtotal</td>
                    <td class="text-end small">Rp {{ $formatNumber(selectedPO.items?.reduce((s,i) => s + parseFloat(i.total_harga||0), 0)) }}</td>
                  </tr>
                  <tr v-if="parseFloat(selectedPO.diskon_persen) > 0">
                    <td colspan="8" class="text-end text-muted small">
                      Diskon {{ selectedPO.diskon_persen }}%
                    </td>
                    <td class="text-end small text-danger fw-semibold">- Rp {{ $formatNumber(selectedPO.diskon_amount) }}</td>
                  </tr>
                  <tr v-if="parseFloat(selectedPO.ppn_percent) > 0">
                    <td colspan="8" class="text-end text-muted small">
                      PPN {{ selectedPO.ppn_percent }}%
                    </td>
                    <td class="text-end small text-warning fw-semibold">Rp {{ $formatNumber(selectedPO.ppn_amount) }}</td>
                  </tr>
                  <tr v-else>
                    <td colspan="8" class="text-end text-muted small">PPN</td>
                    <td class="text-end small text-muted">Tidak kena PPN</td>
                  </tr>
                  <tr>
                    <td colspan="8" class="text-end fw-bold">Grand Total</td>
                    <td class="text-end fw-bold text-primary">Rp {{ $formatNumber(selectedPO.grand_total || selectedPO.total_amount) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button type="button" class="btn btn-outline-success btn-sm" @click="exportExcel(selectedPO)">
              <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" @click="printPDF(selectedPO)">
              <i class="bi bi-printer me-1"></i>Print / PDF
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Buat Surat Jalan dari PO -->
    <div class="modal fade" id="modalSJFromPO" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" v-if="selectedPO">
          <div class="modal-header">
            <h6 class="modal-title text-success"><i class="bi bi-truck me-2"></i>Buat Surat Jalan dari {{ selectedPO.po_number }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Vendor <span class="text-danger">*</span></label>
                <input v-model="sjForm.vendor_name" type="text" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Tanggal Terima <span class="text-danger">*</span></label>
                <input v-model="sjForm.received_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Nama Driver</label>
                <input v-model="sjForm.driver_name" type="text" class="form-control form-control-sm" placeholder="Nama pengemudi..." />
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">No. Kendaraan</label>
                <input v-model="sjForm.vehicle_plate" type="text" class="form-control form-control-sm" placeholder="KT 1234 AB" />
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Catatan</label>
                <input v-model="sjForm.notes" type="text" class="form-control form-control-sm" />
              </div>
            </div>
            <label class="form-label small fw-semibold">Qty Diterima per Barang</label>
            <div class="table-responsive">
              <table class="table table-sm csm-table mb-0">
                <thead><tr><th>Part Number</th><th>Nama Barang</th><th class="text-end">Qty PO</th><th style="width:130px">Qty Diterima</th><th>Satuan</th></tr></thead>
                <tbody>
                  <tr v-for="(item, idx) in sjForm.items" :key="idx">
                    <td>
                      <code v-if="item.item?.part_number" class="small text-primary fw-semibold">{{ item.item.part_number }}</code>
                      <span v-else class="text-muted small">-</span>
                    </td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td class="text-end text-muted">{{ item.qty_ordered }}</td>
                    <td><input v-model="item.qty_received" type="number" class="form-control form-control-sm" min="0" :max="item.qty_ordered" step="0.01" /></td>
                    <td>{{ item.satuan }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm" @click="saveSJ" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-truck me-1"></i>Buat Surat Jalan
            </button>
          </div>
        </div>
      </div>
    </div>

  <!-- ===== Modal Buat PO (Multi-PM) ===== -->
  <div class="modal fade" id="modalBuatPO" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header" style="background:#1a3a5c;">
          <h6 class="modal-title text-white">
            <i class="bi bi-file-earmark-plus me-2"></i>Buat Purchase Order Baru
          </h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <!-- STEP 1: Pilih PM -->
          <div v-if="createStep === 1">
            <div class="alert alert-primary small py-2 mb-3">
              <i class="bi bi-info-circle me-1"></i>
              <strong>Skenario 4 — Banyak PM → Banyak PO:</strong>
              Pilih satu atau lebih PM yang sudah disetujui untuk digabung ke PO ini.
            </div>

            <!-- Filter PM -->
            <div class="row g-2 mb-3">
              <div class="col-md-5">
                <input v-model="pmSearch" class="form-control form-control-sm"
                  placeholder="🔍 Cari nomor PM..." @input="debouncedSearchPM" />
              </div>
              <div class="col-md-4">
                <select v-model="pmWarehouseFilter" class="form-select form-select-sm" @change="searchPM">
                  <option value="">Semua Gudang</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
            </div>

            <!-- Tabel PM yang bisa dipilih -->
            <div v-if="pmLoading" class="text-center py-3"><div class="csm-spinner"></div></div>
            <div v-else class="table-responsive" style="max-height:320px;overflow-y:auto;">
              <table class="table table-sm csm-table mb-0">
                <thead class="sticky-top">
                  <tr>
                    <th style="width:36px">
                      <input type="checkbox" class="form-check-input"
                        :checked="availablePMs.length && availablePMs.every(p => selectedPMIds.includes(p.id))"
                        @change="e => e.target.checked ? availablePMs.forEach(p => addPM(p)) : selectedPMIds = []" />
                    </th>
                    <th>No. PM</th>
                    <th>Tipe</th>
                    <th>Gudang</th>
                    <th>Diajukan Oleh</th>
                    <th class="text-center">Jumlah Item</th>
                    <th>Tgl. Disetujui</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!availablePMs.length">
                    <td colspan="7" class="text-center text-muted py-4">
                      Tidak ada PM yang tersedia (status: approved / pending_purchasing / partial_ordered)
                    </td>
                  </tr>
                  <tr v-for="pm in availablePMs" :key="pm.id"
                    :class="selectedPMIds.includes(pm.id) ? 'table-primary' : ''"
                    style="cursor:pointer" @click="togglePM(pm)">
                    <td><input type="checkbox" class="form-check-input" :checked="selectedPMIds.includes(pm.id)" @click.stop="togglePM(pm)" /></td>
                    <td class="fw-semibold text-primary">{{ pm.nomor }}</td>
                    <td>
                      <span class="badge" :class="pm.type==='part'?'bg-primary':'bg-info text-dark'">
                        {{ pm.type==='part'?'MR Part':'MR Office' }}
                      </span>
                    </td>
                    <td><small>{{ pm.warehouse?.name }}</small></td>
                    <td><small>{{ pm.requester?.name }}</small></td>
                    <td class="text-center">
                      <span class="badge bg-secondary rounded-pill">{{ pm.items_count }}</span>
                    </td>
                    <td>
                      <small class="text-muted">{{ $formatDate(pm.ho_approved_at || pm.updated_at) }}</small>
                      <span v-if="pm.status==='partial_ordered'" class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">Partial</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- PM yang sudah dipilih -->
            <div v-if="selectedPMIds.length" class="mt-3 p-3 border rounded" style="background:#f0f5ff;">
              <div class="small fw-semibold mb-2">
                <i class="bi bi-check-circle-fill text-primary me-1"></i>
                {{ selectedPMIds.length }} PM dipilih:
              </div>
              <div class="d-flex flex-wrap gap-2">
                <span v-for="pm in selectedPMs" :key="pm.id"
                  class="badge bg-primary d-flex align-items-center gap-1" style="font-size:0.75rem;padding:5px 10px;">
                  {{ pm.nomor }}
                  <i class="bi bi-x" style="cursor:pointer" @click.stop="removePM(pm.id)"></i>
                </span>
              </div>
            </div>
          </div>

          <!-- STEP 2: Form PO & pilih item -->
          <div v-if="createStep === 2">
            <!-- Info PM yang dipilih -->
            <div class="d-flex flex-wrap gap-2 mb-3">
              <span class="small fw-semibold text-muted me-1">PM Sumber:</span>
              <span v-for="pm in selectedPMs" :key="pm.id" class="badge bg-primary">{{ pm.nomor }}</span>
            </div>

            <!-- Form vendor & info -->
            <div class="row g-2 mb-3">
              <div class="col-md-5">
                <label class="form-label small fw-semibold">Vendor / Supplier <span class="text-danger">*</span></label>
                <input v-model="createForm.vendor_name" type="text" class="form-control form-control-sm" placeholder="Nama vendor/supplier" />
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Kontak Vendor</label>
                <input v-model="createForm.vendor_contact" type="text" class="form-control form-control-sm" />
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-semibold">Estimasi Tiba</label>
                <input v-model="createForm.expected_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-12">
                <label class="form-label small fw-semibold">Catatan</label>
                <input v-model="createForm.notes" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
              </div>
            </div>

            <!-- Tabel item dari semua PM -->
            <!-- Error multi-gudang -->
            <div v-if="hasMultipleWarehouses" class="alert alert-danger py-2 px-3 mb-2 small">
              <div class="fw-semibold mb-1"><i class="bi bi-x-circle-fill me-1"></i>Tidak bisa membuat PO</div>
              PM yang dipilih berasal dari gudang yang berbeda:
              <ul class="mb-1 mt-1">
                <li v-for="wg in warehouseGroups" :key="wg.warehouseId">
                  <strong>{{ wg.warehouseName }}</strong>
                  <span class="text-muted ms-1">({{ wg.pmNomors.join(', ') }})</span>
                </li>
              </ul>
              Satu PO hanya boleh untuk <strong>satu gudang</strong>. Kembali ke Step 1 dan pilih PM dari gudang yang sama.
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label small fw-semibold mb-0">
                Pilih Item <span class="text-muted">(dari {{ selectedPMs.length }} PM)</span>
              </label>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-xs btn-outline-primary" @click="createForm.items.forEach(i=>i.selected=true)">Pilih Semua</button>
                <button type="button" class="btn btn-xs btn-outline-secondary" @click="createForm.items.forEach(i=>i.selected=false)">Hapus Semua</button>
              </div>
            </div>

            <div class="table-responsive" style="max-height:340px;overflow-y:auto;">
              <table class="table table-sm csm-table mb-0">
                <thead class="sticky-top">
                  <tr>
                    <th style="width:36px" class="text-center">
                      <input type="checkbox" class="form-check-input"
                        :checked="selectableItems.length > 0 && selectableItems.every(i=>i.selected)"
                        @change="e => selectableItems.forEach(i => i.selected = e.target.checked)" />
                    </th>
                    <th style="width:90px">No. PM</th>
                    <th style="width:90px">Part Number</th>
                    <th>Nama Barang</th>
                    <th class="text-center" style="width:70px">Qty PM</th>
                    <th class="text-center" style="width:110px">Qty PO Ini</th>
                    <th style="width:60px">Satuan</th>
                    <th class="text-end" style="width:140px">Harga Satuan (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Group by PM -->
                  <template v-for="pm in selectedPMs" :key="pm.id">
                    <tr :style="{ background: getWarehouseColor(pm.warehouse?.id) + '18' }">
                      <td colspan="8" class="py-1">
                        <span class="badge bg-primary me-2">{{ pm.nomor }}</span>
                        <span class="badge me-1" :style="{ background: getWarehouseColor(pm.warehouse?.id), fontSize: '0.65rem' }">
                          <i class="bi bi-building me-1"></i>{{ pm.warehouse?.name }}
                        </span>
                        <span v-if="pm.status==='partial_ordered'" class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">Sebagian sudah di-PO</span>
                      </td>
                    </tr>
                    <tr v-for="item in createForm.items.filter(i=>i.pm_id===pm.id)" :key="item._key"
                      :class="item.selected ? '' : 'table-light text-muted'">
                      <td class="text-center">
                        <input type="checkbox" class="form-check-input" v-model="item.selected" />
                      </td>
                      <td><small class="text-muted">{{ pm.nomor }}</small></td>
                      <td><code v-if="item.part_number" class="small text-primary fw-semibold">{{ item.part_number }}</code><span v-else class="text-muted small">-</span></td>
                      <td class="fw-semibold small">{{ item.nama_barang }}</td>
                      <td class="text-center small text-muted">{{ item.qty_pm }}</td>
                      <td>
                        <input v-model="item.qty" type="number"
                          class="form-control form-control-sm text-center"
                          min="0.01" :max="item.qty_pm - item.qty_already_ordered" step="0.01" />
                      </td>
                      <td class="small">{{ item.satuan }}</td>
                      <td>
                        <input v-model="item.harga_satuan" type="number"
                          class="form-control form-control-sm text-end"
                          :disabled="!item.selected"
                          :class="item.selected ? '' : 'bg-light'"
                          min="0" step="1000" placeholder="0" />
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>

            <!-- Ringkasan Total -->
            <div class="border rounded p-3 mt-3" style="background:#f8f9fa;">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold">
                  Subtotal (<span class="text-primary">{{ createForm.items.filter(i=>i.selected).length }}</span> item dipilih)
                </span>
                <span class="small">Rp {{ $formatNumber(createSubtotal) }}</span>
              </div>
              <!-- Toggle Diskon -->
              <div class="d-flex align-items-center gap-3 mb-2">
                <span class="small fw-semibold text-danger"><i class="bi bi-tag me-1"></i>Diskon</span>
                <div class="d-flex align-items-center gap-2 flex-grow-1">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="toggleDiskonCreate"
                      :checked="createForm.diskon_persen > 0"
                      @change="createForm.diskon_persen = $event.target.checked ? 10 : 0" />
                    <label class="form-check-label small" for="toggleDiskonCreate">
                      {{ createForm.diskon_persen > 0 ? 'Ada Diskon' : 'Tidak ada diskon' }}
                    </label>
                  </div>
                  <div v-if="createForm.diskon_persen > 0" class="input-group input-group-sm" style="max-width:110px;">
                    <input v-model="createForm.diskon_persen" type="number" class="form-control form-control-sm" min="0" max="100" step="0.5" />
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <span v-if="createForm.diskon_persen > 0" class="small fw-semibold text-danger">- Rp {{ $formatNumber(createDiskonAmt) }}</span>
                <span v-else class="small text-muted">Rp 0</span>
              </div>
              <!-- Toggle PPN -->
              <div class="d-flex align-items-center gap-3 mb-2">
                <span class="small fw-semibold"><i class="bi bi-percent me-1"></i>PPN</span>
                <div class="d-flex align-items-center gap-2 flex-grow-1">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="togglePPNCreate"
                      :checked="createForm.ppn_percent > 0"
                      @change="createForm.ppn_percent = $event.target.checked ? 11 : 0" />
                    <label class="form-check-label small" for="togglePPNCreate">
                      {{ createForm.ppn_percent > 0 ? 'Kena PPN' : 'Tidak kena PPN' }}
                    </label>
                  </div>
                  <div v-if="createForm.ppn_percent > 0" class="input-group input-group-sm" style="max-width:110px;">
                    <input v-model="createForm.ppn_percent" type="number" class="form-control form-control-sm" min="0" max="100" />
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <span v-if="createForm.ppn_percent > 0" class="small fw-semibold text-success">+ Rp {{ $formatNumber(createPPN) }}</span>
                <span v-else class="small text-muted">Rp 0</span>
              </div>
              <div class="d-flex align-items-center justify-content-between border-top pt-2">
                <span class="fw-bold">Grand Total</span>
                <span class="text-primary fw-bold fs-6">Rp {{ $formatNumber(createTotal) }}</span>
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
          <!-- Step 1 -->
          <template v-if="createStep === 1">
            <button class="btn btn-primary btn-sm" @click="goToStep2" :disabled="!selectedPMIds.length">
              Lanjut Pilih Item <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </template>
          <!-- Step 2 -->
          <template v-if="createStep === 2">
            <button class="btn btn-outline-secondary btn-sm" @click="createStep = 1">
              <i class="bi bi-arrow-left me-1"></i>Kembali Pilih PM
            </button>
            <button class="btn btn-primary btn-sm" @click="saveCreatePO" :disabled="saving || hasMultipleWarehouses">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-file-earmark-check me-1"></i> Buat Purchase Order
            </button>
          </template>
        </div>
      </div>
    </div>
  </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { exportPOExcel } from '@/utils/excelExport'

const toast = useToast()
const auth = useAuthStore()
const { listenPO, stopPO } = useRealtime()
const can = (p) => auth.hasPermission(p)

const list = ref([])
const loading = ref(false)
const saving = ref(false)
const meta = ref({ total: 0, page: 1, last_page: 1 })
const filters = ref({ search: '', status: '', date_from: '', date_to: '' })
const selectedPO = ref(null)
const sjForm = ref({ vendor_name: '', received_date: '', driver_name: '', vehicle_plate: '', notes: '', items: [] })
let timer = null

// ── Buat PO multi-PM ────────────────────────────────────
const warehouses     = ref([])
const availablePMs   = ref([])
const pmLoading      = ref(false)
const pmSearch       = ref('')
const pmWarehouseFilter = ref('')
const selectedPMIds  = ref([])
const selectedPMs    = ref([])
const createStep     = ref(1)
const createForm     = ref({ vendor_name:'', vendor_contact:'', warehouse_id:'', expected_date:'', notes:'', diskon_persen:0, ppn_percent:0, items:[] })
let pmTimer = null

const createSubtotal    = computed(() =>
  createForm.value.items.filter(i=>i.selected)
    .reduce((sum,i) => sum + (parseFloat(i.harga_satuan)||0)*(parseFloat(i.qty)||0), 0)
)
// ── Multi-gudang: group item per gudang → buat beberapa PO ──────────────────
const WAREHOUSE_COLORS = ['#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1','#20c997']

function getWarehouseColor(warehouseId) {
  const ids = [...new Set(selectedPMs.value.map(p => p.warehouse?.id).filter(Boolean))]
  const idx = ids.indexOf(warehouseId)
  return WAREHOUSE_COLORS[idx % WAREHOUSE_COLORS.length] || '#6c757d'
}

const warehouseGroups = computed(() => {
  const groups = {}
  for (const pm of selectedPMs.value) {
    const wId   = pm.warehouse?.id
    const wName = pm.warehouse?.name || 'Gudang tidak diketahui'
    if (!groups[wId]) {
      groups[wId] = { warehouseId: wId, warehouseName: wName, color: getWarehouseColor(wId), items: [], pmNomors: [] }
    }
    groups[wId].pmNomors.push(pm.nomor)
    const pmItems = createForm.value.items.filter(i => i.pm_id === pm.id)
    groups[wId].items.push(...pmItems)
  }
  return Object.values(groups)
})

const uniqueWarehouseCount = computed(() => warehouseGroups.value.length)
const hasMultipleWarehouses = computed(() => uniqueWarehouseCount.value > 1)

// Tidak ada locking — semua item bisa dipilih, dikelompokkan saat submit
function isItemLocked() { return false }
function onItemSelect() {}
const selectableItems = computed(() => createForm.value.items)

const activeWarehouseId = computed(() => null) // tidak dipakai lagi

const createDiskonAmt   = computed(() => Math.round(createSubtotal.value * (parseFloat(createForm.value.diskon_persen)||0) / 100))
const createAfterDiskon = computed(() => createSubtotal.value - createDiskonAmt.value)
const createPPN         = computed(() => Math.round(createAfterDiskon.value * (parseFloat(createForm.value.ppn_percent)||0) / 100))
const createTotal       = computed(() => createAfterDiskon.value + createPPN.value)

const statusLabel = (s) => ({ draft: 'Draft', sent_to_vendor: 'Dikirim ke Vendor', completed: 'Selesai', cancelled: 'Dibatalkan' }[s] || s)
const statusClass = (s) => ({ draft: 'bg-secondary', sent_to_vendor: 'bg-info text-dark', completed: 'bg-success', cancelled: 'bg-danger' }[s] || 'bg-secondary')

onMounted(() => { loadData(); listenPO(() => loadData()) })
onUnmounted(() => stopPO())

async function loadData() {
  loading.value = true
  try {
    const res = await axios.get('/purchase-orders', { params: { ...filters.value, page: meta.value.page, per_page: 15 } })
    list.value = res.data.data
    meta.value = res.data.meta
  } finally {
    loading.value = false
    window.clearModalBackdrop()
  }
}

function debouncedLoad() { clearTimeout(timer); timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400) }
function changePage(p) { meta.value.page = p; loadData() }
function resetFilters() { filters.value = { search: '', status: '', date_from: '', date_to: '' }; meta.value.page = 1; loadData() }

async function openDetail(po) {
  try {
    const res = await axios.get(`/purchase-orders/${po.id}`)
    selectedPO.value = res.data.data
    new Modal('#modalDetailPO').show()
  } catch { toast.error('Gagal memuat detail PO') }
}

async function doSend(po) {
  if (!confirm(`Kirim PO ${po.po_number} ke vendor?`)) return
  try {
    await axios.post(`/purchase-orders/${po.id}/send`)
    toast.success('PO dikirim ke vendor')
    loadData()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') }
}

async function openSJModal(po) {
  try {
    const res = await axios.get(`/purchase-orders/${po.id}`)
    selectedPO.value = res.data.data
    sjForm.value = {
      vendor_name: po.vendor_name || '',
      received_date: new Date().toISOString().split('T')[0],
      driver_name: '',
      vehicle_plate: '',
      notes: '',
      items: res.data.data.items.map(i => ({
        purchase_order_item_id: i.id,
        item_id: i.item_id ?? null,
        item: i.item ?? null,
        nama_barang: i.nama_barang,
        kode_unit: i.kode_unit,
        tipe_unit: i.tipe_unit,
        qty_ordered: i.qty,
        qty_received: i.qty,
        satuan: i.satuan,
        harga_satuan: i.harga_satuan,
        keterangan: i.keterangan,
      }))
    }
    new Modal('#modalSJFromPO').show()
  } catch { toast.error('Gagal memuat data PO') }
}

async function saveSJ() {
  if (!sjForm.value.received_date) return toast.error('Isi tanggal penerimaan')
  saving.value = true
  try {
    await axios.post('/surat-jalan', {
      purchase_order_id: selectedPO.value.id,
      material_request_id: selectedPO.value.material_request_id,
      permintaan_material_id: selectedPO.value.permintaan_material_id,
      warehouse_id: selectedPO.value.warehouse_id,
      vendor_name: sjForm.value.vendor_name,
      driver_name: sjForm.value.driver_name,
      vehicle_plate: sjForm.value.vehicle_plate,
      received_date: sjForm.value.received_date,
      notes: sjForm.value.notes,
      items: sjForm.value.items.map(i => ({
        purchase_order_item_id: i.purchase_order_item_id,
        item_id: i.item_id ?? null,
        qty_received: i.qty_received,
        masuk_stok: i.masuk_stok ?? true,
        keterangan: i.keterangan ?? null,
      })),
    })
    toast.success('Surat Jalan berhasil dibuat')
    Modal.getInstance('#modalSJFromPO')?.hide()
    loadData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal membuat Surat Jalan')
  } finally { saving.value = false }
}

// ── Buat PO multi-PM functions ──────────────────────────
async function openCreateModal() {
  createStep.value = 1
  selectedPMIds.value = []
  selectedPMs.value = []
  pmSearch.value = ''
  pmWarehouseFilter.value = ''
  createForm.value = { vendor_name:'', vendor_contact:'', warehouse_id:'', expected_date:'', notes:'', diskon_persen:0, ppn_percent:0, items:[] }
  await Promise.all([loadWarehouses(), searchPM()])
  new Modal('#modalBuatPO').show()
}

async function loadWarehouses() {
  if (warehouses.value.length) return
  try {
    const res = await axios.get('/warehouses')
    warehouses.value = res.data.data
  } catch {}
}

async function searchPM() {
  pmLoading.value = true
  try {
    const res = await axios.get('/permintaan-material', {
      params: {
        status: 'approved,manager_approved,partial_ordered,pending_purchasing',
        search: pmSearch.value || undefined,
        warehouse_id: pmWarehouseFilter.value || undefined,
        per_page: 50,
      }
    })
    availablePMs.value = res.data.data
  } catch {
    availablePMs.value = []
  } finally { pmLoading.value = false }
}

function debouncedSearchPM() {
  clearTimeout(pmTimer)
  pmTimer = setTimeout(searchPM, 350)
}

function togglePM(pm) {
  if (selectedPMIds.value.includes(pm.id)) {
    removePM(pm.id)
  } else {
    addPM(pm)
  }
}

function addPM(pm) {
  if (!selectedPMIds.value.includes(pm.id)) {
    selectedPMIds.value.push(pm.id)
    selectedPMs.value.push(pm)
  }
}

function removePM(id) {
  selectedPMIds.value = selectedPMIds.value.filter(x => x !== id)
  selectedPMs.value   = selectedPMs.value.filter(x => x.id !== id)
}

async function goToStep2() {
  if (!selectedPMIds.value.length) return
  pmLoading.value = true
  try {
    // Load detail tiap PM untuk dapat items + qty sudah di-PO
    const details = await Promise.all(
      selectedPMIds.value.map(id => axios.get(`/permintaan-material/${id}`).then(r => r.data.data))
    )

    // Update selectedPMs dengan data fresh
    selectedPMs.value = details

    const allItems = []
    for (const pm of details) {
      const allPOs = pm.purchase_orders || []
      for (const pmItem of (pm.items || [])) {
        const qtyOrdered = allPOs.reduce((sum, po) =>
          sum + (po.items||[]).filter(poi => poi.permintaan_material_item_id === pmItem.id)
                              .reduce((s, poi) => s + parseFloat(poi.qty||0), 0), 0)
        const qtyPm = parseFloat(pmItem.qty||0)
        const remaining = Math.max(0, qtyPm - qtyOrdered)
        if (remaining > 0) {
          allItems.push({
            _key: `${pm.id}_${pmItem.id}`,
            pm_id: pm.id,
            selected: true,
            permintaan_material_item_id: pmItem.id,
            item_id: pmItem.item_id ?? null,
            part_number: pmItem.part_number || pmItem.item?.part_number || null,
            nama_barang: pmItem.nama_barang,
            kode_unit: pmItem.kode_unit,
            tipe_unit: pmItem.tipe_unit,
            qty_pm: qtyPm,
            qty_already_ordered: qtyOrdered,
            qty: remaining,
            satuan: pmItem.satuan,
            harga_satuan: 0,
            keterangan: pmItem.keterangan,
          })
        }
      }
    }

    createForm.value.items = allItems

    createStep.value = 2
  } catch (e) {
    toast.error('Gagal memuat detail PM')
  } finally { pmLoading.value = false }
}

async function saveCreatePO() {
  if (hasMultipleWarehouses.value) return toast.error('PM berasal dari gudang berbeda, tidak bisa membuat PO')
  if (!createForm.value.vendor_name) return toast.error('Isi nama vendor/supplier')
  const selectedItems = createForm.value.items.filter(i => i.selected)
  if (!selectedItems.length) return toast.error('Pilih minimal satu item')

  // Ambil warehouse_id dari PM pertama
  const warehouseId = selectedPMs.value[0]?.warehouse?.id
  if (!warehouseId) return toast.error('Gudang PM tidak ditemukan')

  saving.value = true
  try {
    await axios.post('/purchase-orders', {
      permintaan_material_ids: selectedPMIds.value,
      warehouse_id:   warehouseId,
      vendor_name:    createForm.value.vendor_name,
      vendor_contact: createForm.value.vendor_contact,
      expected_date:  createForm.value.expected_date,
      notes:          createForm.value.notes,
      diskon_persen:  createForm.value.diskon_persen,
      ppn_percent:    createForm.value.ppn_percent,
      items: selectedItems.map(i => ({
        item_id:                      i.item_id,
        permintaan_material_item_id:  i.permintaan_material_item_id,
        qty_pm:       i.qty_pm,
        part_number:  i.part_number,
        nama_barang:  i.nama_barang,
        kode_unit:    i.kode_unit,
        tipe_unit:    i.tipe_unit,
        qty:          i.qty,
        satuan:       i.satuan,
        harga_satuan: i.harga_satuan,
        keterangan:   i.keterangan,
      }))
    })
    toast.success('Purchase Order berhasil dibuat')
    Modal.getInstance('#modalBuatPO')?.hide()
    loadData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal membuat Purchase Order')
  } finally { saving.value = false }
}

// ── Helpers format ───────────────────────────────────────
function fmtRp(val) {
  if (!val && val !== 0) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID')
}
function fmtDate(val) {
  if (!val) return '-'
  return new Date(val).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

// ── Generate HTML template PO ────────────────────────────
function buildPOHtml(po) {
  const subtotal   = (po.items || []).reduce((s, i) => s + parseFloat(i.total_harga || 0), 0)
  const diskonPct  = parseFloat(po.diskon_persen) || 0
  const diskonAmt  = parseFloat(po.diskon_amount) || 0
  const ppnAmt     = po.ppn_amount || 0
  const grandTotal = po.grand_total || subtotal
  const mrpmList   = po.permintaan_materials?.length
    ? po.permintaan_materials.map(p => p.nomor).join(', ')
    : (po.material_request?.mr_number || po.permintaan_material?.nomor || '-')
  const mrpm = mrpmList
  const estTiba    = po.expected_date ? fmtDate(po.expected_date) : '-'

  const rows = (po.items || []).map((item, i) => `
    <tr>
      <td class="center">${i + 1}</td>
      <td class="center mono">${item.item?.part_number || '-'}</td>
      <td>${item.nama_barang || '-'}</td>
      <td class="center">${item.kode_unit || '-'}</td>
      <td class="center">${item.tipe_unit || '-'}</td>
      <td class="center">${item.qty}</td>
      <td class="center">${item.satuan}</td>
      <td class="right">${fmtRp(item.harga_satuan)}</td>
      <td class="right bold">${fmtRp(item.total_harga)}</td>
    </tr>`).join('')

  const statusMap = { draft: 'DRAFT', sent_to_vendor: 'DIKIRIM KE VENDOR', completed: 'SELESAI', cancelled: 'DIBATALKAN' }
  const statusColor = { draft: '#6c757d', sent_to_vendor: '#0dcaf0', completed: '#198754', cancelled: '#dc3545' }
  const statusText  = statusMap[po.status] || po.status?.toUpperCase() || ''
  const statusClr   = statusColor[po.status] || '#6c757d'

  return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<title>Purchase Order — ${po.po_number}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 11px; color: #1a1a2e; background: #fff; }
  .page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm 16mm 14mm; }

  /* ── Header ── */
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 22px; padding-bottom: 16px; border-bottom: 3px solid #1a3a5c; }
  .company-block { }
  .company-name { font-size: 20px; font-weight: 800; color: #1a3a5c; letter-spacing: -0.5px; line-height: 1; }
  .company-sub { font-size: 10px; color: #6c757d; margin-top: 3px; font-weight: 500; }
  .po-block { text-align: right; }
  .po-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #6c757d; }
  .po-number { font-size: 22px; font-weight: 800; color: #1a3a5c; letter-spacing: -1px; line-height: 1.1; }
  .status-pill { display: inline-block; margin-top: 5px; padding: 3px 10px; border-radius: 20px; font-size: 9px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #fff; background: ${statusClr}; }

  /* ── Info grid ── */
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 18px; border: 1.5px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
  .info-section { padding: 12px 16px; }
  .info-section:first-child { border-right: 1.5px solid #e2e8f0; }
  .info-section-title { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; margin-bottom: 8px; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
  .info-label { color: #64748b; font-weight: 500; min-width: 90px; }
  .info-value { font-weight: 600; color: #1a1a2e; text-align: right; }
  .info-value.primary { color: #1a3a5c; }

  /* ── Table ── */
  .table-wrap { margin-bottom: 0; }
  table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
  thead tr { background: #1a3a5c; }
  thead th { padding: 8px 8px; color: #fff; font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; }
  thead th.center { text-align: center; }
  thead th.right { text-align: right; }
  tbody tr { border-bottom: 1px solid #f1f5f9; }
  tbody tr:nth-child(even) { background: #f8fafc; }
  tbody tr:last-child { border-bottom: 2px solid #e2e8f0; }
  td { padding: 7px 8px; vertical-align: middle; }
  td.center { text-align: center; }
  td.right { text-align: right; }
  td.bold { font-weight: 700; }
  td.mono { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: #1a3a5c; font-weight: 600; }

  /* ── Totals ── */
  .totals { margin-top: 0; margin-left: auto; width: 260px; }
  .total-row { display: flex; justify-content: space-between; padding: 5px 10px; font-size: 11px; }
  .total-row.subtotal { border-top: 1px solid #e2e8f0; color: #64748b; }
  .total-row.ppn { color: #f59e0b; font-weight: 600; }
  .total-row.grand { background: #1a3a5c; color: #fff; border-radius: 0 0 8px 8px; padding: 9px 12px; font-weight: 800; font-size: 13px; }

  /* ── Footer ── */
  .sign-section { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 28px; }
  .sign-box { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; }
  .sign-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 40px; }
  .sign-line { border-top: 1.5px solid #cbd5e1; padding-top: 6px; font-size: 10px; font-weight: 600; color: #475569; }

  .footer-note { margin-top: 20px; padding: 10px 14px; background: #f8fafc; border-left: 3px solid #1a3a5c; border-radius: 0 6px 6px 0; font-size: 9.5px; color: #64748b; }

  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .page { padding: 10mm 12mm; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    <div class="company-block">
      <div class="company-name">PT. Cipta Sarana Makmur</div>
      <div class="company-sub">CSM Inventory Management System</div>
    </div>
    <div class="po-block">
      <div class="po-label">Purchase Order</div>
      <div class="po-number">${po.po_number}</div>
      <div><span class="status-pill">${statusText}</span></div>
    </div>
  </div>

  <!-- Info Grid -->
  <div class="info-grid">
    <div class="info-section">
      <div class="info-section-title">Informasi Vendor</div>
      <div class="info-row"><span class="info-label">Vendor</span><span class="info-value primary">${po.vendor_name || '-'}</span></div>
      <div class="info-row"><span class="info-label">Kontak</span><span class="info-value">${po.vendor_contact || '-'}</span></div>
      <div class="info-row"><span class="info-label">No. MR / PM</span><span class="info-value">${mrpm}</span></div>
    </div>
    <div class="info-section">
      <div class="info-section-title">Informasi Pengiriman</div>
      <div class="info-row"><span class="info-label">Gudang Tujuan</span><span class="info-value primary">${po.warehouse?.name || '-'}</span></div>
      <div class="info-row"><span class="info-label">Tgl. Dibuat</span><span class="info-value">${fmtDate(po.created_at)}</span></div>
      <div class="info-row"><span class="info-label">Est. Tiba</span><span class="info-value">${estTiba}</span></div>
      <div class="info-row"><span class="info-label">Dibuat Oleh</span><span class="info-value">${po.creator?.name || '-'}</span></div>
    </div>
  </div>

  <!-- Items Table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th class="center" style="width:28px">#</th>
          <th class="center" style="width:90px">Part Number</th>
          <th>Nama Barang</th>
          <th class="center" style="width:70px">Kode Unit</th>
          <th class="center" style="width:70px">Tipe Unit</th>
          <th class="center" style="width:40px">Qty</th>
          <th class="center" style="width:40px">Sat.</th>
          <th class="right" style="width:90px">Harga Satuan</th>
          <th class="right" style="width:90px">Total</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  </div>

  <!-- Totals -->
  <div class="totals">
    <div class="total-row subtotal"><span>Subtotal</span><span>${fmtRp(subtotal)}</span></div>
    ${diskonPct > 0
      ? `<div class="total-row" style="color:#dc3545;"><span>Diskon ${diskonPct}%</span><span>- ${fmtRp(diskonAmt)}</span></div>`
      : ''}
    ${ppnAmt > 0
      ? `<div class="total-row ppn"><span>PPN ${po.ppn_percent || 0}%</span><span>${fmtRp(ppnAmt)}</span></div>`
      : `<div class="total-row" style="color:#94a3b8;font-size:10px"><span>PPN</span><span>Tidak kena PPN</span></div>`}
    <div class="total-row grand"><span>Grand Total</span><span>${fmtRp(grandTotal)}</span></div>
  </div>

  <!-- Catatan -->
  ${po.notes ? `<div class="footer-note"><strong>Catatan:</strong> ${po.notes}</div>` : ''}

  <!-- Tanda Tangan -->
  <div class="sign-section">
    <div class="sign-box">
      <div class="sign-label">Ordered By</div>
      <div class="sign-line">${po.creator?.name || '..................'}</div>
    </div>
    <div class="sign-box">
      <div class="sign-label">Logistic</div>
      <div class="sign-line">..................</div>
    </div>
    <div class="sign-box">
      <div class="sign-label">Approved By</div>
      <div class="sign-line">..................</div>
    </div>
  </div>

</div>
</body>
</html>`
}

// ── Print PDF ────────────────────────────────────────────
function printPDF(po) {
  const html = buildPOHtml(po)
  const win  = window.open('', '_blank', 'width=900,height=700')
  win.document.write(html)
  win.document.close()
  win.onload = () => { win.focus(); win.print() }
}

async function printPODirect(po) {
  try {
    const res = await axios.get(`/purchase-orders/${po.id}`)
    printPDF(res.data.data)
  } catch { toast.error('Gagal memuat data PO') }
}

// ── Export Excel ─────────────────────────────────────────
async function exportExcel(po) {
  await exportPOExcel(po, toast)
}
</script>