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
        <button v-if="pm" class="btn btn-outline-danger btn-sm" @click="onClickPrint" title="Print / PDF">
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
                <tr><td class="text-muted" width="40%">No. MR</td><td class="fw-semibold">{{ pm.nomor }}</td></tr>
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
            <div class="csm-card-header d-flex align-items-center justify-content-between">
              <h6><i class="bi bi-list-check me-2"></i>Daftar Barang Diminta</h6>
              <div class="d-flex align-items-center gap-2">
                <span v-if="pm.status === 'draft'" class="badge bg-warning text-dark small">
                  <i class="bi bi-pencil me-1"></i>Draft — item bisa diedit / dihapus
                </span>
                <button v-if="pm.status === 'draft' && can('create-pm')"
                  class="btn btn-sm btn-success" @click="openAddItem">
                  <i class="bi bi-plus-lg me-1"></i>Tambah Barang
                </button>
              </div>
            </div>
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
                      <th v-if="pm.status === 'draft'" class="text-center" width="80">Aksi</th>
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
                      <td v-if="pm.status === 'draft'" class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                          <button class="btn btn-xs btn-outline-primary" @click="openEditItem(item)" title="Edit item">
                            <i class="bi bi-pencil"></i>
                          </button>
                          <button class="btn btn-xs btn-outline-danger" @click="doDeleteItem(item)" title="Hapus item"
                            :disabled="pm.items.length <= 1">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
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
                      <code v-if="item.part_number || item.item?.part_number"
                        class="small text-primary fw-semibold">
                        {{ item.part_number || item.item?.part_number }}
                      </code>
                      <span v-else class="badge bg-light text-muted border" style="font-size:.65rem;">Tanpa PN</span>
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

              <!-- ── Jenis Pembayaran ───────────────────────────────────── -->
              <div class="col-12">
                <hr class="my-1" />
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Jenis Pembayaran <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 mt-1">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" id="po-cash" value="cash" v-model="poForm.payment_type" />
                    <label class="form-check-label small" for="po-cash">
                      <i class="bi bi-cash-coin text-success me-1"></i>Cash / Tunai
                    </label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" id="po-kredit" value="kredit" v-model="poForm.payment_type" />
                    <label class="form-check-label small" for="po-kredit">
                      <i class="bi bi-credit-card text-primary me-1"></i>Kredit
                    </label>
                  </div>
                </div>
              </div>
              <!-- Tenor — hanya tampil jika Kredit -->
              <div class="col-md-3" v-if="poForm.payment_type === 'kredit'">
                <label class="form-label small fw-semibold">Tenor <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                  <input v-model="poForm.payment_term_days" type="number" class="form-control form-control-sm"
                    min="1" max="365" placeholder="30" />
                  <span class="input-group-text">hari</span>
                </div>
              </div>
              <!-- Estimasi jatuh tempo -->
              <div class="col-md-5" v-if="poForm.payment_type === 'kredit' && poForm.payment_term_days > 0">
                <label class="form-label small fw-semibold">Estimasi Jatuh Tempo</label>
                <div class="form-control form-control-sm bg-light text-muted" style="cursor:default;">
                  <i class="bi bi-calendar-event me-1"></i>{{ estimatedDueDatePO }}
                </div>
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
                      <span v-else class="badge bg-light text-muted border" style="font-size:.65rem;">Tanpa PN</span>
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

              <!-- Ringkasan Pembayaran -->
              <div v-if="poForm.payment_type === 'kredit'" class="d-flex align-items-center justify-content-between border-top pt-2 mb-1">
                <span class="small text-primary">
                  <i class="bi bi-credit-card me-1"></i>Kredit {{ poForm.payment_term_days }} hari
                </span>
                <span class="small text-muted">Jatuh tempo: {{ estimatedDueDatePO }}</span>
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

    <!-- ===== Modal Tambah Barang ===== -->
    <div class="modal fade" id="modalAddItem" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title text-success"><i class="bi bi-plus-circle me-2"></i>Tambah Barang</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">

              <!-- ── SEARCH BARANG ── -->
              <div class="col-12" v-if="!addItemForm.is_new_item">
                <label class="form-label small fw-semibold">Cari Barang dari Master Data</label>
                <div class="position-relative">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0">
                      <i class="bi bi-search text-muted"></i>
                    </span>
                    <input
                      v-model="addItemSearch"
                      type="text"
                      class="form-control border-start-0"
                      :class="addItemForm.item_id ? 'bg-light' : ''"
                      :placeholder="pm?.type === 'part' ? 'Cari nama part / part number...' : 'Cari nama barang...'"
                      :readonly="!!addItemForm.item_id"
                      @input="onAddItemSearch"
                      @focus="showAddItemDropdown = true"
                      @blur="() => setTimeout(() => { showAddItemDropdown = false }, 200)"
                    />
                    <button v-if="addItemSearch" type="button"
                      class="btn btn-outline-secondary btn-sm"
                      @mousedown.prevent="clearAddItemSearch">
                      <i class="bi bi-x"></i>
                    </button>
                  </div>

                  <!-- Dropdown hasil -->
                  <div v-if="showAddItemDropdown && addItemSearch && !addItemForm.item_id"
                    class="add-item-dropdown shadow-sm border rounded">
                    <div v-if="addItemSearchLoading" class="p-3 text-center text-muted small">
                      <span class="csm-spinner me-1"></span>Mencari...
                    </div>
                    <template v-else>
                      <div v-for="item in addItemResults" :key="item.id"
                        class="add-item-dropdown__row"
                        @mousedown.prevent="pilihBarangMaster(item)">
                        <div>
                          <div class="fw-semibold small" style="color:#1e293b;">{{ item.name }}</div>
                          <small class="text-muted">
                            <span v-if="item.part_number" class="text-primary fw-semibold me-1">{{ item.part_number }}</span>
                            <span v-if="item.category?.name">· {{ item.category.name }}</span>
                            <span v-if="item.brand"> · {{ item.brand }}</span>
                          </small>
                        </div>
                        <span class="badge bg-light text-dark border flex-shrink-0">{{ item.unit }}</span>
                      </div>
                      <div v-if="!addItemResults.length && addItemSearch" class="p-2 text-center">
                        <small class="text-muted d-block mb-2">Tidak ditemukan: <strong>{{ addItemSearch }}</strong></small>
                      </div>
                      <div class="p-2 border-top">
                        <button type="button" class="btn btn-warning btn-sm w-100"
                          @mousedown.prevent="aktivasiBarangBaruAdd">
                          <i class="bi bi-plus-circle me-1"></i>
                          Daftarkan "{{ addItemSearch || 'barang baru' }}" sebagai Barang Baru
                        </button>
                      </div>
                    </template>
                  </div>
                </div>

                <!-- Barang terpilih badge -->
                <div v-if="addItemForm.item_id" class="mt-2">
                  <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-check-circle me-1"></i>Barang dipilih dari master data
                  </span>
                </div>
              </div>

              <!-- ── FORM BARANG BARU ── -->
              <div v-if="addItemForm.is_new_item" class="col-12">
                <div class="p-3 rounded border border-warning" style="background:#fffbeb;">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="fw-semibold text-warning">
                      <i class="bi bi-exclamation-triangle me-1"></i>Barang baru — akan didaftarkan ke Master Barang
                    </small>
                    <button type="button" class="btn btn-xs btn-outline-secondary" @click="batalBarangBaruAdd">
                      <i class="bi bi-arrow-left me-1"></i>Batal
                    </button>
                  </div>
                  <div class="row g-2">
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Part Number <span class="text-danger">*</span></label>
                      <input v-model="addItemForm.new_part_number" type="text"
                        class="form-control form-control-sm"
                        :class="addItemDupWarning ? 'border-danger' : ''"
                        placeholder="Contoh: FLT-OLI-320..."
                        @input="checkAddItemPartNumber" />
                      <div v-if="addItemDupWarning" class="mt-1 p-2 rounded border border-danger" style="background:#fff5f5;">
                        <small class="text-danger fw-semibold d-block mb-1">
                          <i class="bi bi-exclamation-circle me-1"></i>
                          Part Number sudah ada di Master Barang.
                        </small>
                        <button type="button" class="btn btn-sm btn-danger w-100" @click="pakaiMasterDariAddItem">
                          <i class="bi bi-box-seam me-1"></i>Gunakan "{{ addItemDupItem?.name }}"
                        </button>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
                      <select v-model="addItemForm.new_category_id" class="form-select form-select-sm">
                        <option value="">-- Pilih Kategori --</option>
                        <option v-for="cat in addItemCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Brand / Merk</label>
                      <input v-model="addItemForm.new_brand" type="text" class="form-control form-control-sm" placeholder="CAT, Komatsu..." />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Stok Minimum</label>
                      <input v-model="addItemForm.new_min_stock" type="number" class="form-control form-control-sm" min="0" placeholder="0" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- ── NAMA BARANG ── -->
              <div class="col-12">
                <label class="form-label small fw-semibold">Nama Barang <span class="text-danger">*</span></label>
                <input v-model="addItemForm.nama_barang" type="text" class="form-control form-control-sm"
                  :class="addItemForm.is_new_item ? 'border-warning' : (addItemForm.item_id ? 'bg-light' : '')"
                  :readonly="!!addItemForm.item_id && !addItemForm.is_new_item"
                  placeholder="Nama barang..." />
              </div>

              <!-- ── FIELDS PART ── -->
              <template v-if="pm?.type === 'part'">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold">Part Number</label>
                  <input v-model="addItemForm.part_number" type="text" class="form-control form-control-sm"
                    :readonly="!!addItemForm.item_id && !addItemForm.is_new_item"
                    :class="addItemForm.item_id && !addItemForm.is_new_item ? 'bg-light' : ''"
                    placeholder="Contoh: J8610495" />
                </div>

                <!-- Kode Unit multi-select -->
                <div class="col-md-6 position-relative">
                  <label class="form-label small fw-semibold">Kode Unit / Alat Berat</label>
                  <!-- Tags yang sudah dipilih -->
                  <div v-if="addItemUnitList.length" class="d-flex flex-wrap gap-1 mb-1">
                    <span v-for="(ku, ki) in addItemUnitList" :key="ki"
                      class="badge d-inline-flex align-items-center gap-1"
                      style="background:#1a3a5c;font-size:.75rem;padding:4px 8px;">
                      {{ ku.kode }}
                      <span class="text-white-50 small">{{ ku.tipe }}</span>
                      <button type="button" class="btn-close btn-close-white ms-1"
                        style="font-size:.55rem;opacity:.7;"
                        @click="removeAddItemUnit(ki)"></button>
                    </span>
                  </div>
                  <!-- Input search unit -->
                  <input
                    v-model="addItemUnitSearch"
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Cari kode unit... (CSM 0038)"
                    autocomplete="off"
                    @input="filterAddItemUnits"
                    @focus="showAddItemUnitDrop = true; filterAddItemUnits()"
                    @blur="() => setTimeout(() => { showAddItemUnitDrop = false }, 200)"
                  />
                  <!-- Dropdown -->
                  <ul v-if="showAddItemUnitDrop && addItemUnitDropResults.length"
                    class="list-group position-absolute w-100 shadow-sm"
                    style="z-index:1070;max-height:180px;overflow-y:auto;top:100%;left:0;">
                    <li v-for="u in addItemUnitDropResults" :key="u.id"
                      class="list-group-item list-group-item-action py-1 px-2 small"
                      style="cursor:pointer;"
                      @mousedown.prevent="addAddItemUnit(u)">
                      <strong>{{ u.unit_code }}</strong>
                      <span class="text-muted ms-1">— {{ u.type_unit }} {{ u.brand }}</span>
                      <span v-if="addItemUnitList.some(k => k.kode === u.unit_code)"
                        class="badge bg-success ms-1" style="font-size:.65rem;">✓</span>
                    </li>
                  </ul>
                </div>

                <!-- Tipe Unit (auto-filled) -->
                <div class="col-12">
                  <label class="form-label small fw-semibold">Tipe Unit</label>
                  <input
                    :value="addItemUnitList.map(k => k.tipe).filter(Boolean).join(', ') || addItemForm.tipe_unit"
                    type="text" class="form-control form-control-sm bg-light"
                    placeholder="Otomatis terisi dari unit yang dipilih" readonly />
                </div>
              </template>

              <!-- ── QTY & SATUAN ── -->
              <div class="col-6">
                <label class="form-label small fw-semibold">Jumlah <span class="text-danger">*</span></label>
                <input v-model="addItemForm.qty" type="number" class="form-control form-control-sm" min="0.01" step="0.01" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Satuan <span class="text-danger">*</span></label>
                <input v-model="addItemForm.satuan" type="text" class="form-control form-control-sm"
                  :class="addItemForm.item_id && !addItemForm.is_new_item ? 'bg-light' : ''"
                  :readonly="!!addItemForm.item_id && !addItemForm.is_new_item"
                  placeholder="Pcs, Ltr, Set..." />
              </div>

              <!-- ── KETERANGAN ── -->
              <div class="col-12">
                <label class="form-label small fw-semibold">Keterangan</label>
                <input v-model="addItemForm.keterangan" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
              </div>

            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success btn-sm" @click="saveAddItem" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-plus-lg me-1"></i>Tambah Barang
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Modal Edit Item ===== -->
    <div class="modal fade" id="modalEditItem" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title text-primary"><i class="bi bi-pencil me-2"></i>Edit Item</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2">
              <div class="col-12" v-if="pm?.type === 'part'">
                <label class="form-label small fw-semibold">Part Number</label>
                <input v-model="editItemForm.part_number" type="text" class="form-control form-control-sm" placeholder="Contoh: J8610495" />
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Nama Barang <span class="text-danger">*</span></label>
                <input v-model="editItemForm.nama_barang" type="text" class="form-control form-control-sm" />
              </div>
              <div class="col-6" v-if="pm?.type === 'part'">
                <label class="form-label small fw-semibold">Kode Unit</label>
                <input v-model="editItemForm.kode_unit" type="text" class="form-control form-control-sm" placeholder="CSM 0038" />
              </div>
              <div class="col-6" v-if="pm?.type === 'part'">
                <label class="form-label small fw-semibold">Tipe Unit</label>
                <input v-model="editItemForm.tipe_unit" type="text" class="form-control form-control-sm" placeholder="CAT 320D" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Jumlah <span class="text-danger">*</span></label>
                <input v-model="editItemForm.qty" type="number" class="form-control form-control-sm" min="0.01" step="0.01" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Satuan <span class="text-danger">*</span></label>
                <input v-model="editItemForm.satuan" type="text" class="form-control form-control-sm" placeholder="Pcs, Ltr, Set..." />
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Keterangan</label>
                <input v-model="editItemForm.keterangan" type="text" class="form-control form-control-sm" placeholder="Opsional..." />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-primary btn-sm" @click="saveEditItem" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
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
import { useSignerPicker } from '@/composables/useSignerPicker'
import SignerPickerModal from '@/components/SignerPickerModal.vue'

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

