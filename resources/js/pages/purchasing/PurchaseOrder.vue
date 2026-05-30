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

    <!-- Summary Card: Cash vs Kredit -->
    <div class="row g-2 mb-3" v-if="summary">
      <div class="col-6 col-md-3">
        <div class="csm-card h-100">
          <div class="csm-card-body py-2 px-3 d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10" style="width:38px;height:38px;min-width:38px">
              <i class="bi bi-cash-coin text-success fs-5"></i>
            </div>
            <div>
              <div class="fw-bold fs-5 lh-1">{{ summary.cash_count }}</div>
              <div class="small text-muted">PO Cash</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="csm-card h-100">
          <div class="csm-card-body py-2 px-3 d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10" style="width:38px;height:38px;min-width:38px">
              <i class="bi bi-credit-card text-primary fs-5"></i>
            </div>
            <div>
              <div class="fw-bold fs-5 lh-1">{{ summary.kredit_count }}</div>
              <div class="small text-muted">PO Kredit</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="csm-card h-100" :class="summary.near_due_count > 0 ? 'border-warning' : ''">
          <div class="csm-card-body py-2 px-3 d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10" style="width:38px;height:38px;min-width:38px">
              <i class="bi bi-clock-history text-warning fs-5"></i>
            </div>
            <div>
              <div class="fw-bold fs-5 lh-1">{{ summary.near_due_count }}</div>
              <div class="small text-muted">Mendekati Jatuh Tempo</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="csm-card h-100" :class="summary.overdue_count > 0 ? 'border-danger' : ''">
          <div class="csm-card-body py-2 px-3 d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10" style="width:38px;height:38px;min-width:38px">
              <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
            </div>
            <div>
              <div class="fw-bold fs-5 lh-1">{{ summary.overdue_count }}</div>
              <div class="small text-muted">PO Kredit Overdue</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter -->
    <div class="csm-card mb-3">
      <div class="csm-card-body py-2">
        <div class="row g-2">
          <div class="col-md-3">
            <input v-model="filters.search" class="form-control form-control-sm" placeholder="🔍 Cari No. PO..." @input="debouncedLoad" />
          </div>
          <div class="col-md-2">
            <select v-model="filters.status" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="sent_to_vendor">Dikirim ke Vendor</option>
              <option value="partial_received">Diterima Sebagian</option>
              <option value="completed">Selesai</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </div>
          <!-- Filter Jenis Pembayaran (BARU) -->
          <div class="col-md-2">
            <select v-model="filters.payment_type" class="form-select form-select-sm" @change="loadData">
              <option value="">Semua Pembayaran</option>
              <option value="cash">Cash</option>
              <option value="kredit">Kredit</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="loadData" />
          </div>
          <div class="col-md-1">
            <button class="btn btn-outline-secondary btn-sm w-100" @click="resetFilters">Reset</button>
          </div>
        </div>
        <!-- Quick filter overdue/near-due -->
        <div class="d-flex gap-2 mt-2">
          <button class="btn btn-xs btn-outline-danger" :class="filters.overdue ? 'active' : ''" @click="toggleOverdue">
            <i class="bi bi-exclamation-triangle me-1"></i>Overdue
          </button>
          <button class="btn btn-xs btn-outline-warning" :class="filters.near_due ? 'active' : ''" @click="toggleNearDue">
            <i class="bi bi-clock me-1"></i>Jatuh Tempo &le;7 Hari
          </button>
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
                <th>Pembayaran</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
                <th>Dibuat Oleh</th>
                <th>Tanggal</th>
                <th class="text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!list.length">
                <td colspan="12" class="text-center text-muted py-5">Belum ada Purchase Order</td>
              </tr>
              <tr v-for="po in list" :key="po.id"
                :class="isOverdueRow(po) ? 'table-danger' : isNearDueRow(po) ? 'table-warning' : ''">
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
                <!-- Kolom Pembayaran (BARU) -->
                <td>
                  <span class="badge" :class="po.payment_type === 'kredit' ? 'bg-primary' : 'bg-success'">
                    <i class="bi" :class="po.payment_type === 'kredit' ? 'bi-credit-card' : 'bi-cash-coin'"></i>
                    {{ po.payment_type === 'kredit' ? `Kredit ${po.payment_term_days}h` : 'Cash' }}
                  </span>
                </td>
                <!-- Kolom Jatuh Tempo (BARU) -->
                <td>
                  <template v-if="po.payment_type === 'kredit' && po.payment_due_date">
                    <div :class="isOverdueRow(po) ? 'text-danger fw-semibold' : isNearDueRow(po) ? 'text-warning fw-semibold' : 'text-muted'">
                      <small>{{ $formatDate(po.payment_due_date) }}</small>
                      <span v-if="isOverdueRow(po)" class="badge bg-danger ms-1" style="font-size:0.6rem;">LEWAT</span>
                      <span v-else-if="isNearDueRow(po)" class="badge bg-warning text-dark ms-1" style="font-size:0.6rem;">SEGERA</span>
                    </div>
                  </template>
                  <small v-else class="text-muted">-</small>
                </td>
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
                    <!-- Tombol Buat Surat Jalan dinonaktifkan
                    <button v-if="po.status === 'sent_to_vendor' && !po.surat_jalan_count && can('create-sj')"
                      class="btn btn-outline-success" title="Buat Surat Jalan" @click="openSJModal(po)">
                      <i class="bi bi-truck"></i>
                    </button>
                    -->
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
                    <!-- Info pembayaran (BARU) -->
                    <tr>
                      <td class="text-muted">Pembayaran</td>
                      <td>
                        <span class="badge" :class="selectedPO.payment_type === 'kredit' ? 'bg-primary' : 'bg-success'">
                          {{ selectedPO.payment_type === 'kredit' ? 'Kredit' : 'Cash' }}
                        </span>
                        <span v-if="selectedPO.payment_type === 'kredit'" class="ms-1 small text-muted">
                          {{ selectedPO.payment_term_days }} hari
                        </span>
                      </td>
                    </tr>
                    <tr v-if="selectedPO.payment_type === 'kredit'">
                      <td class="text-muted">Jatuh Tempo</td>
                      <td>
                        <span :class="isOverdueRow(selectedPO) ? 'text-danger fw-semibold' : isNearDueRow(selectedPO) ? 'text-warning fw-semibold' : ''">
                          {{ selectedPO.payment_due_date ? $formatDate(selectedPO.payment_due_date) : '-' }}
                        </span>
                        <span v-if="isOverdueRow(selectedPO)" class="badge bg-danger ms-1">Terlambat</span>
                        <span v-else-if="isNearDueRow(selectedPO)" class="badge bg-warning text-dark ms-1">Segera</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Invoice supplier (hanya untuk PO kredit) -->
            <div v-if="selectedPO.payment_type === 'kredit' && selectedPO.supplier_invoices?.length" class="mb-3">
              <div class="small fw-semibold text-muted mb-1"><i class="bi bi-receipt me-1"></i>Invoice Hutang Supplier</div>
              <div class="table-responsive">
                <table class="table table-sm csm-table mb-0">
                  <thead><tr><th>No. Invoice</th><th>Tgl. Invoice</th><th>Jatuh Tempo</th><th class="text-end">Total</th><th class="text-end">Sisa</th><th>Status</th></tr></thead>
                  <tbody>
                    <tr v-for="inv in selectedPO.supplier_invoices" :key="inv.id">
                      <td class="fw-semibold small">
                        <span v-if="inv.invoice_number">{{ inv.invoice_number }}</span>
                        <span v-else class="badge bg-warning text-dark" style="font-size:9px;">Belum ada no. supplier</span>
                        <div class="text-muted" style="font-size:10px;">{{ inv.internal_number }}</div>
                      </td>
                      <td><small>{{ $formatDate(inv.invoice_date) }}</small></td>
                      <td>
                        <small :class="inv.status !== 'paid' && new Date(inv.due_date) < new Date() ? 'text-danger fw-semibold' : ''">
                          {{ $formatDate(inv.due_date) }}
                        </small>
                      </td>
                      <td class="text-end small">Rp {{ $formatNumber(inv.total_amount) }}</td>
                      <td class="text-end small fw-semibold" :class="parseFloat(inv.remaining_amount) > 0 ? 'text-danger' : 'text-success'">
                        Rp {{ $formatNumber(inv.remaining_amount) }}
                      </td>
                      <td>
                        <span class="badge" :class="inv.status === 'paid' ? 'bg-success' : inv.status === 'partial' ? 'bg-warning text-dark' : 'bg-danger'">
                          {{ inv.status === 'paid' ? 'Lunas' : inv.status === 'partial' ? 'Sebagian' : 'Belum Bayar' }}
                        </span>
                      </td>
                    </tr>
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
                      <span class="d-inline-flex align-items-center gap-1">
                        <code
                          v-if="item.part_number || item.item?.part_number"
                          class="small text-primary fw-semibold"
                        >{{ item.part_number || item.item?.part_number }}</code>
                        <span v-else class="text-muted small">-</span>
                        <PartNumberEditButton
                          :po="selectedPO"
                          :item="item"
                          :can-edit="can('manage-po')"
                          @updated="onPartNumberUpdated"
                        />
                      </span>
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
                    <td colspan="8" class="text-end text-muted small">Diskon {{ selectedPO.diskon_persen }}%</td>
                    <td class="text-end small text-danger fw-semibold">- Rp {{ $formatNumber(selectedPO.diskon_amount) }}</td>
                  </tr>
                  <tr v-if="parseFloat(selectedPO.ppn_percent) > 0">
                    <td colspan="8" class="text-end text-muted small">PPN {{ selectedPO.ppn_percent }}%</td>
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
            <button type="button" class="btn btn-outline-danger btn-sm" @click="openDetailPrint(selectedPO)">
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
                <thead><tr><th>Part Number</th><th>Nama Barang</th><th class="text-end">Qty PO</th><th class="text-end">Sudah Diterima</th><th class="text-end">Sisa</th><th style="width:130px">Qty Diterima Kini</th><th>Satuan</th></tr></thead>
                <tbody>
                  <tr v-for="(item, idx) in sjForm.items" :key="idx">
                    <td>
                      <code v-if="item.item?.part_number" class="small text-primary fw-semibold">{{ item.item.part_number }}</code>
                      <span v-else class="text-muted small">-</span>
                    </td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td class="text-end text-muted">{{ item.qty_ordered }}</td>
                    <td class="text-end"><span :class="item.qty_received_before > 0 ? 'text-warning fw-semibold' : 'text-muted'">{{ item.qty_received_before }}</span></td>
                    <td class="text-end text-danger fw-semibold">{{ item.qty_remaining }}</td>
                    <td><input v-model="item.qty_received" type="number" class="form-control form-control-sm" min="0" :max="item.qty_remaining" step="0.01" /></td>
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
              Pilih satu atau lebih Permintaan Material yang sudah disetujui.
            </div>
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
                    <th>No. PM</th><th>Tipe</th><th>Gudang</th><th>Diajukan Oleh</th>
                    <th class="text-center">Jumlah Item</th><th>Tgl. Disetujui</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!availablePMs.length">
                    <td colspan="7" class="text-center text-muted py-4">Tidak ada PM yang tersedia</td>
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
            <div class="d-flex flex-wrap gap-2 mb-3">
              <span class="small fw-semibold text-muted me-1">PM Sumber:</span>
              <span v-for="pm in selectedPMs" :key="pm.id" class="badge bg-primary">{{ pm.nomor }}</span>
            </div>

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

              <!-- ── Jenis Pembayaran (BARU) ─────────────────────────────── -->
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Jenis Pembayaran <span class="text-danger">*</span></label>
                <div class="d-flex gap-2">
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="pt-cash" value="cash" v-model="createForm.payment_type" />
                    <label class="form-check-label small" for="pt-cash">
                      <i class="bi bi-cash-coin text-success me-1"></i>Cash
                    </label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" id="pt-kredit" value="kredit" v-model="createForm.payment_type" />
                    <label class="form-check-label small" for="pt-kredit">
                      <i class="bi bi-credit-card text-primary me-1"></i>Kredit
                    </label>
                  </div>
                </div>
              </div>
              <!-- Tenor — hanya tampil jika Kredit -->
              <div class="col-md-3" v-if="createForm.payment_type === 'kredit'">
                <label class="form-label small fw-semibold">Tenor <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <input v-model="createForm.payment_term_days" type="number" class="form-control form-control-sm"
                    min="1" max="365" placeholder="30" />
                  <span class="input-group-text">hari</span>
                </div>
              </div>
              <!-- Estimasi jatuh tempo — tampil saat kredit + tenor terisi -->
              <div class="col-md-5" v-if="createForm.payment_type === 'kredit' && createForm.payment_term_days > 0">
                <label class="form-label small fw-semibold">Estimasi Jatuh Tempo</label>
                <div class="form-control form-control-sm bg-light text-muted" style="cursor:default;">
                  <i class="bi bi-calendar-event me-1"></i>{{ estimatedDueDate }}
                </div>
              </div>

              <div class="col-12">
                <label class="form-label small fw-semibold">Catatan</label>
                <input v-model="createForm.notes" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
              </div>
            </div>

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
              Satu PO hanya boleh untuk <strong>satu gudang</strong>.
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
              <!-- Ringkasan Pembayaran (BARU) -->
              <div v-if="createForm.payment_type === 'kredit'" class="d-flex align-items-center justify-content-between border-top pt-2 mb-1">
                <span class="small text-primary">
                  <i class="bi bi-credit-card me-1"></i>Kredit {{ createForm.payment_term_days }} hari
                </span>
                <span class="small text-muted">Jatuh tempo: {{ estimatedDueDate }}</span>
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
          <template v-if="createStep === 1">
            <button class="btn btn-primary btn-sm" @click="goToStep2" :disabled="!selectedPMIds.length">
              Lanjut Pilih Item <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </template>
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
  <!-- Modal Pilih Penandatangan -->
  <SignerPickerModal
    v-model="showSignerModal"
    :signers="signers"
    @confirm="printPDF"
  />
