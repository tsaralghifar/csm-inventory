<template>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <h5 class="fw-bold mb-3" style="color:#1a3a5c;">Profil Saya</h5>

      <!-- Informasi Akun -->
      <div class="csm-card mb-3">
        <div class="csm-card-header"><h6><i class="bi bi-person-circle me-2"></i>Informasi Akun</h6></div>
        <div class="csm-card-body">
          <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold"
              style="width:64px;height:64px;font-size:1.5rem;">
              {{ auth.user?.name?.charAt(0) }}
            </div>
            <div>
              <div class="fw-bold fs-5">{{ auth.user?.name }}</div>
              <div class="text-muted small">{{ auth.user?.email }}</div>
              <span v-for="r in auth.user?.roles" :key="r" :class="roleClass(r)" class="badge me-1">{{ r }}</span>
            </div>
          </div>
          <dl class="row">
            <dt class="col-5 text-muted small">No. HP</dt>
            <dd class="col-7 small">{{ auth.user?.phone || '-' }}</dd>
            <dt class="col-5 text-muted small">Jabatan</dt>
            <dd class="col-7 small">{{ auth.user?.position || '-' }}</dd>
            <dt class="col-5 text-muted small">Gudang / Site</dt>
            <dd class="col-7 small">{{ auth.user?.warehouse?.name || 'Semua Gudang' }}</dd>
          </dl>
        </div>
      </div>

      <!-- ══ TANDA TANGAN DIGITAL ══ -->
      <div class="csm-card mb-3">
        <div class="csm-card-header">
          <h6><i class="bi bi-pen me-2"></i>Tanda Tangan Digital</h6>
        </div>
        <div class="csm-card-body">
          <p class="text-muted small mb-3">
            Upload foto/scan tanda tangan Anda (PNG/JPG, maks. 2MB).
            Tanda tangan ini akan otomatis muncul di PDF saat Anda dipilih sebagai penandatangan laporan.
          </p>

          <!-- Preview area -->
          <div
            class="border border-2 border-dashed rounded-3 mb-3 text-center position-relative"
            :class="signaturePreview ? 'border-success bg-light' : 'border-secondary bg-light'"
            style="min-height:110px; cursor: pointer;"
            @click="signaturePreview && (zoomSignature = true)"
          >
            <img
              v-if="signaturePreview"
              :src="signaturePreview"
              alt="Preview tanda tangan"
              class="p-2"
              style="max-height:90px; max-width:100%; object-fit:contain;"
            />
            <div v-else class="d-flex flex-column align-items-center justify-content-center py-4 text-muted">
              <i class="bi bi-pen fs-3 mb-1"></i>
              <span class="small">Belum ada tanda tangan</span>
            </div>
            <!-- Badge status -->
            <span v-if="signaturePreview"
              class="position-absolute top-0 end-0 badge bg-success m-2"
              style="font-size:10px;">
              <i class="bi bi-check-circle me-1"></i>Sudah diupload
            </span>
          </div>

          <!-- Feedback -->
          <div v-if="sigError" class="alert alert-danger py-2 small mb-2">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ sigError }}
          </div>
          <div v-if="sigSuccess" class="alert alert-success py-2 small mb-2">
            <i class="bi bi-check-circle me-1"></i>Tanda tangan berhasil disimpan!
          </div>

          <!-- Tombol aksi -->
          <div class="d-flex gap-2 flex-wrap">
            <label
              class="btn btn-csm-primary btn-sm mb-0"
              :class="{ disabled: sigUploading }"
              style="cursor:pointer;"
            >
              <span v-if="sigUploading"><span class="csm-spinner me-1"></span></span>
              <i v-else class="bi bi-upload me-1"></i>
              {{ sigUploading ? 'Mengupload...' : (signaturePreview ? 'Ganti Tanda Tangan' : 'Upload Tanda Tangan') }}
              <input
                ref="sigInput"
                type="file"
                accept="image/png,image/jpeg"
                class="d-none"
                @change="handleUpload"
                :disabled="sigUploading"
              />
            </label>

            <button
              v-if="signaturePreview"
              class="btn btn-outline-danger btn-sm"
              @click="handleDelete"
              :disabled="sigDeleting"
            >
              <span v-if="sigDeleting"><span class="csm-spinner me-1"></span></span>
              <i v-else class="bi bi-trash me-1"></i>
              {{ sigDeleting ? 'Menghapus...' : 'Hapus' }}
            </button>
          </div>

          <!-- Tips -->
          <div class="alert alert-info py-2 mt-3 mb-0 small">
            <div class="fw-semibold mb-1"><i class="bi bi-lightbulb me-1"></i>Tips agar hasil terbaik:</div>
            <ul class="mb-0 ps-3">
              <li>Tanda tangan di kertas putih, foto dari atas lurus</li>
              <li>Crop rapat, sisakan sedikit ruang putih di tepi</li>
              <li>Simpan sebagai PNG agar latar transparan (opsional)</li>
              <li>Resolusi minimal 200×80 px untuk hasil cetakan tajam</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Ganti Password -->
      <div class="csm-card">
        <div class="csm-card-header"><h6><i class="bi bi-lock me-2"></i>Ganti Password</h6></div>
        <div class="csm-card-body">
          <div class="mb-3">
            <label class="form-label fw-semibold small">Password Lama <span class="text-danger">*</span></label>
            <input v-model="pwdForm.current_password" type="password" class="form-control form-control-sm" />
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Password Baru <span class="text-danger">*</span></label>
            <input v-model="pwdForm.password" type="password" class="form-control form-control-sm" placeholder="Min. 8 karakter" />
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Konfirmasi Password Baru <span class="text-danger">*</span></label>
            <input v-model="pwdForm.password_confirmation" type="password" class="form-control form-control-sm" />
          </div>
          <div v-if="pwdForm.password && pwdForm.password !== pwdForm.password_confirmation" class="alert alert-danger py-2 small">
            Password baru tidak sama!
          </div>
          <button class="btn btn-csm-primary btn-sm" @click="changePwd" :disabled="saving || !canSubmit">
            <span v-if="saving"><span class="csm-spinner me-1"></span></span>Ganti Password
          </button>
        </div>
      </div>
    </div>

    <!-- Lightbox zoom tanda tangan -->
    <div
      v-if="zoomSignature && signaturePreview"
      class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
      style="background:rgba(0,0,0,0.7); z-index:9999; cursor:zoom-out;"
      @click="zoomSignature = false"
    >
      <img
        :src="signaturePreview"
        alt="Tanda tangan"
        class="rounded-3 shadow-lg bg-white p-3"
        style="max-height:80vh; max-width:90vw; object-fit:contain;"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/store/auth'