// ── Tanda Tangan ──────────────────────────────────────────────────────
const {
  showSignerModal, slots, isFinalized, finalizedAt,
  loading: signerLoading, actionLoading: signerActionLoading,
  openSignerPicker, addMySlot, finalizeDoc, closeModal,
} = useSignerPicker()

const bonForm = ref({ warehouse_id: '', issue_date: '', received_by: '', notes: '' })
const poForm = ref({ vendor_name: '', vendor_contact: '', warehouse_id: '', expected_date: '', notes: '', diskon_persen: 0, ppn_percent: 0, items: [], payment_type: 'cash', payment_term_days: 30 })

const editItemForm = ref({ id: null, part_number: '', nama_barang: '', kode_unit: '', tipe_unit: '', qty: 1, satuan: '', keterangan: '' })
const addItemForm  = ref({
  item_id: null, part_number: '', nama_barang: '', kode_unit: '', tipe_unit: '',
  qty: 1, satuan: 'Pcs', keterangan: '',
  is_new_item: false,
  new_part_number: '', new_category_id: '', new_brand: '', new_min_stock: 0,
})
const addItemSearch        = ref('')
const showAddItemDropdown  = ref(false)
const addItemResults       = ref([])
const addItemSearchLoading = ref(false)
const addItemCategories    = ref([])

