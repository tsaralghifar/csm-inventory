<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-sm btn-outline-secondary" @click="$router.back()">
        <i class="bi bi-arrow-left"></i>
      </button>
      <div>
        <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Detail Permintaan Material</h5>
        <small class="text-muted">{{ pm?.nomor }}</small>
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span v-if="pm" class="badge" :class="pm.type === 'part' ? 'bg-primary' : 'bg-info text-dark'">
          {{ pm.type === 'part' ? '🔧 MR Part' : '🏢 MR Office' }}
        </span>
        <span v-if="pm" class="badge fs-6" :class="statusClass(pm.status)">{{ statusLabel(pm.status) }}</span>
        <button v-if="pm" class="btn btn-outline-success btn-sm" @click="exportExcel" title="Export Excel">
          <i class="bi bi-file-earmark-excel me-1"></i>Excel
        </button>
        <button v-if="pm" class="btn btn-outline-danger btn-sm" @click="printPDF" title="Print / PDF">
          <i class="bi bi-printer me-1"></i>Print
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5"><div class="csm-spinner"></div></div>

    <div v-else-if="pm">
      <div class="row g-3">

        <!-- Info Umum -->
        <div class="col-md-5">
          <div class="csm-card h-100">
            <div class="csm-card-header"><h6><i class="bi bi-info-circle me-2"></i>Informasi Permintaan</h6></div>
            <div class="csm-card-body">
              <table class="table table-sm table-borderless mb-0 small">
                <tbody>
                <tr><td class="text-muted" width="40%">No. PM</td><td class="fw-semibold">{{ pm.nomor }}</td></tr>
                <tr><td class="text-muted">Tipe</td>
                  <td>
                    <span class="badge" :class="pm.type === 'part' ? 'bg-primary' : 'bg-info text-dark'">
                      {{ pm.type === 'part' ? 'MR Part' : 'MR Office' }}
                    </span>
                  </td>
                </tr>
                <tr><td class="text-muted">Site / Gudang</td><td>{{ pm.warehouse?.name }}</td></tr>
                <tr><td class="text-muted">Diajukan Oleh</td><td>{{ pm.requester?.name }}</td></tr>
                <tr><td class="text-muted">Tanggal Buat</td><td>{{ $formatDate(pm.created_at) }}</td></tr>
                <tr><td class="text-muted">Tgl. Dibutuhkan</td><td>{{ pm.needed_date ? $formatDate(pm.needed_date) : '-' }}</td></tr>
                <tr><td class="text-muted">Catatan</td><td>{{ pm.notes || '-' }}</td></tr>
                <!-- Info pengajuan PO jika sudah diajukan -->
                <tr v-if="pm.po_submitted_at">
                  <td class="text-muted">Diajukan ke Purchasing</td>
                  <td>
                    <span class="fw-semibold text-purple">{{ pm.poSubmitter?.name || '-' }}</span>
                    <small class="text-muted d-block">{{ $formatDate(pm.po_submitted_at) }}</small>
                  </td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Timeline Approval -->
        <div class="col-md-7">
          <div class="csm-card h-100">
            <div class="csm-card-header"><h6><i class="bi bi-diagram-3 me-2"></i>Alur Persetujuan</h6></div>
            <div class="csm-card-body">

              <!-- ===== ALUR MR PART ===== -->
              <template v-if="pm.type === 'part'">
                <div class="d-flex align-items-start">

                  <!-- Submit -->
                  <div class="text-center" style="min-width:60px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="pm.status !== 'draft' ? 'bg-success text-white' : 'bg-secondary text-white'"
                      style="width:36px;height:36px;">
                      <i class="bi bi-send-fill small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Submit</small>
                    <small class="fw-semibold d-block" style="font-size:0.65rem;">{{ pm.requester?.name }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Chief Mekanik -->
                  <div class="text-center" style="min-width:60px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="stepClass(['pending_manager','approved','pending_purchasing','purchasing','partial_ordered','bon_pengeluaran','completed'], pm.status, 'pending_chief')"
                      style="width:36px;height:36px;">
                      <i class="bi bi-person-check-fill small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Chief</small>
                    <small v-if="pm.chiefAuthorizer" class="fw-semibold d-block" style="font-size:0.65rem;">{{ pm.chiefAuthorizer?.name }}</small>
                    <small v-if="pm.chief_authorized_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(pm.chief_authorized_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Manager -->
                  <div class="text-center" style="min-width:60px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="stepClass(['approved','pending_purchasing','purchasing','partial_ordered','bon_pengeluaran','completed'], pm.status, 'pending_manager')"
                      style="width:36px;height:36px;">
                      <i class="bi bi-briefcase-fill small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Manager</small>
                    <small v-if="pm.managerApprover" class="fw-semibold d-block" style="font-size:0.65rem;">{{ pm.managerApprover?.name }}</small>
                    <small v-if="pm.manager_approved_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(pm.manager_approved_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Admin HO -->
                  <div class="text-center" style="min-width:60px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="stepClass(['approved','pending_purchasing','purchasing','partial_ordered','bon_pengeluaran','completed'], pm.status, 'pending_ho')"
                      style="width:36px;height:36px;">
                      <i class="bi bi-building-check small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Admin HO</small>
                    <small v-if="pm.hoApprover" class="fw-semibold d-block" style="font-size:0.65rem;">{{ pm.hoApprover?.name }}</small>
                    <small v-if="pm.ho_approved_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(pm.ho_approved_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Purchasing (step baru) -->
                  <div class="text-center" style="min-width:60px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="stepClass(['purchasing','partial_ordered','bon_pengeluaran','completed'], pm.status, 'pending_purchasing')"
                      style="width:36px;height:36px;">
                      <i class="bi bi-cart-check-fill small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Purchasing</small>
                    <small v-if="pm.poSubmitter" class="fw-semibold d-block" style="font-size:0.65rem;">{{ pm.poSubmitter?.name }}</small>
                    <small v-if="pm.po_submitted_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(pm.po_submitted_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Selesai -->
                  <div class="text-center" style="min-width:60px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="['purchasing','partial_ordered','bon_pengeluaran','completed'].includes(pm.status) ? 'bg-success text-white' : 'bg-light text-muted border'"
                      style="width:36px;height:36px;">
                      <i class="bi bi-check2-all small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Selesai</small>
                    <small class="text-muted d-block" style="font-size:0.65rem;">Bon / PO</small>
                  </div>
                </div>
              </template>

              <!-- ===== ALUR MR OFFICE ===== -->
              <template v-else>
                <div class="d-flex align-items-start">

                  <!-- Submit -->
                  <div class="text-center" style="min-width:80px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="pm.status !== 'draft' ? 'bg-success text-white' : 'bg-secondary text-white'"
                      style="width:36px;height:36px;">
                      <i class="bi bi-send-fill small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Submit</small>
                    <small class="fw-semibold d-block" style="font-size:0.65rem;">{{ pm.requester?.name }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Admin HO -->
                  <div class="text-center" style="min-width:80px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="stepClass(['approved','pending_purchasing','purchasing','partial_ordered','bon_pengeluaran','completed'], pm.status, 'pending_ho')"
                      style="width:36px;height:36px;">
                      <i class="bi bi-building-check small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Admin HO</small>
                    <small v-if="pm.hoApprover" class="fw-semibold d-block" style="font-size:0.65rem;">{{ pm.hoApprover?.name }}</small>
                    <small v-if="pm.ho_approved_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(pm.ho_approved_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Purchasing -->
                  <div class="text-center" style="min-width:80px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="stepClass(['purchasing','partial_ordered','bon_pengeluaran','completed'], pm.status, 'pending_purchasing')"
                      style="width:36px;height:36px;">
                      <i class="bi bi-cart-check-fill small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Purchasing</small>
                    <small v-if="pm.poSubmitter" class="fw-semibold d-block" style="font-size:0.65rem;">{{ pm.poSubmitter?.name }}</small>
                    <small v-if="pm.po_submitted_at" class="text-muted d-block" style="font-size:0.6rem;">{{ $formatDate(pm.po_submitted_at) }}</small>
                  </div>

                  <div class="flex-grow-1 border-top mt-4" style="border-style:dashed!important;"></div>

                  <!-- Selesai -->
                  <div class="text-center" style="min-width:80px;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                      :class="['purchasing','partial_ordered','bon_pengeluaran','completed'].includes(pm.status) ? 'bg-success text-white' : 'bg-light text-muted border'"
                      style="width:36px;height:36px;">
                      <i class="bi bi-check2-all small"></i>
                    </div>
                    <small class="text-muted d-block" style="font-size:0.7rem;">Selesai</small>
                    <small class="text-muted d-block" style="font-size:0.65rem;">Bon / SJ</small>
                  </div>
                </div>
              </template>

              <!-- Rejection reason -->
              <div v-if="pm.status === 'rejected'" class="alert alert-danger mt-3 small py-2">
                <i class="bi bi-x-circle me-1"></i>
                <strong>Ditolak:</strong> {{ pm.rejection_reason }}
              </div>

              <!-- Info setelah Admin HO approve — menunggu diajukan ke Purchasing -->
              <div v-if="pm.status === 'approved'" class="alert alert-warning mt-3 small py-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Menunggu tindak lanjut Admin HO:</strong>
                Klik <strong>"Ajukan PO ke Purchasing"</strong> untuk meneruskan PM ini ke antrian Purchasing,
                atau <strong>"Buat Bon Pengeluaran"</strong> jika stok tersedia di gudang.
              </div>

              <!-- Info setelah diajukan ke Purchasing -->
              <div v-if="pm.status === 'pending_purchasing'" class="alert mt-3 small py-2" style="background:#f3f0ff;border-color:#7c3aed;color:#4c1d95;">
                <i class="bi bi-cart-check me-1"></i>
                <strong>Sudah diajukan ke Purchasing</strong> oleh {{ pm.poSubmitter?.name || 'Admin HO' }}
                pada {{ $formatDate(pm.po_submitted_at) }}.
                Menunggu Purchasing membuat Purchase Order.
              </div>
            </div>
          </div>
        </div>

        <!-- Daftar Barang -->
        <div class="col-12">
          <div class="csm-card">
            <div class="csm-card-header"><h6><i class="bi bi-list-check me-2"></i>Daftar Barang Diminta</h6></div>
            <div class="csm-card-body p-0">
              <div class="table-responsive">
                <table class="table csm-table mb-0">
                  <thead>
                    <tr>
                      <th width="5%">#</th>
                      <th v-if="pm.type === 'part'">Part Number</th>
                      <th>Nama Barang / Deskripsi</th>
                      <th v-if="pm.type === 'part'">Kode Unit</th>
                      <th v-if="pm.type === 'part'">Tipe Unit</th>
                      <th class="text-end">Jumlah</th>
                      <th>Satuan</th>
                      <th>Keterangan</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, idx) in pm.items" :key="item.id">
                      <td class="text-muted small">{{ idx + 1 }}</td>
                      <td v-if="pm.type === 'part'">
                        <code v-if="item.part_number || item.item?.part_number" class="small text-primary fw-semibold">
                          {{ item.part_number || item.item?.part_number }}
                        </code>
                        <span v-else class="text-muted small">-</span>
                      </td>
                      <td class="fw-semibold">{{ item.nama_barang }}</td>
                      <td v-if="pm.type === 'part'"><code class="small text-secondary">{{ item.kode_unit || '-' }}</code></td>
                      <td v-if="pm.type === 'part'"><small>{{ item.tipe_unit || '-' }}</small></td>
                      <td class="text-end fw-bold">{{ item.qty }}</td>
                      <td><span class="badge bg-light text-dark border">{{ item.satuan }}</span></td>
                      <td><small class="text-muted">{{ item.keterangan || '-' }}</small></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- PO yang sudah dibuat -->
        <div v-if="pm.purchase_orders?.length" class="col-12">
          <div class="csm-card border-primary">
            <div class="csm-card-header bg-primary bg-opacity-10 d-flex align-items-center justify-content-between">
              <h6 class="text-primary mb-0"><i class="bi bi-file-earmark-text me-2"></i>Purchase Order</h6>
              <span v-if="canStillOrder" class="badge bg-warning text-dark">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ unorderedItems.length }} item belum masuk PO — bisa buat PO lanjutan
              </span>
            </div>
            <div class="csm-card-body p-0">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>No. PO</th>
                    <th>Vendor</th>
                    <th>Item dalam PO ini</th>
                    <th>Status</th>
                    <th>Dibuat Oleh</th>
                    <th>Tanggal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="po in pm.purchase_orders" :key="po.id">
                    <td class="fw-semibold text-primary" style="white-space:nowrap">{{ po.po_number }}</td>
                    <td><small>{{ po.vendor_name || '-' }}</small></td>
                    <td>
                      <div class="d-flex flex-wrap gap-1">
                        <span v-for="item in (po.items||[])" :key="item.id"
                          class="badge bg-light text-dark border" style="font-size:0.7rem;">
                          {{ item.nama_barang }}
                          <span class="text-muted">({{ item.qty }} {{ item.satuan }})</span>
                        </span>
                      </div>
                    </td>
                    <td><span class="badge" :class="poStatusClass(po.status)">{{ poStatusLabel(po.status) }}</span></td>
                    <td><small>{{ po.creator?.name }}</small></td>
                    <td><small class="text-muted" style="white-space:nowrap">{{ $formatDate(po.created_at) }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Item belum masuk PO -->
        <div v-if="unorderedItems.length" class="col-12">
          <div class="csm-card" style="border:2px solid #f59e0b;">
            <div class="csm-card-header" style="background:#fffbeb;">
              <h6 class="mb-0" style="color:#d97706;">
                <i class="bi bi-hourglass-split me-2"></i>
                Item Belum Masuk PO ({{ unorderedItems.length }} item)
              </h6>
            </div>
            <div class="csm-card-body p-0">
              <table class="table table-sm mb-0">
                <thead>
                  <tr>
                    <th>Part Number</th>
                    <th>Nama Barang</th>
                    <th class="text-center">Qty PM</th>
                    <th class="text-center">Sudah di-PO</th>
                    <th class="text-center">Sisa</th>
                    <th>Satuan</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in unorderedItems" :key="item.id" class="table-warning">
                    <td>
                      <code v-if="item.part_number" class="small text-primary fw-semibold">{{ item.part_number }}</code>
                      <span v-else class="text-muted small">-</span>
                    </td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td class="text-center small text-muted">{{ item.qty_pm }}</td>
                    <td class="text-center">
                      <span v-if="item.qty_ordered > 0" class="badge bg-success">{{ item.qty_ordered }}</span>
                      <span v-else class="text-muted small">0</span>
                    </td>
                    <td class="text-center">
                      <span class="badge fw-bold" style="background:#f59e0b;color:#fff;">{{ item.qty_remaining }}</span>
                    </td>
                    <td><small>{{ item.satuan }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Bon Pengeluaran yang sudah dibuat -->
        <div v-if="pm.bon_pengeluaran?.length" class="col-12">
          <div class="csm-card border-success">
            <div class="csm-card-header bg-success bg-opacity-10">
              <h6 class="text-success"><i class="bi bi-box-arrow-right me-2"></i>Bon Pengeluaran</h6>
            </div>
            <div class="csm-card-body p-0">
              <table class="table table-sm mb-0">
                <thead><tr><th>No. Bon</th><th>Gudang</th><th>Status</th><th>Diterima Oleh / Mekanik</th><th>Tanggal</th></tr></thead>
                <tbody>
                  <tr v-for="bon in pm.bon_pengeluaran" :key="bon.id">
                    <td class="fw-semibold text-success">{{ bon.bon_number }}</td>
                    <td><small>{{ bon.warehouse?.name }}</small></td>
                    <td><span class="badge" :class="bon.status === 'issued' ? 'bg-success' : bon.status === 'approved' ? 'bg-primary' : 'bg-secondary'">
                      {{ bon.status === 'issued' ? 'Sudah Dikeluarkan' : bon.status === 'approved' ? 'Disetujui' : 'Draft' }}
                    </span></td>
                    <td><small>{{ bon.received_by || '-' }}</small></td>
                    <td><small class="text-muted">{{ $formatDate(bon.created_at) }}</small></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="col-12">
          <div class="csm-card">
            <div class="csm-card-body">
              <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="small text-muted me-1">Aksi:</span>

                <!-- Submit draft -->
                <button v-if="pm.status === 'draft' && can('create-pm')"
                  class="btn btn-info btn-sm" @click="doSubmit" :disabled="acting">
                  <i class="bi bi-send me-1"></i>
                  {{ pm.type === 'part' ? 'Submit ke Chief Mekanik' : 'Submit ke Admin HO' }}
                </button>

                <!-- Chief Mekanik otorisasi -->
                <button v-if="pm.status === 'pending_chief' && can('authorize-mr-chief')"
                  class="btn btn-warning btn-sm" @click="doAuthorizeChief" :disabled="acting">
                  <i class="bi bi-person-check me-1"></i>Otorisasi (Chief Mekanik)
                </button>

                <!-- Manager approve -->
                <button v-if="pm.status === 'pending_manager' && can('approve-mr-manager')"
                  class="btn btn-success btn-sm" @click="doApproveManager" :disabled="acting">
                  <i class="bi bi-check-circle me-1"></i>Setujui (Manager)
                </button>

                <!-- Admin HO approve -->
                <button v-if="pm.status === 'pending_ho' && can('approve-pm-ho')"
                  class="btn btn-success btn-sm" @click="doApproveHO" :disabled="acting">
                  <i class="bi bi-check-all me-1"></i>Setujui (Admin HO)
                </button>

                <!-- ★ TOMBOL BARU: Admin HO ajukan PO ke Purchasing -->
                <!-- Muncul setelah Admin HO approve, sebelum diajukan ke Purchasing -->
                <button
                  v-if="pm.status === 'approved' && isAdminHOorSuperuser"
                  class="btn btn-sm fw-semibold"
                  style="background:#7c3aed;color:#fff;border:none;"
                  @click="doSubmitPurchasing"
                  :disabled="acting">
                  <i class="bi bi-cart-check me-1"></i>Ajukan PO ke Purchasing
                  <span class="badge ms-1" style="background:rgba(255,255,255,0.25);font-size:0.65rem;">Outstanding</span>
                </button>

                <!-- Tolak -->
                <button v-if="canReject"
                  class="btn btn-danger btn-sm" @click="openRejectModal" :disabled="acting">
                  <i class="bi bi-x-circle me-1"></i>Tolak
                </button>

                <!-- ── TINDAK LANJUT: Bon Pengeluaran (stok ada) ── -->
                <!-- Hanya tersedia saat approved (belum diajukan ke Purchasing) -->
                <template v-if="pm.status === 'approved' && can('create-bon')">
                  <div class="vr mx-1"></div>
                  <button class="btn btn-success btn-sm" @click="openBonModal" :disabled="acting">
                    <i class="bi bi-box-arrow-right me-1"></i>Buat Bon Pengeluaran
                    <span class="badge bg-white text-success ms-1" style="font-size:0.65rem;">Stok Ada</span>
                  </button>
                </template>

                <!-- ── TINDAK LANJUT: Buat PO (hanya Purchasing, saat pending_purchasing) ── -->
                <template v-if="pm.status === 'pending_purchasing' && can('create-po')">
                  <div class="vr mx-1"></div>
                  <button class="btn btn-primary btn-sm" @click="openPOModal" :disabled="acting">
                    <i class="bi bi-file-earmark-plus me-1"></i>Buat Purchase Order
                    <span class="badge bg-white text-primary ms-1" style="font-size:0.65rem;">Stok Kosong</span>
                  </button>
                </template>

                <!-- Buat PO Lanjutan — saat partial_ordered dan ada item sisa -->
                <template v-if="canStillOrder && can('create-po')">
                  <div class="vr mx-1"></div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark">
                      <i class="bi bi-exclamation-triangle me-1"></i>{{ unorderedItems.length }} item belum di-PO
                    </span>
                    <button class="btn btn-primary btn-sm" @click="openPOModal" :disabled="acting">
                      <i class="bi bi-file-earmark-plus me-1"></i>Buat PO Lanjutan
                    </button>
                  </div>
                </template>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Tolak ===== -->
    <div class="modal fade" id="modalRejectDetail" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Tolak Permintaan</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label small fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
            <textarea v-model="rejectReason" class="form-control" rows="3" placeholder="Jelaskan alasan penolakan..."></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger btn-sm" @click="doReject" :disabled="acting || !rejectReason">
              <span v-if="acting" class="csm-spinner me-1"></span>Tolak
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Buat Bon Pengeluaran ===== -->
    <div class="modal fade" id="modalBon" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title text-success"><i class="bi bi-box-arrow-right me-2"></i>Buat Bon Pengeluaran</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-success small py-2 mb-3">
              <i class="bi bi-info-circle me-1"></i>
              Bon Pengeluaran dibuat jika barang <strong>tersedia di stok gudang</strong>. Stok akan otomatis dikurangi saat bon dikeluarkan.
            </div>
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Gudang Sumber Stok <span class="text-danger">*</span></label>
                <select v-model="bonForm.warehouse_id" class="form-select form-select-sm">
                  <option value="">-- Pilih Gudang --</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Tanggal Pengeluaran <span class="text-danger">*</span></label>
                <input v-model="bonForm.issue_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Diterima Oleh / Mekanik <span class="text-danger">*</span></label>
                <input v-model="bonForm.received_by" type="text" class="form-control form-control-sm" placeholder="Nama penerima / mekanik" />
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Catatan</label>
                <input v-model="bonForm.notes" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm csm-table">
                <thead>
                  <tr>
                    <th v-if="pm?.type === 'part'">Part Number</th>
                    <th>Nama Barang</th>
                    <th v-if="pm?.type === 'part'">Kode Unit</th>
                    <th class="text-end">Qty</th>
                    <th>Satuan</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in pm?.items" :key="item.id">
                    <td v-if="pm?.type === 'part'">
                      <code v-if="item.part_number || item.item?.part_number" class="small text-primary fw-semibold">
                        {{ item.part_number || item.item?.part_number }}
                      </code>
                      <span v-else class="text-muted small">-</span>
                    </td>
                    <td class="fw-semibold">{{ item.nama_barang }}</td>
                    <td v-if="pm?.type === 'part'"><code class="small text-secondary">{{ item.kode_unit || '-' }}</code></td>
                    <td class="text-end">{{ item.qty }}</td>
                    <td>{{ item.satuan }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm" @click="saveBon" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-box-arrow-right me-1"></i>Buat Bon Pengeluaran
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Buat Purchase Order ===== -->
    <div class="modal fade" id="modalPO" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header" style="background:#1a3a5c;">
            <h6 class="modal-title text-white"><i class="bi bi-file-earmark-plus me-2"></i>Buat Purchase Order</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">

            <div class="alert alert-primary small py-2 mb-3 d-flex align-items-center gap-2">
              <i class="bi bi-info-circle-fill"></i>
              <span>PO ini dibuat dari <strong>{{ pm?.nomor }}</strong>. Pilih item yang akan dimasukkan ke PO ini — boleh sebagian (partial) jika barang harus dibeli dari vendor berbeda atau waktu berbeda.</span>
            </div>

            <div v-if="pm?.purchase_orders?.length || pm?.purchaseOrders?.length" class="mb-3">
              <div class="small fw-semibold text-muted mb-1"><i class="bi bi-cart-check me-1"></i>PO yang sudah ada untuk PM ini:</div>
              <div class="d-flex flex-wrap gap-2">
                <span v-for="existingPO in (pm.purchase_orders || pm.purchaseOrders || [])" :key="existingPO.id"
                  class="badge bg-light text-dark border small">
                  {{ existingPO.po_number }} — {{ existingPO.vendor_name }}
                  <span class="badge ms-1" :class="existingPO.status==='draft'?'bg-secondary':existingPO.status==='sent_to_vendor'?'bg-info text-dark':'bg-success'">
                    {{ existingPO.status==='draft'?'Draft':existingPO.status==='sent_to_vendor'?'Dikirim':'Selesai' }}
                  </span>
                </span>
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-md-5">
                <label class="form-label small fw-semibold">Vendor / Supplier <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input
                    v-model="supplierSearch"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Cari atau ketik nama vendor/supplier..."
                    @input="onSupplierSearch"
                    @focus="showSupplierDropdown = true"
                    @blur="onSupplierBlur"
                    autocomplete="off"
                  />
                  <div
                    v-if="showSupplierDropdown && filteredSuppliers.length"
                    class="position-absolute w-100 bg-white border rounded shadow-sm"
                    style="z-index:9999; max-height:200px; overflow-y:auto; top:100%;"
                  >
                    <div
                      v-for="s in filteredSuppliers"
                      :key="s.id"
                      class="px-3 py-2 small cursor-pointer hover-bg"
                      style="cursor:pointer;"
                      @mousedown.prevent="selectSupplier(s)"
                    >
                      <div class="fw-semibold">{{ s.name }}</div>
                      <div class="text-muted" style="font-size:0.75rem;">
                        {{ s.phone || s.email || 'Tidak ada kontak' }}
                      </div>
                    </div>
                  </div>
                  <div
                    v-if="showSupplierDropdown && supplierSearch.length >= 1 && !filteredSuppliers.length"
                    class="position-absolute w-100 bg-white border rounded shadow-sm px-3 py-2 small text-muted"
                    style="z-index:9999; top:100%;"
                  >
                    Tidak ada supplier ditemukan — input akan disimpan sebagai nama vendor
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Kontak Vendor</label>
                <input v-model="poForm.vendor_contact" type="text" class="form-control form-control-sm" placeholder="No. telp / email vendor" />
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-semibold">Estimasi Tiba</label>
                <input v-model="poForm.expected_date" type="date" class="form-control form-control-sm" />
              </div>
              <div class="col-md-5">
                <label class="form-label small fw-semibold">Gudang Tujuan</label>
                <input type="text" class="form-control form-control-sm" :value="pm?.warehouse?.name || '-'" disabled />
              </div>
              <div class="col-md-7">
                <label class="form-label small fw-semibold">Catatan</label>
                <input v-model="poForm.notes" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label small fw-semibold mb-0">Pilih Item & Harga</label>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-xs btn-outline-primary" @click="selectAllItems">
                  <i class="bi bi-check-all me-1"></i>Pilih Semua
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary" @click="deselectAllItems">
                  <i class="bi bi-x-lg me-1"></i>Hapus Semua
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm csm-table mb-0">
                <thead>
                  <tr>
                    <th style="width:36px" class="text-center">
                      <input type="checkbox" class="form-check-input"
                        :checked="poForm.items.every(i=>i.selected)"
                        @change="e => poForm.items.forEach(i => i.selected = e.target.checked)" />
                    </th>
                    <th style="width:90px">Part No.</th>
                    <th>Nama Barang</th>
                    <th class="text-center" style="width:90px">Qty</th>
                    <th class="text-end" style="width:130px">Harga Satuan</th>
                    <th class="text-end" style="width:110px">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in poForm.items" :key="idx"
                    :class="item.selected ? '' : 'table-light text-muted'">
                    <td class="text-center">
                      <input type="checkbox" class="form-check-input" v-model="item.selected" />
                    </td>
                    <td>
                      <code v-if="item.part_number" class="small text-primary fw-semibold">{{ item.part_number }}</code>
                      <span v-else class="text-muted small">-</span>
                    </td>
                    <td>
                      <div class="fw-semibold small">{{ item.nama_barang }}</div>
                      <small class="text-muted">{{ item.satuan }} · PM: {{ item.qty_pm }}</small>
                    </td>
                    <td>
                      <input v-model="item.qty" type="number"
                        class="form-control form-control-sm text-center"
                        :class="item.selected ? '' : 'bg-light'"
                        :disabled="!item.selected"
                        min="0.01" :max="item.qty_pm - item.qty_already_ordered" step="0.01" />
                    </td>
                    <td>
                      <input v-model="item.harga_satuan" type="number"
                        class="form-control form-control-sm text-end"
                        :class="item.selected ? '' : 'bg-light'"
                        :disabled="!item.selected"
                        min="0" step="1000" placeholder="0" />
                    </td>
                    <td class="text-end small fw-semibold">
                      <span v-if="item.selected">Rp {{ $formatNumber(hitungSubtotalItem(item)) }}</span>
                      <span v-else class="text-muted">-</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Ringkasan Total -->
            <div class="border rounded p-3 mt-3" style="background:#f8f9fa;">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold">Subtotal (<span class="text-primary">{{ poForm.items.filter(i=>i.selected).length }}</span> item dipilih)</span>
                <span class="small">Rp {{ $formatNumber(subtotalSebelumDiskon) }}</span>
              </div>

              <div class="d-flex align-items-center gap-3 mb-2">
                <span class="small fw-semibold text-danger"><i class="bi bi-tag me-1"></i>Diskon</span>
                <div class="d-flex align-items-center gap-2 flex-grow-1">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="toggleDiskon"
                      :checked="poForm.diskon_persen > 0"
                      @change="poForm.diskon_persen = $event.target.checked ? 10 : 0" />
                    <label class="form-check-label small" for="toggleDiskon">
                      {{ poForm.diskon_persen > 0 ? 'Ada Diskon' : 'Tidak ada diskon' }}
                    </label>
                  </div>
                  <div v-if="poForm.diskon_persen > 0" class="input-group input-group-sm" style="max-width:110px;">
                    <input v-model="poForm.diskon_persen" type="number" class="form-control form-control-sm" min="0" max="100" step="0.5" />
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <span v-if="poForm.diskon_persen > 0" class="small fw-semibold text-danger">- Rp {{ $formatNumber(totalDiskonPO) }}</span>
                <span v-else class="small text-muted">Rp 0</span>
              </div>

              <div class="d-flex align-items-center gap-3 mb-2">
                <span class="small fw-semibold"><i class="bi bi-percent me-1"></i>PPN</span>
                <div class="d-flex align-items-center gap-2 flex-grow-1">
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="togglePPN"
                      :checked="poForm.ppn_percent > 0"
                      @change="poForm.ppn_percent = $event.target.checked ? 11 : 0" />
                    <label class="form-check-label small" for="togglePPN">
                      {{ poForm.ppn_percent > 0 ? 'Kena PPN' : 'Tidak kena PPN' }}
                    </label>
                  </div>
                  <div v-if="poForm.ppn_percent > 0" class="input-group input-group-sm" style="max-width:110px;">
                    <input v-model="poForm.ppn_percent" type="number" class="form-control form-control-sm" min="0" max="100" step="1" />
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <span v-if="poForm.ppn_percent > 0" class="small fw-semibold text-success">+ Rp {{ $formatNumber(ppnAmountPO) }}</span>
                <span v-else class="small text-muted">Rp 0</span>
              </div>

              <div class="d-flex align-items-center justify-content-between border-top pt-2">
                <span class="fw-bold">Grand Total</span>
                <span class="text-primary fw-bold fs-6">Rp {{ $formatNumber(totalPO) }}</span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-primary btn-sm" @click="savePO" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-file-earmark-check me-1"></i>Buat Purchase Order
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/store/auth'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const { listenPM, stopPM } = useRealtime()
const can = (p) => auth.hasPermission(p)

// Cek apakah user adalah Admin HO atau Superuser (untuk tombol Ajukan PO)
// Gunakan computed dari auth store: is_superuser / is_admin_ho
const isAdminHOorSuperuser = computed(() =>
  auth.isSuperuser || auth.isAdminHO
)

const pm = ref(null)
const warehouses = ref([])
const loading = ref(true)
const acting = ref(false)
const saving = ref(false)
const rejectReason = ref('')

const bonForm = ref({ warehouse_id: '', issue_date: '', received_by: '', notes: '' })
const poForm = ref({ vendor_name: '', vendor_contact: '', warehouse_id: '', expected_date: '', notes: '', diskon_persen: 0, ppn_percent: 0, items: [] })

const suppliers = ref([])
const supplierSearch = ref('')
const showSupplierDropdown = ref(false)

const filteredSuppliers = computed(() => {
  if (!supplierSearch.value) return suppliers.value.slice(0, 10)
  const q = supplierSearch.value.toLowerCase()
  return suppliers.value.filter(s =>
    s.name.toLowerCase().includes(q) ||
    (s.phone && s.phone.includes(q)) ||
    (s.email && s.email.toLowerCase().includes(q))
  ).slice(0, 10)
})

function onSupplierSearch() {
  poForm.value.vendor_name = supplierSearch.value
  showSupplierDropdown.value = true
}

function selectSupplier(s) {
  supplierSearch.value = s.name
  poForm.value.vendor_name = s.name
  poForm.value.vendor_contact = s.phone || s.email || ''
  showSupplierDropdown.value = false
}

function onSupplierBlur() {
  setTimeout(() => { showSupplierDropdown.value = false }, 150)
}

function hitungSubtotalItem(item) {
  return (parseFloat(item.harga_satuan) || 0) * (parseFloat(item.qty) || 0)
}

const subtotalSebelumDiskon = computed(() =>
  poForm.value.items
    .filter(i => i.selected)
    .reduce((sum, i) => sum + (parseFloat(i.harga_satuan)||0) * (parseFloat(i.qty)||0), 0)
)
const totalDiskonPO = computed(() =>
  Math.round(subtotalSebelumDiskon.value * (parseFloat(poForm.value.diskon_persen) || 0) / 100)
)
const subtotalPO = computed(() => subtotalSebelumDiskon.value - totalDiskonPO.value)
const ppnAmountPO = computed(() => Math.round(subtotalPO.value * (parseFloat(poForm.value.ppn_percent) || 0) / 100))
const totalPO = computed(() => subtotalPO.value + ppnAmountPO.value)

const statusLabel = (s) => ({
  draft: 'Draft',
  pending_chief: 'Menunggu Chief Mekanik',
  pending_manager: 'Menunggu Manager',
  pending_ho: 'Menunggu Admin HO',
  approved: 'Disetujui HO',
  pending_purchasing: 'Menunggu PO — Outstanding',
  purchasing: 'Proses Purchasing',
  partial_ordered: 'Sebagian PO',
  bon_pengeluaran: 'Bon Pengeluaran',
  completed: 'Selesai',
  rejected: 'Ditolak',
}[s] || s)

const statusClass = (s) => ({
  draft: 'bg-secondary',
  pending_chief: 'bg-warning text-dark',
  pending_manager: 'bg-warning text-dark',
  pending_ho: 'bg-info text-dark',
  approved: 'bg-primary',
  // pending_purchasing pakai warna ungu agar mudah dibedakan
  pending_purchasing: 'text-white',
  purchasing: 'bg-info text-dark',
  partial_ordered: 'bg-warning text-dark',
  bon_pengeluaran: 'bg-info text-dark',
  completed: 'bg-success',
  rejected: 'bg-danger',
}[s] || 'bg-secondary') + (s === 'pending_purchasing' ? ' ' : '')

// Computed khusus untuk badge pending_purchasing pakai style inline
const statusStyle = (s) => s === 'pending_purchasing' ? 'background:#7c3aed;' : ''

function stepClass(doneStatuses, current, activeStatus) {
  if (doneStatuses.includes(current)) return 'bg-success text-white'
  if (current === activeStatus) return 'bg-warning text-dark'
  if (current === 'rejected') return 'bg-danger text-white'
  return 'bg-light text-muted border'
}

const poStatusLabel = (s) => ({ draft: 'Draft', sent_to_vendor: 'Dikirim ke Vendor', completed: 'Selesai', cancelled: 'Dibatalkan' }[s] || s)
const poStatusClass = (s) => ({ draft: 'bg-secondary', sent_to_vendor: 'bg-info text-dark', completed: 'bg-success', cancelled: 'bg-danger' }[s] || 'bg-secondary')

const unorderedItems = computed(() => {
  if (!pm.value?.items) return []
  const allPOs = pm.value.purchase_orders || []
  return pm.value.items
    .map(pmItem => {
      const qtyOrdered = allPOs.reduce((sum, po) =>
        sum + (po.items || [])
          .filter(poi => poi.permintaan_material_item_id === pmItem.id)
          .reduce((s, poi) => s + parseFloat(poi.qty || 0), 0), 0)
      const qtyPm = parseFloat(pmItem.qty || 0)
      return {
        ...pmItem,
        qty_pm:        qtyPm,
        qty_ordered:   qtyOrdered,
        qty_remaining: Math.max(0, qtyPm - qtyOrdered),
      }
    })
    .filter(i => i.qty_remaining > 0)
})

// Tombol "Buat PO Lanjutan" muncul saat partial_ordered dan ada sisa item
const canStillOrder = computed(() => {
  if (!pm.value) return false
  if (!['partial_ordered', 'purchasing'].includes(pm.value.status)) return false
  if (!pm.value.purchase_orders?.length) return false
  return unorderedItems.value.length > 0
})

const canReject = computed(() => {
  if (!pm.value) return false
  const s = pm.value.status
  if (s === 'pending_chief' && can('authorize-mr-chief')) return true
  if (s === 'pending_manager' && can('approve-mr-manager')) return true
  if (s === 'pending_ho' && can('approve-pm-ho')) return true
  return false
})

onMounted(async () => {
  // loadPM() dijalankan lebih dulu — tidak menunggu warehouses/suppliers
  loadPM()
  listenPM(() => loadPM())

  // Load warehouses & suppliers paralel, error tidak akan block tampilan PM
  try {
    const [resW, resS] = await Promise.all([
      axios.get('/warehouses'),
      axios.get('/suppliers'),
    ])
    warehouses.value = resW.data.data
    suppliers.value = resS.data.data || []
  } catch (e) {
    console.warn('Gagal load warehouses/suppliers:', e)
  }
})
onUnmounted(() => stopPM())

async function loadPM() {
  loading.value = true
  try {
    const res = await axios.get(`/permintaan-material/${route.params.id}`)
    pm.value = res.data.data
  } catch { toast.error('Data tidak ditemukan') } finally {
    loading.value = false
    window.clearModalBackdrop()
  }
}

async function doSubmit() {
  const msg = pm.value.type === 'part' ? 'Submit ke Chief Mekanik?' : 'Submit ke Admin HO?'
  if (!confirm(msg)) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.value.id}/submit`)
    toast.success('Berhasil disubmit')
    loadPM()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doAuthorizeChief() {
  if (!confirm('Otorisasi MR ini sebagai Chief Mekanik?')) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.value.id}/authorize-chief`)
    toast.success('Diotorisasi, diteruskan ke Manager')
    loadPM()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doApproveManager() {
  if (!confirm('Setujui MR ini sebagai Manager?')) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.value.id}/approve-manager`)
    toast.success('Disetujui Manager, diteruskan ke Admin HO')
    loadPM()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

async function doApproveHO() {
  if (!confirm('Setujui final MR ini sebagai Admin HO?')) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.value.id}/approve-ho`)
    toast.success('MR disetujui! Silakan klik "Ajukan PO ke Purchasing" untuk meneruskan ke antrian Purchasing.')
    loadPM()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

// ★ FUNGSI BARU: Ajukan PO ke Purchasing
async function doSubmitPurchasing() {
  if (!confirm(`Ajukan PM ${pm.value.nomor} ke Purchasing?\n\nSetelah ini, status PM akan menjadi "Menunggu PO — Outstanding" dan Purchasing bisa melihat PM ini di antrian mereka.`)) return
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.value.id}/submit-purchasing`)
    toast.success(`PM ${pm.value.nomor} berhasil diajukan ke Purchasing!`)
    loadPM()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal mengajukan PO') } finally { acting.value = false }
}

function openRejectModal() {
  rejectReason.value = ''
  new Modal('#modalRejectDetail').show()
}

async function doReject() {
  acting.value = true
  try {
    await axios.post(`/permintaan-material/${pm.value.id}/reject`, { reason: rejectReason.value })
    toast.success('Permintaan ditolak')
    Modal.getInstance('#modalRejectDetail')?.hide()
    loadPM()
  } catch (e) { toast.error(e.response?.data?.message || 'Gagal') } finally { acting.value = false }
}

function openBonModal() {
  bonForm.value = {
    warehouse_id: pm.value.warehouse?.id || '',
    issue_date: new Date().toISOString().split('T')[0],
    received_by: '',
    notes: '',
  }
  new Modal('#modalBon').show()
}

async function saveBon() {
  if (!bonForm.value.warehouse_id) return toast.error('Pilih gudang sumber stok')
  if (!bonForm.value.received_by) return toast.error('Isi nama penerima barang')
  if (!bonForm.value.issue_date) return toast.error('Isi tanggal pengeluaran')
  saving.value = true
  try {
    let stockMap = {}
    try {
      const stockRes = await axios.get(`/warehouses/${bonForm.value.warehouse_id}/stocks`, { params: { per_page: 999 } })
      ;(stockRes.data.data || []).forEach(s => {
        if (s.item?.name) stockMap[s.item.name.toLowerCase()] = s.item.id
      })
    } catch {}

    const items = pm.value.items.map(i => ({
      nama_barang: i.nama_barang,
      item_id: stockMap[i.nama_barang?.toLowerCase()] || null,
      kode_unit: i.kode_unit,
      tipe_unit: i.tipe_unit,
      qty: i.qty,
      satuan: i.satuan,
      keterangan: i.keterangan,
    }))
    await axios.post('/bon-pengeluaran', {
      permintaan_material_id: pm.value.id,
      warehouse_id: bonForm.value.warehouse_id,
      received_by: bonForm.value.received_by,
      issue_date: bonForm.value.issue_date,
      notes: bonForm.value.notes,
      unit_code: pm.value.items?.[0]?.kode_unit || null,
      unit_type: pm.value.items?.[0]?.tipe_unit || null,
      auto_issue: true,
      items,
    })
    toast.success('Bon Pengeluaran berhasil dibuat')
    Modal.getInstance('#modalBon')?.hide()
    loadPM()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal membuat Bon Pengeluaran')
  } finally { saving.value = false }
}

async function openPOModal() {
  let freshPM
  try {
    const res = await axios.get(`/permintaan-material/${pm.value.id}`)
    freshPM = res.data.data
  } catch {
    toast.error('Gagal memuat data PM')
    return
  }

  const qtyOrderedMap = {}
  for (const existingPO of (freshPM.purchase_orders || [])) {
    for (const poItem of (existingPO.items || [])) {
      if (poItem.permintaan_material_item_id) {
        qtyOrderedMap[poItem.permintaan_material_item_id] =
          (qtyOrderedMap[poItem.permintaan_material_item_id] || 0) + parseFloat(poItem.qty || 0)
      }
    }
  }

  const items = freshPM.items
    .map(i => {
      const alreadyOrdered = qtyOrderedMap[i.id] || 0
      const remaining = Math.max(0, parseFloat(i.qty) - alreadyOrdered)
      return {
        selected: true,
        permintaan_material_item_id: i.id,
        item_id: i.item_id ?? null,
        part_number: i.part_number || i.item?.part_number || null,
        nama_barang: i.nama_barang,
        kode_unit: i.kode_unit,
        tipe_unit: i.tipe_unit,
        qty_pm: parseFloat(i.qty),
        qty_already_ordered: alreadyOrdered,
        qty: remaining,
        satuan: i.satuan,
        harga_satuan: 0,
        diskon_persen: 0,
        keterangan: i.keterangan,
      }
    })
    .filter(i => i.qty_already_ordered < i.qty_pm)

  if (!items.some(i => i.qty_already_ordered < i.qty_pm)) {
    toast.warning('Semua item dari PM ini sudah masuk ke PO')
    return
  }

  poForm.value = {
    vendor_name: '',
    vendor_contact: '',
    warehouse_id: pm.value.warehouse?.id || '',
    expected_date: '',
    notes: '',
    diskon_persen: 0,
    ppn_percent: 0,
    items,
  }
  supplierSearch.value = ''
  showSupplierDropdown.value = false
  new Modal('#modalPO').show()
}

function selectAllItems() {
  poForm.value.items.forEach(i => {
    i.selected = true
    if (!i.qty || i.qty <= 0) i.qty = Math.max(0, i.qty_pm - i.qty_already_ordered)
  })
}

function deselectAllItems() {
  poForm.value.items.forEach(i => i.selected = false)
}

async function savePO() {
  if (!poForm.value.vendor_name) return toast.error('Isi nama vendor/supplier')
  const selectedItems = poForm.value.items.filter(i => i.selected)
  if (!selectedItems.length) return toast.error('Pilih minimal satu item')
  saving.value = true
  try {
    await axios.post('/purchase-orders', {
      permintaan_material_ids: [pm.value.id],
      warehouse_id: pm.value.warehouse?.id,
      vendor_name: poForm.value.vendor_name,
      vendor_contact: poForm.value.vendor_contact,
      expected_date: poForm.value.expected_date,
      notes: poForm.value.notes,
      ppn_percent: poForm.value.ppn_percent,
      diskon_persen: poForm.value.diskon_persen,
      items: selectedItems.map(i => ({
        item_id: i.item_id,
        permintaan_material_item_id: i.permintaan_material_item_id,
        qty_pm: i.qty_pm,
        part_number: i.part_number,
        nama_barang: i.nama_barang,
        kode_unit: i.kode_unit,
        tipe_unit: i.tipe_unit,
        qty: i.qty,
        satuan: i.satuan,
        harga_satuan: i.harga_satuan,
        diskon_persen: i.diskon_persen || 0,
        keterangan: i.keterangan,
      })),
    })
    toast.success('Purchase Order berhasil dibuat')
    Modal.getInstance('#modalPO')?.hide()
    loadPM()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal membuat Purchase Order')
  } finally { saving.value = false }
}

// ── Print / Export helpers ───────────────────────────────
function fmtD(val) {
  if (!val) return '-'
  return new Date(val).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
}

function buildHTML(data) {
  const isPart = data.type === 'part'
  const statusMap = {
    draft:'DRAFT', pending_chief:'MENUNGGU CHIEF MEKANIK', pending_manager:'MENUNGGU MANAGER',
    pending_ho:'MENUNGGU ADMIN HO', approved:'DISETUJUI HO',
    pending_purchasing:'OUTSTANDING — MENUNGGU PO',
    purchasing:'PROSES PURCHASING', completed:'SELESAI', rejected:'DITOLAK',
  }
  const statusColor = {
    draft:'#6c757d', pending_chief:'#f59e0b', pending_manager:'#f59e0b',
    pending_ho:'#0ea5e9', approved:'#3b82f6',
    pending_purchasing:'#7c3aed',
    purchasing:'#0ea5e9', completed:'#16a34a', rejected:'#dc2626',
  }
  const sBg  = statusColor[data.status] || '#6c757d'
  const sTxt = statusMap[data.status] || (data.status || '').toUpperCase()

  const partHeaders = isPart
    ? `<th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Part Number</th>
       <th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Kode Unit</th>
       <th style="background:#1a3a5c;color:#fff;padding:8px;text-align:center;border:1px solid #1a3a5c;font-size:9pt;">Tipe Unit</th>` : ''

  const rows = (data.items || []).map((item, i) => {
    const partCells = isPart
      ? `<td style="font-family:Courier New,monospace;color:#1a3a5c;font-weight:600;text-align:center;border:1px solid #dee2e6;">${item.part_number || item.item?.part_number || '-'}</td>
         <td style="text-align:center;border:1px solid #dee2e6;">${item.kode_unit || '-'}</td>
         <td style="text-align:center;border:1px solid #dee2e6;">${item.tipe_unit || '-'}</td>` : ''
    return `<tr style="${i % 2 === 1 ? 'background:#f8fafc;' : ''}">
      <td style="text-align:center;border:1px solid #dee2e6;">${i + 1}</td>
      ${partCells}
      <td style="font-weight:600;border:1px solid #dee2e6;">${item.nama_barang || '-'}</td>
      <td style="text-align:center;border:1px solid #dee2e6;">${item.qty}</td>
      <td style="text-align:center;border:1px solid #dee2e6;">${item.satuan}</td>
      <td style="border:1px solid #dee2e6;color:#64748b;font-size:9.5pt;">${item.keterangan || '-'}</td>
    </tr>`
  }).join('')

  return `<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"/>
<title>${data.nomor}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Plus Jakarta Sans',sans-serif;font-size:11px;color:#1a1a2e;background:#fff;}
  .page{width:210mm;min-height:297mm;margin:0 auto;padding:14mm 16mm;}
  .header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;padding-bottom:14px;border-bottom:3px solid #1a3a5c;}
  .cn{font-size:20px;font-weight:800;color:#1a3a5c;} .cs{font-size:10px;color:#6c757d;margin-top:3px;}
  .dr{text-align:right;} .dl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#6c757d;}
  .dn{font-size:20px;font-weight:800;color:#1a3a5c;}
  .sp{display:inline-block;margin-top:4px;padding:3px 10px;border-radius:20px;font-size:9px;font-weight:700;letter-spacing:1px;color:#fff;background:${sBg};}
  .tp{display:inline-block;margin-top:4px;margin-right:4px;padding:3px 10px;border-radius:20px;font-size:9px;font-weight:700;color:#fff;background:${isPart ? '#2563a8' : '#0891b2'};}
  .ig{display:grid;grid-template-columns:1fr 1fr;gap:0;margin-bottom:18px;border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;}
  .is{padding:11px 14px;} .is:first-child{border-right:1.5px solid #e2e8f0;}
  .it{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;margin-bottom:8px;}
  .ir{display:flex;justify-content:space-between;margin-bottom:4px;font-size:10.5px;}
  .il{color:#64748b;font-weight:500;min-width:100px;} .iv{font-weight:600;color:#1a1a2e;text-align:right;} .hi{color:#1a3a5c;}
  table{width:100%;border-collapse:collapse;font-size:10.5px;}
  thead th{padding:8px;color:#fff;background:#1a3a5c;font-weight:700;font-size:9px;text-transform:uppercase;letter-spacing:0.8px;border:1px solid #1a3a5c;}
  td{padding:7px 8px;vertical-align:middle;}
  .sg{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-top:28px;}
  .sb{border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 12px;}
  .sl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:40px;}
  .sn{border-top:1.5px solid #cbd5e1;padding-top:6px;font-size:10px;font-weight:600;color:#475569;min-height:22px;}
  .nb{margin-top:16px;padding:9px 12px;background:#f8fafc;border-left:3px solid #1a3a5c;border-radius:0 6px 6px 0;font-size:9.5px;color:#64748b;}
  @media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style></head><body>
<div class="page">
  <div class="header">
    <div><div class="cn">PT. Cipta Sarana Makmur</div><div class="cs">CSM Inventory Management System</div></div>
    <div class="dr">
      <div class="dl">${isPart ? 'Material Request Part' : 'Material Request Office'}</div>
      <div class="dn">${data.nomor}</div>
      <div><span class="tp">${isPart ? '🔧 MR Part' : '🏢 MR Office'}</span><span class="sp">${sTxt}</span></div>
    </div>
  </div>
  <div class="ig">
    <div class="is">
      <div class="it">Informasi Permintaan</div>
      <div class="ir"><span class="il">No. PM</span><span class="iv hi">${data.nomor}</span></div>
      <div class="ir"><span class="il">Gudang / Site</span><span class="iv hi">${data.warehouse?.name || '-'}</span></div>
      <div class="ir"><span class="il">Diajukan Oleh</span><span class="iv">${data.requester?.name || '-'}</span></div>
      <div class="ir"><span class="il">Tanggal Dibuat</span><span class="iv">${fmtD(data.created_at)}</span></div>
    </div>
    <div class="is">
      <div class="it">Persetujuan</div>
      <div class="ir"><span class="il">Tgl. Dibutuhkan</span><span class="iv">${data.needed_date ? fmtD(data.needed_date) : '-'}</span></div>
      <div class="ir"><span class="il">Chief Mekanik</span><span class="iv">${data.chiefAuthorizer?.name || (data.chief_authorized_at ? '✓' : '-')}</span></div>
      <div class="ir"><span class="il">Manager</span><span class="iv">${data.managerApprover?.name || (data.manager_approved_at ? '✓' : '-')}</span></div>
      <div class="ir"><span class="il">Admin HO</span><span class="iv">${data.approver?.name || (data.approved_at ? '✓' : '-')}</span></div>
    </div>
  </div>
  <table>
    <thead><tr>
      <th style="width:30px;text-align:center;">#</th>
      ${partHeaders}
      <th>Nama Barang / Deskripsi</th>
      <th style="width:45px;text-align:center;">Qty</th>
      <th style="width:50px;text-align:center;">Satuan</th>
      <th style="width:120px;">Keterangan</th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table>
  ${data.notes ? `<div class="nb"><strong>Catatan:</strong> ${data.notes}</div>` : ''}
  <div class="sg">
    <div class="sb"><div class="sl">Ordered by Logistic</div><div class="sn"></div></div>
    <div class="sb"><div class="sl">Received by Purchasing</div><div class="sn"></div></div>
    <div class="sb"><div class="sl">Authorized by</div><div class="sn">${data.chiefAuthorizer?.name || ''}</div></div>
    <div class="sb"><div class="sl">Approved by</div><div class="sn">${data.approver?.name || data.managerApprover?.name || ''}</div></div>
  </div>
</div>
</body></html>`
}

function printPDF() {
  const html = buildHTML(pm.value)
  const win  = window.open('', '_blank', 'width=900,height=700')
  win.document.write(html)
  win.document.close()
  win.onload = () => { win.focus(); win.print() }
}

function exportExcel() {
  if (!pm.value) return
  const url = `/api/permintaan-material/${pm.value.id}/export-excel`
  toast.info('⏳ Menyiapkan file Excel...')
  fetch(url, {
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('csm_token')}`,
      'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    }
  })
  .then(res => {
    if (!res.ok) throw new Error('Gagal')
    return res.blob()
  })
  .then(blob => {
    const burl = URL.createObjectURL(blob)
    const a = Object.assign(document.createElement('a'), { href: burl, download: `PM-${pm.value.nomor}.xlsx` })
    document.body.appendChild(a); a.click()
    document.body.removeChild(a); URL.revokeObjectURL(burl)
    toast.success(`✅ PM-${pm.value.nomor}.xlsx berhasil diunduh`)
  })
  .catch(() => toast.error('Gagal mengunduh Excel'))
}
</script>