</template>
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { Modal } from 'bootstrap'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { useRealtime } from '@/composables/useRealtime'
import { useSignerPicker } from '@/composables/useSignerPicker'
import SignerPickerModal from '@/components/SignerPickerModal.vue'
import PartNumberEditButton from '@/components/PartNumberEditButton.vue'

const toast = useToast()
const auth = useAuthStore()
const { listenPurchaseOrder, stopPurchaseOrder } = useRealtime()
const can = (p) => auth.hasPermission(p)

// ── Signer Picker ───────────────────────────────────────────────────
const { showSignerModal, signers, openSignerPicker } = useSignerPicker()
let pendingPrintPO = null

// ── State ────────────────────────────────────────────────────────────
const list    = ref([])
const loading = ref(false)
const saving  = ref(false)
const summary = ref(null)
const meta    = ref({ total: 0, page: 1, last_page: 1 })

const filters = ref({
  search: '',
  status: '',
  payment_type: '',
  date_from: '',
  date_to: '',
  overdue: false,
  near_due: false,
})

const selectedPO  = ref(null)
const warehouses  = ref([])

// ── Create PO ────────────────────────────────────────────────────────
const createStep     = ref(1)
const availablePMs   = ref([])
const selectedPMIds  = ref([])
const selectedPMs    = ref([])
const pmSearch       = ref('')
const pmWarehouseFilter = ref('')
const pmLoading      = ref(false)
let pmTimer = null