const addItemDupWarning  = ref(false)
const addItemDupItem     = ref(null)
let addItemDupTimer = null

async function checkAddItemPartNumber() {
  addItemDupWarning.value = false; addItemDupItem.value = null
  const pn = (addItemForm.value.new_part_number || '').trim()
  if (pn.length < 2) return
  clearTimeout(addItemDupTimer)
  addItemDupTimer = setTimeout(async () => {
    try {
      const res = await axios.get('/items', { params: { search: pn, per_page: 5 } })
      const found = (res.data.data || []).find(i => i.part_number?.toLowerCase() === pn.toLowerCase())
      if (found) { addItemDupWarning.value = true; addItemDupItem.value = found }
    } catch {}
  }, 400)
}

function pakaiMasterDariAddItem() {
  const master = addItemDupItem.value; if (!master) return
  addItemForm.value.is_new_item = false
  addItemForm.value.item_id     = master.id
  addItemForm.value.nama_barang = master.name
  addItemForm.value.part_number = master.part_number || ''
  addItemForm.value.satuan      = master.unit || ''
  addItemSearch.value           = master.name
  addItemDupWarning.value       = false; addItemDupItem.value = null
}
function onAddItemSearch() {
  showAddItemDropdown.value = true
  clearTimeout(addItemSearchTimer)
  if (!addItemSearch.value.trim()) { addItemResults.value = []; return }
  addItemSearchLoading.value = true
  addItemSearchTimer = setTimeout(async () => {
    try {
      const res = await axios.get('/items', { params: { search: addItemSearch.value, per_page: 10 } })
      addItemResults.value = res.data.data || []
    } catch { addItemResults.value = [] }
    finally { addItemSearchLoading.value = false }
  }, 300)
}

