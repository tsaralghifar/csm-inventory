<template>
  <div>
    <div class="mb-3">
      <h5 class="fw-bold mb-1" style="color:#1a3a5c;">Role & Hak Akses</h5>
      <small class="text-muted">Atur menu dan permission yang bisa diakses setiap role</small>
    </div>

    <div v-if="loading" class="p-4 text-center"><div class="csm-spinner"></div></div>

    <div class="row g-3" v-else>
      <div class="col-md-3">
        <div class="csm-card">
          <div class="csm-card-header"><h6>Daftar Role</h6></div>
          <div class="list-group list-group-flush">
            <button v-for="role in roles" :key="role.id"
              :class="['list-group-item list-group-item-action py-3', selectedRole?.name === role.name ? 'active' : '']"
              @click="selectRole(role)">
              <div class="d-flex align-items-center gap-2">
                <span :class="roleClass(role.name)" class="badge">{{ role.name }}</span>
                <small class="text-muted" v-if="selectedRole?.name !== role.name">{{ role.permissions?.length || 0 }} akses</small>
              </div>
            </button>
          </div>
        </div>
      </div>

      <div class="col-md-9" v-if="selectedRole">
        <div class="csm-card">
          <div class="csm-card-header">
            <h6>Permissions untuk: <span :class="roleClass(selectedRole.name)" class="badge ms-1">{{ selectedRole.name }}</span></h6>
            <button class="btn btn-success btn-sm" @click="savePermissions" :disabled="saving">
              <span v-if="saving"><span class="csm-spinner me-1"></span></span>Simpan Perubahan
            </button>
          </div>
          <div class="csm-card-body">
            <div class="row g-4">
              <div v-for="(perms, group) in groupedPermissions" :key="group" class="col-md-6">
                <div class="border rounded p-3">
                  <div class="fw-bold mb-2 text-capitalize">
                    <i class="bi bi-grid-3x3-gap me-1 text-primary"></i>{{ groupLabel(group) }}
                  </div>
                  <div v-for="perm in perms" :key="perm.id" class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" :id="'perm_'+perm.id"
                      :value="perm.name"
                      v-model="selectedPermissions"
                      :disabled="selectedRole.name === 'superuser'" />
                    <label class="form-check-label small" :for="'perm_'+perm.id">
                      {{ permLabel(perm.name) }}
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="selectedRole.name === 'superuser'" class="alert alert-info mt-3 small">
              <i class="bi bi-info-circle me-1"></i>Superuser secara otomatis memiliki semua akses dan tidak dapat diubah.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const toast = useToast()
const roles = ref([]); const allPermissions = ref({}); const loading = ref(false); const saving = ref(false)
const selectedRole = ref(null); const selectedPermissions = ref([])

onMounted(async () => {
  loading.value = true
  try {
    const [rRes, pRes] = await Promise.all([axios.get('/roles'), axios.get('/permissions')])
    roles.value = rRes.data.data; allPermissions.value = pRes.data.data
  } finally { loading.value = false }
})

const groupedPermissions = computed(() => allPermissions.value)

function selectRole(role) {
  selectedRole.value = role
  selectedPermissions.value = role.permissions?.map(p => p.name) || []
}

async function savePermissions() {
  saving.value = true
  try {
    await axios.post('/roles/update-permissions', { role: selectedRole.value.name, permissions: selectedPermissions.value })
    toast.success('Permission berhasil diperbarui')
    const r = await axios.get('/roles')
    roles.value = r.data.data
    const updated = roles.value.find(r => r.name === selectedRole.value.name)
    if (updated) { selectedRole.value = updated; selectedPermissions.value = updated.permissions?.map(p => p.name) || [] }
  } catch(e) { toast.error(e.response?.data?.message || e.response?.data?.error || 'Gagal menyimpan: ' + (e.response?.status || e.message)) } finally { saving.value = false }
}