const createForm = ref({
  vendor_name: '',
  vendor_contact: '',
  expected_date: '',
  notes: '',
  payment_type: 'cash',
  payment_term_days: 30,
  diskon_persen: 0,
  ppn_percent: 0,
  items: [],
})

// ── Surat Jalan Form ─────────────────────────────────────────────────
const sjForm = ref({
  vendor_name: '',
  received_date: '',
  driver_name: '',
  vehicle_plate: '',
  notes: '',
  items: [],
})

// ── Modals ───────────────────────────────────────────────────────────
let modalDetail = null
let modalCreate = null
let modalSJ     = null

let timer = null

// ── Computed ─────────────────────────────────────────────────────────
const selectableItems = computed(() =>
  createForm.value.items.filter(i => {
    const remaining = i.qty_pm - (i.qty_already_ordered || 0)
    return remaining > 0
  })
)

const hasMultipleWarehouses = computed(() => {
  if (!selectedPMs.value.length) return false
  const ids = [...new Set(selectedPMs.value.map(p => p.warehouse?.id))]
  return ids.length > 1
})

const warehouseGroups = computed(() => {
  const groups = {}
  selectedPMs.value.forEach(pm => {
    const wid = pm.warehouse?.id
    if (!groups[wid]) groups[wid] = { warehouseId: wid, warehouseName: pm.warehouse?.name, pmNomors: [] }
    groups[wid].pmNomors.push(pm.nomor)
  })
  return Object.values(groups)
})