function pilihBarangMaster(item) {
  addItemForm.value.item_id    = item.id
  addItemForm.value.nama_barang = item.name
  addItemForm.value.part_number = item.part_number || ''
  addItemForm.value.satuan      = item.unit || ''
  addItemSearch.value           = item.name
  showAddItemDropdown.value     = false
}

function clearAddItemSearch() {
  addItemSearch.value           = ''
  addItemResults.value          = []
  addItemForm.value.item_id     = null
  addItemForm.value.nama_barang = ''
  addItemForm.value.part_number = ''
  addItemForm.value.satuan      = 'Pcs'
}

async function aktivasiBarangBaruAdd() {
  addItemForm.value.is_new_item       = true
  addItemForm.value.nama_barang       = addItemSearch.value
  addItemForm.value.new_part_number   = ''
  addItemForm.value.new_category_id   = ''
  addItemForm.value.new_brand         = ''
  addItemForm.value.new_min_stock     = 0
  showAddItemDropdown.value           = false
  // Load categories jika belum
  if (!addItemCategories.value.length) {
    try {
      const res = await axios.get('/categories')
      addItemCategories.value = res.data.data || res.data || []
    } catch {}
  }
}

function batalBarangBaruAdd() {
  addItemForm.value.is_new_item = false
  addItemForm.value.item_id     = null
}