function roleClass(r) {
  const m = {
    superuser:'bg-danger', admin_ho:'bg-primary', admin_site:'bg-info',
    manager:'bg-success', chief_mekanik:'bg-warning text-dark',
    purchasing:'bg-dark', viewer:'bg-secondary',
    accounting:'bg-purple'
  }
  return m[r] || 'bg-secondary'
}

function groupLabel(g) {
  const m = {
    stocks:'Stok', items:'Barang', warehouses:'Gudang',
    mr:'Material Request', fuel:'BBM/Solar', apd:'APD',
    toolbox:'Toolbox', reports:'Laporan', users:'User',
    roles:'Role', units:'Unit Alat', employees:'Karyawan',
    sj:'Surat Jalan / Tanda Terima Pembelian', transfer:'Transfer Barang', retur:'Retur Barang',
    bon:'Bon Pengeluaran', po:'Purchase Order', pm:'Permintaan Material',
    accounting:'Accounting', payroll:'Payroll',
    other:'Lainnya'
  }
  return m[g] || g
}

function permLabel(p) {
  const m = {
    'view-stocks':'Lihat Stok', 'create-stock-in':'Stok Masuk', 'create-stock-out':'Stok Keluar', 'adjust-stock':'Penyesuaian Stok',
    'view-items':'Lihat Barang', 'manage-items':'Kelola Barang',
    'view-warehouses':'Lihat Gudang', 'manage-warehouses':'Kelola Gudang',
    'view-mr':'Lihat MR', 'create-mr':'Buat MR', 'approve-mr':'Approve MR (Admin)', 'dispatch-mr':'Kirim Barang (DO)',
    'authorize-mr-chief':'Otorisasi MR (Chief)', 'approve-mr-manager':'Approve MR (Manager)', 'approve-mr-ho':'Approve MR (HO)',
    'view-po':'Lihat PO', 'create-po':'Buat PO', 'manage-po':'Kelola PO',
    'view-bon':'Lihat Bon Pengeluaran', 'create-bon':'Buat Bon', 'issue-bon':'Keluarkan Bon',
    'view-sj':'Lihat Surat Jalan', 'create-sj':'Buat Surat Jalan (dari PO)', 'receive-sj':'Konfirmasi Terima Barang (dari SJ)',
    'view-transfer':'Lihat Transfer Barang', 'create-transfer':'Buat Transfer Barang',
    'approve-transfer-admin':'Approve Transfer (Admin HO)', 'approve-transfer-atasan':'Approve Transfer (Atasan)',
    'dispatch-transfer':'Kirim Transfer (Buat DO)', 'receive-transfer':'Konfirmasi Terima Transfer',
    'view-retur':'Lihat Retur Barang', 'create-retur':'Buat Retur Barang', 'confirm-retur':'Konfirmasi Retur',
    'view-pm':'Lihat Permintaan Material', 'create-pm':'Buat Permintaan Material',
    'approve-pm-site':'Approve PM (Site)', 'approve-pm-ho':'Approve PM (HO)',
    'view-fuel':'Lihat BBM', 'manage-fuel':'Kelola BBM',
    'view-apd':'Lihat APD', 'manage-apd':'Kelola APD',
    'view-toolbox':'Lihat Toolbox', 'manage-toolbox':'Kelola Toolbox',
    'view-reports':'Lihat Laporan', 'export-reports':'Export Laporan',
    'manage-users':'Kelola User', 'manage-roles':'Kelola Role',
    'view-units':'Lihat Unit', 'manage-units':'Kelola Unit',
    'view-employees':'Lihat Karyawan', 'manage-employees':'Kelola Karyawan',
    'view-accounting':'Lihat Accounting', 'manage-accounting':'Kelola Accounting', 'approve-accounting':'Approve Transaksi Accounting',
    'view-payroll':'Lihat Payroll', 'manage-payroll':'Kelola Payroll', 'approve-payroll':'Approve & Bayar Payroll',
  }
  return m[p] || p
}
</script>