const createSubtotal = computed(() =>
  createForm.value.items
    .filter(i => i.selected)
    .reduce((s, i) => s + (parseFloat(i.qty || 0) * parseFloat(i.harga_satuan || 0)), 0)
)

const createDiskonAmt = computed(() =>
  (createSubtotal.value * (parseFloat(createForm.value.diskon_persen) || 0)) / 100
)

const createPPN = computed(() =>
  ((createSubtotal.value - createDiskonAmt.value) * (parseFloat(createForm.value.ppn_percent) || 0)) / 100
)

const createTotal = computed(() =>
  createSubtotal.value - createDiskonAmt.value + createPPN.value
)

const estimatedDueDate = computed(() => {
  const days = parseInt(createForm.value.payment_term_days || 0)
  if (!days) return '-'
  const d = new Date()
  d.setDate(d.getDate() + days)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
})

// ── Lifecycle ────────────────────────────────────────────────────────
onMounted(async () => {
  modalDetail = new Modal(document.getElementById('modalDetailPO'))
  modalCreate = new Modal(document.getElementById('modalBuatPO'))
  modalSJ     = new Modal(document.getElementById('modalSJFromPO'))

  const [whRes] = await Promise.all([
    axios.get('/warehouses'),
  ])
  warehouses.value = whRes.data.data || []

  loadData()
  loadSummary()

  if (listenPurchaseOrder) listenPurchaseOrder(() => { loadData(); loadSummary() })
})