const addItemUnitList        = ref([])   // [{kode, tipe}]
const addItemUnitSearch      = ref('')
const showAddItemUnitDrop    = ref(false)
const addItemUnitDropResults = ref([])
const allUnits               = ref([])

async function loadUnits() {
  if (allUnits.value.length) return
  try {
    const res = await axios.get('/units', { params: { per_page: 999 } })
    allUnits.value = res.data.data || []
  } catch {}
}

function filterAddItemUnits() {
  const q = (addItemUnitSearch.value || '').toLowerCase()
  addItemUnitDropResults.value = q.length < 1
    ? allUnits.value.slice(0, 10)
    : allUnits.value.filter(u =>
        u.unit_code?.toLowerCase().includes(q) ||
        u.type_unit?.toLowerCase().includes(q) ||
        u.brand?.toLowerCase().includes(q)
      ).slice(0, 15)
}

function addAddItemUnit(u) {
  if (addItemUnitList.value.some(k => k.kode === u.unit_code)) {
    addItemUnitSearch.value = ''; showAddItemUnitDrop.value = false; return
  }
  addItemUnitList.value.push({ kode: u.unit_code || '', tipe: u.type_unit || '' })
  addItemForm.value.kode_unit = addItemUnitList.value.map(k => k.kode).join(', ')
  addItemForm.value.tipe_unit = addItemUnitList.value.map(k => k.tipe).filter(Boolean).join(', ')
  addItemUnitSearch.value = ''; showAddItemUnitDrop.value = false
}

function removeAddItemUnit(idx) {
  addItemUnitList.value.splice(idx, 1)
  addItemForm.value.kode_unit = addItemUnitList.value.map(k => k.kode).join(', ')
  addItemForm.value.tipe_unit = addItemUnitList.value.map(k => k.tipe).filter(Boolean).join(', ')
}

function openAddItem() {
  addItemForm.value = {
    item_id: null, part_number: '', nama_barang: '', kode_unit: '', tipe_unit: '',
    qty: 1, satuan: 'Pcs', keterangan: '',
    is_new_item: false,
    new_part_number: '', new_category_id: '', new_brand: '', new_min_stock: 0,
  }
  addItemSearch.value       = ''
  addItemResults.value      = []
  showAddItemDropdown.value = false
  addItemUnitList.value     = []
  addItemUnitSearch.value   = ''
  addItemUnitDropResults.value = []
  addItemDupWarning.value      = false
  addItemDupItem.value         = null
  loadUnits()
  new Modal('#modalAddItem').show()
}