import { useToast } from 'vue-toastification'

const auth    = useAuthStore()
const toast   = useToast()
const saving  = ref(false)
const pwdForm = ref({ current_password: '', password: '', password_confirmation: '' })
const canSubmit = computed(() =>
  pwdForm.value.current_password &&
  pwdForm.value.password?.length >= 8 &&
  pwdForm.value.password === pwdForm.value.password_confirmation
)

// ── Tanda Tangan ─────────────────────────────────────────────────────
const signaturePreview = ref(null)
const sigUploading     = ref(false)
const sigDeleting      = ref(false)
const sigError         = ref(null)
const sigSuccess       = ref(false)
const sigInput         = ref(null)
const zoomSignature    = ref(false)

// Ambil preview tanda tangan dari profil saat halaman dimuat
onMounted(async () => {
  try {
    const { data } = await axios.get('/profile')
    signaturePreview.value = data.data.signature_preview ?? null
  } catch { /* abaikan jika gagal */ }
})

function clearSigFeedback() { sigError.value = null; sigSuccess.value = false }

async function handleUpload(e) {
  const file = e.target.files?.[0]
  if (!file) return
  clearSigFeedback()

  // Validasi
  if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
    sigError.value = 'Hanya file PNG atau JPG yang diizinkan.'; return
  }
  if (file.size > 2 * 1024 * 1024) {
    sigError.value = 'Ukuran file maksimal 2MB.'; return
  }

  // Preview lokal sementara
  const reader = new FileReader()
  reader.onload = ev => { signaturePreview.value = ev.target.result }
  reader.readAsDataURL(file)

  sigUploading.value = true
  try {
    const formData = new FormData()
    formData.append('signature_file', file)
    const { data } = await axios.post('/users/signature', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    signaturePreview.value = data.data.preview_url
    sigSuccess.value = true
    toast.success('Tanda tangan berhasil disimpan')
    setTimeout(() => { sigSuccess.value = false }, 3000)
  } catch (err) {
    sigError.value = err.response?.data?.message ?? 'Gagal mengupload tanda tangan.'
    signaturePreview.value = null
  } finally {
    sigUploading.value = false
    if (sigInput.value) sigInput.value.value = ''
  }
}

async function handleDelete() {
  const confirmed = window.confirm('Hapus tanda tangan? Tanda tangan tidak akan muncul di PDF sampai Anda upload ulang.')
  if (!confirmed) return
  clearSigFeedback()
  sigDeleting.value = true
  try {
    await axios.delete('/users/signature')
    signaturePreview.value = null
    toast.success('Tanda tangan berhasil dihapus')
  } catch (err) {
    sigError.value = err.response?.data?.message ?? 'Gagal menghapus tanda tangan.'
  } finally {
    sigDeleting.value = false
  }
}

// ── Password ──────────────────────────────────────────────────────────
function roleClass(r) {
  const m = { superuser:'bg-danger', admin_ho:'bg-primary', admin_site:'bg-info', manager:'bg-success', viewer:'bg-secondary' }
  return m[r] || 'bg-secondary'
}

async function changePwd() {
  saving.value = true
  try {
    await auth.changePassword(pwdForm.value)
    toast.success('Password berhasil diubah')
    pwdForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch(e) {
    toast.error(e.response?.data?.message || 'Gagal mengubah password')
  } finally {
    saving.value = false
  }
}
</script>