onUnmounted(() => {
  if (stopPurchaseOrder) stopPurchaseOrder()
})

// ── Data Loading ─────────────────────────────────────────────────────
async function loadData() {
  loading.value = true
  try {
    const params = {
      ...filters.value,
      page: meta.value.page,
      per_page: 15,
    }
    const res = await axios.get('/purchase-orders', { params })
    list.value = res.data.data ?? []
    meta.value = res.data.meta ?? { total: 0, page: 1, last_page: 1 }
  } catch (e) {
    toast.error('Gagal memuat data PO')
  } finally {
    loading.value = false
  }
}

async function loadSummary() {
  try {
    const res = await axios.get('/purchase-orders/summary')
    summary.value = res.data.data ?? res.data
  } catch {}
}

function debouncedLoad() {
  clearTimeout(timer)
  timer = setTimeout(() => { meta.value.page = 1; loadData() }, 400)
}

function changePage(p) { meta.value.page = p; loadData() }

function resetFilters() {
  filters.value = { search: '', status: '', payment_type: '', date_from: '', date_to: '', overdue: false, near_due: false }
  meta.value.page = 1
  loadData()
}

function toggleOverdue() {
  filters.value.overdue = !filters.value.overdue
  if (filters.value.overdue) filters.value.near_due = false
  meta.value.page = 1
  loadData()
}

function toggleNearDue() {
  filters.value.near_due = !filters.value.near_due
  if (filters.value.near_due) filters.value.overdue = false
  meta.value.page = 1
  loadData()
}

// ── Status Helpers ───────────────────────────────────────────────────
const statusLabel = (s) => ({
  draft: 'Draft',
  sent_to_vendor: 'Dikirim ke Vendor',
  partial_received: 'Diterima Sebagian',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
}[s] || s)

const statusClass = (s) => ({
  draft: 'bg-secondary',
  sent_to_vendor: 'bg-info text-dark',
  partial_received: 'bg-warning text-dark',
  completed: 'bg-success',
  cancelled: 'bg-danger',
}[s] || 'bg-secondary')

function isOverdueRow(po) {
  if (po.payment_type !== 'kredit' || !po.payment_due_date) return false
  return new Date(po.payment_due_date) < new Date()
}

function isNearDueRow(po) {
  if (po.payment_type !== 'kredit' || !po.payment_due_date) return false
  const due = new Date(po.payment_due_date)
  const now = new Date()
  const diff = (due - now) / (1000 * 60 * 60 * 24)
  return diff >= 0 && diff <= 7
}

// ── Detail ───────────────────────────────────────────────────────────
async function openDetail(po) {
  try {
    const res = await axios.get(`/purchase-orders/${po.id}`)
    selectedPO.value = res.data.data ?? res.data
  } catch {
    selectedPO.value = po
  }
  modalDetail.show()
}

// ── Send to Vendor ───────────────────────────────────────────────────
async function doSend(po) {
  if (!confirm(`Kirim PO ${po.po_number} ke vendor?`)) return
  try {
    await axios.post(`/purchase-orders/${po.id}/send`)
    toast.success('PO berhasil dikirim ke vendor')
    loadData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal mengirim PO')
  }
}

// ── Print PDF ────────────────────────────────────────────────────────
async function printPODirect(po) {
  pendingPrintPO = po
  await openSignerPicker()
}

async function printPDF(signerIds) {
  const po = pendingPrintPO
  if (!po) return
  showSignerModal.value = false
  pendingPrintPO = null

  const resolvedSigners = (signerIds || []).map(id => {
    const found = signers.value.find(s => s.id === id)
    return found
      ? { label: found.name, name: found.name, position: found.position || '', signature: found.signature_url || null }
      : { label: '', name: '', position: '', signature: null }
  })

  // Fetch full detail for print
  let fullPO = po
  try {
    const res = await axios.get(`/purchase-orders/${po.id}`)
    fullPO = res.data.data ?? res.data
  } catch {}

  const html = buildPOHtml(fullPO, resolvedSigners)
  const win = window.open('', '_blank')
  win.document.write(html)
  win.document.close()
  setTimeout(() => win.print(), 800)
}

function openDetailPrint(po) {
  pendingPrintPO = po
  openSignerPicker()
}