async function saveAddItem() {
  if (!addItemForm.value.nama_barang) return toast.error('Nama barang wajib diisi')
  if (!addItemForm.value.qty || addItemForm.value.qty <= 0) return toast.error('Jumlah harus lebih dari 0')
  if (!addItemForm.value.satuan) return toast.error('Satuan wajib diisi')
  if (addItemForm.value.is_new_item) {
    if (!addItemForm.value.new_part_number) return toast.error('Part Number wajib diisi untuk barang baru')
    if (!addItemForm.value.new_category_id) return toast.error('Kategori wajib dipilih untuk barang baru')
    if (addItemDupWarning.value)
      return toast.error(`Part Number "${addItemForm.value.new_part_number}" sudah ada di master. Gunakan barang yang sudah ada atau ganti Part Number.`)
  }

  // Cek duplikat terhadap item yang sudah ada di PM
  const existingItems = pm.value?.items || []
  const newPN = (addItemForm.value.part_number || addItemForm.value.new_part_number || '').toLowerCase()
  const newItemId = addItemForm.value.item_id

  if (newItemId && existingItems.some(i => i.item_id === newItemId)) {
    return toast.error(`Barang "${addItemForm.value.nama_barang}" sudah ada di daftar PM ini.`)
  }
  if (newPN && existingItems.some(i => (i.part_number || '').toLowerCase() === newPN)) {
    return toast.error(`Part Number "${newPN.toUpperCase()}" sudah ada di daftar PM ini.`)
  }
  saving.value = true
  try {
    await axios.post(`/permintaan-material/${pm.value.id}/items`, {
      item_id:       addItemForm.value.item_id,
      nama_barang:   addItemForm.value.nama_barang,
      part_number:   addItemForm.value.part_number,
      kode_unit:     addItemForm.value.kode_unit,
      tipe_unit:     addItemForm.value.tipe_unit,
      qty:           addItemForm.value.qty,
      satuan:        addItemForm.value.satuan,
      keterangan:    addItemForm.value.keterangan,
      is_new_item:   addItemForm.value.is_new_item,
      new_part_number: addItemForm.value.new_part_number,
      new_category_id: addItemForm.value.new_category_id,
      new_brand:       addItemForm.value.new_brand,
      new_min_stock:   addItemForm.value.new_min_stock,
    })
    toast.success('Barang berhasil ditambahkan')
    Modal.getInstance('#modalAddItem')?.hide()
    loadPM()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menambahkan barang')
  } finally { saving.value = false }
}

function openEditItem(item) {
  editItemForm.value = {
    id:           item.id,
    part_number:  item.part_number || '',
    nama_barang:  item.nama_barang || '',
    kode_unit:    item.kode_unit || '',
    tipe_unit:    item.tipe_unit || '',
    qty:          item.qty,
    satuan:       item.satuan || '',
    keterangan:   item.keterangan || '',
  }
  new Modal('#modalEditItem').show()
}

async function saveEditItem() {
  if (!editItemForm.value.nama_barang) return toast.error('Nama barang wajib diisi')
  if (!editItemForm.value.qty || editItemForm.value.qty <= 0) return toast.error('Jumlah harus lebih dari 0')
  if (!editItemForm.value.satuan) return toast.error('Satuan wajib diisi')

  // Cek duplikat part_number: pastikan tidak ada item LAIN di PM ini dengan part_number yang sama
  const newPN = (editItemForm.value.part_number || '').trim().toLowerCase()
  if (newPN) {
    const duplicate = (pm.value.items || []).find(i =>
      i.id !== editItemForm.value.id &&
      (i.part_number || '').trim().toLowerCase() === newPN
    )
    if (duplicate) {
      return toast.error(`Part Number "${newPN.toUpperCase()}" sudah ada di daftar PM ini. Tidak bisa menyimpan perubahan dengan Part Number yang sama.`)
    }
  }

  saving.value = true
  try {
    await axios.put(`/permintaan-material/${pm.value.id}/items/${editItemForm.value.id}`, editItemForm.value)
    toast.success('Item berhasil diperbarui')
    Modal.getInstance('#modalEditItem')?.hide()
    loadPM()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal memperbarui item')
  } finally { saving.value = false }
}

