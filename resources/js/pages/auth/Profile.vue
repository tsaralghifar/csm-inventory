<template>
  <div class="row justify-content-center">
    <div class="col-md-6">
      <h5 class="fw-bold mb-3" style="color:#1a3a5c;">Profil Saya</h5>

      <div class="csm-card mb-3">
        <div class="csm-card-header"><h6><i class="bi bi-person-circle me-2"></i>Informasi Akun</h6></div>
        <div class="csm-card-body">
          <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold" style="width:64px;height:64px;font-size:1.5rem;">
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
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/store/auth'
import { useToast } from 'vue-toastification'

const auth = useAuthStore(); const toast = useToast()
const saving = ref(false)
const pwdForm = ref({ current_password: '', password: '', password_confirmation: '' })
const canSubmit = computed(() => pwdForm.value.current_password && pwdForm.value.password?.length >= 8 && pwdForm.value.password === pwdForm.value.password_confirmation)
function roleClass(r) { const m={superuser:'bg-danger',admin_ho:'bg-primary',admin_site:'bg-info',manager:'bg-success',viewer:'bg-secondary'}; return m[r]||'bg-secondary' }

async function changePwd() {
  saving.value = true
  try {
    await auth.changePassword(pwdForm.value)
    toast.success('Password berhasil diubah')
    pwdForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch(e) { toast.error(e.response?.data?.message || 'Gagal mengubah password') } finally { saving.value = false }
}
</script>