function escHtml(str) {
  return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function fmtRp(val) { return 'Rp ' + Number(val || 0).toLocaleString('id-ID') }
function fmtDate(val) {
  if (!val) return '-'
  return new Date(val).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

function buildPOHtml(po, resolvedSigners = []) {
  const signBoxes = resolvedSigners.length
    ? resolvedSigners.map(s => `
        <div style="flex:1;min-width:120px;text-align:center;border:1.5px solid #e2e8f0;padding:10px 12px;border-radius:8px;background:#fafbfc;">
          <div style="font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;margin-bottom:6px;">${escHtml(s.label)}</div>
          ${s.signature
            ? `<div style="height:90px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;"><img src="${s.signature}" style="max-height:88px;max-width:100%;width:auto;height:auto;object-fit:contain;display:block;margin:0 auto;"/></div>`
            : `<div style="height:90px;margin-bottom:6px;"></div>`}
          <div style="border-top:1.5px solid #cbd5e1;padding-top:6px;font-size:9pt;font-weight:600;color:#475569;">${escHtml(s.name)}</div>
        </div>`).join('')
    : ['Dibuat Oleh', 'Diperiksa', 'Disetujui'].map(l => `
        <div style="flex:1;min-width:120px;text-align:center;border:1.5px solid #e2e8f0;padding:10px 12px;border-radius:8px;background:#fafbfc;">
          <div style="font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;margin-bottom:6px;">${l}</div>
          <div style="height:90px;margin-bottom:6px;"></div>
          <div style="border-top:1.5px solid #cbd5e1;padding-top:6px;font-size:9pt;font-weight:600;color:#475569;"></div>
        </div>`).join('')

  const itemRows = (po.items || []).map((item, i) => `
    <tr style="${i % 2 ? 'background:#f8fafc;' : ''}">
      <td style="text-align:center;border:1px solid #dee2e6;padding:6px;">${i + 1}</td>
      <td style="font-family:monospace;color:#1a3a5c;font-weight:600;border:1px solid #dee2e6;padding:6px;">${escHtml(item.part_number || item.item?.part_number || '-')}</td>
      <td style="font-weight:600;border:1px solid #dee2e6;padding:6px;">${escHtml(item.nama_barang)}</td>
      <td style="text-align:center;border:1px solid #dee2e6;padding:6px;">${item.qty}</td>
      <td style="text-align:center;border:1px solid #dee2e6;padding:6px;">${escHtml(item.satuan)}</td>
      <td style="text-align:right;border:1px solid #dee2e6;padding:6px;">${fmtRp(item.harga_satuan)}</td>
      <td style="text-align:right;font-weight:600;border:1px solid #dee2e6;padding:6px;">${fmtRp(item.total_harga)}</td>
    </tr>`).join('')

  return `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<title>Purchase Order — ${escHtml(po.po_number)}</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 10pt; margin: 0; padding: 20px; color: #333; }
  @media print { body { padding: 0; } }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1a3a5c; color: #fff; padding: 8px; text-align: left; border: 1px solid #1a3a5c; font-size: 9pt; }
</style>
</head>
<body>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;border-bottom:3px solid #1a3a5c;padding-bottom:12px;">
    <div>
      <div style="font-size:18pt;font-weight:800;color:#1a3a5c;">PURCHASE ORDER</div>
      <div style="font-size:11pt;color:#64748b;">${escHtml(po.po_number)}</div>
    </div>
    <div style="text-align:right;font-size:9pt;color:#64748b;">
      <div>PT. Cipta Sarana Makmur</div>
      <div>Tanggal: ${fmtDate(po.created_at)}</div>
    </div>
  </div>
  <div style="display:flex;gap:20px;margin-bottom:16px;">
    <div style="flex:1;font-size:9pt;">
      <div><strong>Vendor:</strong> ${escHtml(po.vendor_name || '-')}</div>
      <div><strong>Kontak:</strong> ${escHtml(po.vendor_contact || '-')}</div>
      <div><strong>Gudang:</strong> ${escHtml(po.warehouse?.name || '-')}</div>
    </div>
    <div style="flex:1;font-size:9pt;">
      <div><strong>Status:</strong> ${statusLabel(po.status)}</div>
      <div><strong>Pembayaran:</strong> ${po.payment_type === 'kredit' ? `Kredit ${po.payment_term_days} hari` : 'Cash'}</div>
      ${po.payment_type === 'kredit' && po.payment_due_date ? `<div><strong>Jatuh Tempo:</strong> ${fmtDate(po.payment_due_date)}</div>` : ''}
      <div><strong>Est. Tiba:</strong> ${fmtDate(po.expected_date)}</div>
    </div>
  </div>
  <table style="margin-bottom:12px;">
    <thead><tr>
      <th style="width:30px;">#</th>
      <th>Part Number</th>
      <th>Nama Barang</th>
      <th style="width:50px;text-align:center;">Qty</th>
      <th style="width:50px;text-align:center;">Satuan</th>
      <th style="width:110px;text-align:right;">Harga Satuan</th>
      <th style="width:110px;text-align:right;">Total</th>
    </tr></thead>
    <tbody>${itemRows}</tbody>
    <tfoot>
      <tr><td colspan="6" style="text-align:right;padding:6px;border:1px solid #dee2e6;font-size:9pt;">Subtotal</td><td style="text-align:right;padding:6px;border:1px solid #dee2e6;">${fmtRp(po.items?.reduce((s, i) => s + parseFloat(i.total_harga || 0), 0))}</td></tr>
      ${parseFloat(po.diskon_persen) > 0 ? `<tr><td colspan="6" style="text-align:right;padding:6px;border:1px solid #dee2e6;font-size:9pt;">Diskon ${po.diskon_persen}%</td><td style="text-align:right;padding:6px;border:1px solid #dee2e6;color:#dc2626;">- ${fmtRp(po.diskon_amount)}</td></tr>` : ''}
      ${parseFloat(po.ppn_percent) > 0 ? `<tr><td colspan="6" style="text-align:right;padding:6px;border:1px solid #dee2e6;font-size:9pt;">PPN ${po.ppn_percent}%</td><td style="text-align:right;padding:6px;border:1px solid #dee2e6;color:#16a34a;">${fmtRp(po.ppn_amount)}</td></tr>` : ''}
      <tr><td colspan="6" style="text-align:right;padding:8px;border:1px solid #1a3a5c;background:#f0f5ff;font-weight:700;">GRAND TOTAL</td><td style="text-align:right;padding:8px;border:1px solid #1a3a5c;background:#f0f5ff;font-weight:700;color:#1a3a5c;">${fmtRp(po.grand_total || po.total_amount)}</td></tr>
    </tfoot>
  </table>
  ${po.notes ? `<div style="font-size:9pt;margin-bottom:16px;"><strong>Catatan:</strong> ${escHtml(po.notes)}</div>` : ''}
  <div style="display:flex;gap:12px;margin-top:32px;">${signBoxes}</div>
</body>
</html>`
}

// ── Export Excel ─────────────────────────────────────────────────────
async function exportExcel(po) {
  try {
    const res = await axios.get(`/purchase-orders/${po.id}`, { responseType: 'blob' })
    // Fallback: just print the detail
    toast.info('Fitur export Excel belum tersedia')
  } catch {
    toast.error('Gagal export')
  }
}

// ── Create PO ────────────────────────────────────────────────────────
function openCreateModal() {
  createStep.value = 1
  selectedPMIds.value = []
  selectedPMs.value = []
  createForm.value = {
    vendor_name: '',
    vendor_contact: '',
    expected_date: '',
    notes: '',
    payment_type: 'cash',
    payment_term_days: 30,
    diskon_persen: 0,
    ppn_percent: 0,
    items: [],
  }
  searchPM()
  modalCreate.show()
}

const warehouseColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
function getWarehouseColor(wid) {
  const keys = [...new Set(selectedPMs.value.map(p => p.warehouse?.id))]
  const idx = keys.indexOf(wid)
  return warehouseColors[idx % warehouseColors.length] || '#6c757d'
}

function debouncedSearchPM() {
  clearTimeout(pmTimer)
  pmTimer = setTimeout(() => searchPM(), 300)
}

async function searchPM() {
  pmLoading.value = true
  try {
    const res = await axios.get('/permintaan-material', {
      params: {
        status: 'approved,pending_purchasing,partial_ordered',
        search: pmSearch.value,
        warehouse_id: pmWarehouseFilter.value || undefined,
        per_page: 50,
        for_po: 1,
      }
    })
    availablePMs.value = res.data.data ?? []
  } catch {
    availablePMs.value = []
  } finally {
    pmLoading.value = false
  }
}

function togglePM(pm) {
  const idx = selectedPMIds.value.indexOf(pm.id)
  if (idx >= 0) {
    selectedPMIds.value.splice(idx, 1)
    selectedPMs.value = selectedPMs.value.filter(p => p.id !== pm.id)
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

function removePM(pmId) {
  selectedPMIds.value = selectedPMIds.value.filter(id => id !== pmId)
  selectedPMs.value = selectedPMs.value.filter(p => p.id !== pmId)
}

async function goToStep2() {
  if (!selectedPMIds.value.length) return
  pmLoading.value = true
  try {
    // Fetch full PM details to get items
    const fullPMs = await Promise.all(
      selectedPMs.value.map(pm => axios.get(`/permintaan-material/${pm.id}`).then(r => r.data.data ?? r.data))
    )
    // Build items list
    const items = []
    fullPMs.forEach(pm => {
      (pm.items || []).forEach(item => {
        const qtyAlready = parseFloat(item.qty_po || item.qty_already_ordered || 0)
        const remaining = parseFloat(item.qty) - qtyAlready
        if (remaining > 0) {
          items.push({
            _key: `${pm.id}_${item.id}`,
            pm_id: pm.id,
            pm_item_id: item.id,
            item_id: item.item_id || item.item?.id,
            part_number: item.part_number || item.item?.part_number || '',
            nama_barang: item.nama_barang || item.item?.name || '',
            satuan: item.satuan || item.unit || '',
            qty_pm: parseFloat(item.qty),
            qty_already_ordered: qtyAlready,
            qty: remaining,
            harga_satuan: item.harga_satuan || item.item?.harga_satuan || 0,
            selected: true,
          })
        }
      })
    })
    createForm.value.items = items
    createStep.value = 2
  } catch (e) {
    toast.error('Gagal memuat item PM')
  } finally {
    pmLoading.value = false
  }
}

async function saveCreatePO() {
  if (hasMultipleWarehouses.value) return
  const selectedItems = createForm.value.items.filter(i => i.selected && parseFloat(i.qty) > 0)
  if (!selectedItems.length) {
    toast.warning('Pilih minimal 1 item')
    return
  }
  if (!createForm.value.vendor_name) {
    toast.warning('Nama vendor wajib diisi')
    return
  }
  saving.value = true
  try {
    const payload = {
      vendor_name: createForm.value.vendor_name,
      vendor_contact: createForm.value.vendor_contact,
      expected_date: createForm.value.expected_date || null,
      notes: createForm.value.notes,
      payment_type: createForm.value.payment_type,
      payment_term_days: createForm.value.payment_type === 'kredit' ? createForm.value.payment_term_days : null,
      diskon_persen: createForm.value.diskon_persen || 0,
      ppn_percent: createForm.value.ppn_percent || 0,
      permintaan_material_ids: selectedPMIds.value,
      items: selectedItems.map(i => ({
        pm_item_id: i.pm_item_id,
        item_id: i.item_id,
        nama_barang: i.nama_barang,
        qty: parseFloat(i.qty),
        satuan: i.satuan,
        harga_satuan: parseFloat(i.harga_satuan) || 0,
      })),
    }
    await axios.post('/purchase-orders', payload)
    toast.success('Purchase Order berhasil dibuat')
    modalCreate.hide()
    loadData()
    loadSummary()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal membuat PO')
  } finally {
    saving.value = false
  }
}

// ── Surat Jalan from PO ───────────────────────────────────────────────
function openSJModal(po) {
  selectedPO.value = po
  sjForm.value = {
    vendor_name: po.vendor_name || '',
    received_date: new Date().toISOString().slice(0, 10),
    driver_name: '',
    vehicle_plate: '',
    notes: '',
    items: (po.items || []).map(item => ({
      po_item_id: item.id,
      nama_barang: item.nama_barang,
      item: item.item,
      qty_ordered: parseFloat(item.qty),
      qty_received_before: parseFloat(item.qty_received || 0),
      qty_remaining: parseFloat(item.qty) - parseFloat(item.qty_received || 0),
      qty_received: parseFloat(item.qty) - parseFloat(item.qty_received || 0),
      satuan: item.satuan,
    }))
  }
  modalSJ.show()
}

async function saveSJ() {
  saving.value = true
  try {
    await axios.post(`/surat-jalan`, {
      purchase_order_id: selectedPO.value.id,
      vendor_name: sjForm.value.vendor_name,
      received_date: sjForm.value.received_date,
      driver_name: sjForm.value.driver_name,
      vehicle_plate: sjForm.value.vehicle_plate,
      notes: sjForm.value.notes,
      items: sjForm.value.items.map(i => ({
        po_item_id: i.po_item_id,
        qty_received: parseFloat(i.qty_received) || 0,
      }))
    })
    toast.success('Surat Jalan berhasil dibuat')
    modalSJ.hide()
    loadData()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal membuat Surat Jalan')
  } finally {
    saving.value = false
  }
}

// ── Part Number Updated ───────────────────────────────────────────────
function onPartNumberUpdated(updatedItem) {
  if (!selectedPO.value) return
  const idx = selectedPO.value.items?.findIndex(i => i.id === updatedItem.id)
  if (idx >= 0) selectedPO.value.items[idx] = { ...selectedPO.value.items[idx], ...updatedItem }
}
</script>