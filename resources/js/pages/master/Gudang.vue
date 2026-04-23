<template>
  <div>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="fw-bold mb-0" style="color:#1a3a5c;">Manajemen Gudang</h5>
      <button v-if="can('manage-warehouses')" class="btn btn-csm-primary btn-sm" @click="openForm()">
        <i class="bi bi-plus-circle me-1"></i>Tambah Gudang
      </button>
    </div>

    <!-- Tabs HO vs Site -->
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item"><a class="nav-link" :class="{active: tab==='all'}" href="#" @click.prevent="tab='all'">Semua</a></li>
      <li class="nav-item"><a class="nav-link" :class="{active: tab==='ho'}" href="#" @click.prevent="tab='ho'">Gudang HO</a></li>
      <li class="nav-item"><a class="nav-link" :class="{active: tab==='site'}" href="#" @click.prevent="tab='site'">Gudang Site</a></li>
    </ul>

    <div class="row g-3">
      <div v-for="w in filteredWarehouses" :key="w.id" class="col-12 col-md-6 col-xl-4">
        <div class="csm-card h-100">
          <div class="csm-card-header">
            <div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge" :class="w.type === 'ho' ? 'bg-primary' : 'bg-info text-dark'">{{ w.type.toUpperCase() }}</span>
                <h6 class="mb-0">{{ w.name }}</h6>
              </div>
              <small class="text-muted">{{ w.code }}</small>
            </div>
            <div class="d-flex gap-1">
              <button v-if="can('manage-warehouses')" class="btn btn-sm btn-outline-secondary" @click="openForm(w)"><i class="bi bi-pencil"></i></button>
              <span class="badge" :class="w.is_active ? 'bg-success' : 'bg-secondary'">{{ w.is_active ? 'Aktif' : 'Nonaktif' }}</span>
            </div>
          </div>
          <div class="csm-card-body">
            <div class="row g-2 text-sm mb-3">
              <div class="col-6">
                <div class="text-muted small">Lokasi</div>
                <div class="fw-semibold small">{{ w.location || '-' }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted small">PIC</div>
                <div class="fw-semibold small">{{ w.pic_name || '-' }}</div>
              </div>
            </div>
            <div class="d-flex gap-2">
              <div class="text-center flex-1 p-2 rounded bg-light">
                <div class="fw-bold text-primary">{{ w.item_stocks_count || 0 }}</div>
                <div class="text-muted" style="font-size:0.72rem;">Item</div>
              </div>
              <div class="text-center flex-1 p-2 rounded bg-light">
                <div class="fw-bold text-info">{{ w.units_count || 0 }}</div>
                <div class="text-muted" style="font-size:0.72rem;">Unit</div>
              </div>
            </div>
            <div class="mt-2">
              <router-link :to="`/stok/${w.type === 'ho' ? 'ho' : 'site'}?warehouse_id=${w.id}`" class="btn btn-sm btn-outline-primary w-100">
                <i class="bi bi-boxes me-1"></i>Lihat Stok
              </router-link>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!filteredWarehouses.length && !loading" class="col-12">
        <div class="alert alert-info">Tidak ada gudang ditemukan</div>
      </div>
    </div>

    <!-- Modal Form Gudang -->
    <div class="modal fade" id="modalGudang" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title"><i class="bi bi-building me-2"></i>{{ editMode ? 'Edit Gudang' : 'Tambah Gudang Baru' }}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label small fw-semibold">Kode Gudang <span class="text-danger">*</span></label>
                <input v-model="form.code" class="form-control" placeholder="HO-CSM / SITE-LOA" :disabled="editMode" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Tipe <span class="text-danger">*</span></label>
                <select v-model="form.type" class="form-select" :disabled="editMode">
                  <option value="ho">Gudang HO</option>
                  <option value="site">Gudang Site</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Nama Gudang <span class="text-danger">*</span></label>
                <input v-model="form.name" class="form-control" placeholder="Gudang Site Loajanan" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Lokasi / Kota</label>
                <input v-model="form.location" class="form-control" placeholder="Samarinda, Kaltim" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">PIC / Kepala Gudang</label>
                <input v-model="form.pic_name" class="form-control" />
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">No. HP PIC</label>
                <input v-model="form.pic_phone" class="form-control" placeholder="08xx" />
              </div>
              <div class="col-6" v-if="editMode">
                <label class="form-label small fw-semibold">Status</label>
                <select v-model="form.is_active" class="form-select">
                  <option :value="true">Aktif</option>
                  <option :value="false">Nonaktif</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Alamat Lengkap</label>
                <textarea v-model="form.address" class="form-control" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-csm-primary btn-sm" @click="saveGudang" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>{{ editMode ? 'Update' : 'Tambah Gudang' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/store/auth'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import { useRealtime } from '@/composables/useRealtime'
import { Modal } from 'bootstrap'

const auth = useAuthStore()
const toast = useToast()
const { listenMaster, stopMaster } = useRealtime()

const warehouses = ref([])
const loading = ref(true)
const saving = ref(false)
const editMode = ref(false)
const editId = ref(null)
const tab = ref('all')

const form = ref({ code: '', name: '', type: 'site', location: '', pic_name: '', pic_phone: '', address: '', is_active: true })

function can(p) { return auth.hasPermission(p) }

const filteredWarehouses = computed(() => {
  if (tab.value === 'all') return warehouses.value
  return warehouses.value.filter(w => w.type === tab.value)
})

async function loadWarehouses() {
  loading.value = true
  try {
    const res = await axios.get('/warehouses')
    warehouses.value = res.data.data
  } catch (e) { toast.error('Gagal memuat data gudang') }
  finally { loading.value = false }
}

function openForm(warehouse = null) {
  editMode.value = !!warehouse
  editId.value = warehouse?.id || null
  form.value = warehouse ? { ...warehouse } : { code: '', name: '', type: 'site', location: '', pic_name: '', pic_phone: '', address: '', is_active: true }
  new Modal('#modalGudang').show()
}

let suppressNextToast = false

async function saveGudang() {
  if (!form.value.code || !form.value.name) return toast.error('Kode dan nama gudang wajib diisi')
  saving.value = true
  try {
    if (editMode.value) {
      await axios.put(`/warehouses/${editId.value}`, form.value)
      toast.success('Gudang berhasil diperbarui')
    } else {
      await axios.post('/warehouses', form.value)
      toast.success('Gudang baru berhasil ditambahkan')
    }
    suppressNextToast = true
    Modal.getInstance('#modalGudang')?.hide()
    loadWarehouses()
  } catch (e) {
    toast.error(e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Gagal menyimpan')
  } finally { saving.value = false }
}

onMounted(() => {
  loadWarehouses()
  const actionLabels = {"created": "Gudang baru ditambahkan", "updated": "Data gudang diperbarui", "deleted": "Gudang dihapus"}
  listenMaster((e) => {
    if (!suppressNextToast) {
      toast.info(`🔔 ${actionLabels[e.action] || "Data master diperbarui"}`, { timeout: 3500 })
    }
    suppressNextToast = false
    loadWarehouses()
  })
})

onUnmounted(() => stopMaster())
</script>