async function doDeleteItem(item) {
  if (pm.value.items.length <= 1) return toast.error('PM harus memiliki minimal 1 item')
  if (!confirm(`Hapus item "${item.nama_barang}" dari PM ini?`)) return
  acting.value = true
  try {
    await axios.delete(`/permintaan-material/${pm.value.id}/items/${item.id}`)
    toast.success('Item berhasil dihapus')
    loadPM()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Gagal menghapus item')
  } finally { acting.value = false }
}

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

const estimatedDueDatePO = computed(() => {
  const days = parseInt(poForm.value.payment_term_days)
  if (!days || days < 1) return '-'
  const d = new Date()
  d.setDate(d.getDate() + days)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
})

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
  // Suppliers hanya dimuat jika user punya akses view-accounting
  try {
    const requests = [axios.get('/warehouses')]
    if (can('view-accounting')) requests.push(axios.get('/suppliers'))

    const [resW, resS] = await Promise.all(requests)
    warehouses.value = resW.data.data
    suppliers.value = resS?.data.data || []
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
    const items = pm.value.items.map(i => ({
      nama_barang: i.nama_barang,
      item_id: i.item_id || null,
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
    payment_type: 'cash',
    payment_term_days: 30,
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
  if (poForm.value.payment_type === 'kredit' && (!poForm.value.payment_term_days || poForm.value.payment_term_days < 1)) {
    return toast.error('Isi tenor (hari) untuk PO kredit')
  }
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
      payment_type: poForm.value.payment_type,
      payment_term_days: poForm.value.payment_type === 'kredit' ? parseInt(poForm.value.payment_term_days) : null,
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

function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') }

function buildSignGrid(resolvedSigners) {
  const defaults = [
    { label: 'Ordered by Logistic',    name: '', position: '', signature: null },
    { label: 'Received by Purchasing', name: '', position: '', signature: null },
    { label: 'Authorized by',          name: '', position: '', signature: null },
    { label: 'Approved by',            name: '', position: '', signature: null },
  ]
  const list = resolvedSigners.length ? resolvedSigners : defaults
  return list.map(s => `
    <div class="sb">
      <div class="sl">${escH(s.label)}</div>
      ${s.signature
        ? `<div class="si"><img src="${s.signature}" style="max-height:100%;max-width:100%;width:auto;height:auto;object-fit:contain;display:block;margin:0 auto;" /></div>`
        : `<div class="si"></div>`
      }
      <div class="sn">${s.name ? escH(s.name) : ''}${s.position ? `<div style="font-size:8px;font-weight:400;color:#94a3b8;margin-top:2px;">${escH(s.position)}</div>` : ''}</div>
    </div>`
  ).join('')
}

function buildHTML(data, resolvedSigners = []) {
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
  .sg{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-top:28px;background:#fff;}
  .sb{border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 12px;background:#fff;}
  .sl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:6px;}
  .si{height:90px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;}
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
      <div class="ir"><span class="il">No. MR</span><span class="iv hi">${data.nomor}</span></div>
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
    ${buildSignGrid(resolvedSigners)}
  </div>
</div>
</body></html>`
}

// Langkah 1: buka modal pilih penandatangan
async function onClickPrint() {
  openSignerPicker('permintaan_material', pm?.id)
}

// Langkah 2: user konfirmasi signer → fetch TTD → print
async function printPDF(slots = []) {
  // Konversi slots ke resolvedSigners (filter null)
  const resolvedSigners = slots.filter(s => s !== null)

  const html = buildHTML(pm.value, resolvedSigners)
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
    const a = Object.assign(document.createElement('a'), { href: burl, download: `MR-${pm.value.nomor}.xlsx` })
    document.body.appendChild(a); a.click()
    document.body.removeChild(a); URL.revokeObjectURL(burl)
    toast.success(`✅ MR-${pm.value.nomor}.xlsx berhasil diunduh`)
  })
  .catch(() => toast.error('Gagal mengunduh Excel'))
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
  printPDF(slots.value)
}

</script>

<style scoped>
.add-item-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0; right: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,.12);
  z-index: 1060;
  max-height: 280px;
  overflow-y: auto;
}
.add-item-dropdown__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  transition: background .12s;
}
.add-item-dropdown__row:last-child { border-bottom: none; }
.add-item-dropdown__row:hover { background: #f0f9ff; }
</style>