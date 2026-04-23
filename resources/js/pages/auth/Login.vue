<template>
  <div class="csm-login-bg">
    <div class="csm-login-card">
      <div class="text-center mb-4">
        <div style="font-size:3rem;">🏗️</div>
        <h4 class="fw-bold" style="color:#1a3a5c;">CSM Inventory System</h4>
        <p class="text-muted small mb-0">PT. Cipta Sarana Makmur</p>
        <p class="text-muted" style="font-size:0.78rem;">Sistem Manajemen Inventori Sparepart Alat Berat</p>
      </div>

      <form @submit.prevent="doLogin">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input v-model="form.email" type="email" class="form-control" placeholder="email@csm.co.id" required :disabled="loading" />
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold small">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input v-model="form.password" :type="showPwd ? 'text' : 'password'" class="form-control" placeholder="••••••••" required :disabled="loading" />
            <button type="button" class="btn btn-outline-secondary" @click="showPwd = !showPwd">
              <i :class="showPwd ? 'bi-eye-slash' : 'bi-eye'" class="bi"></i>
            </button>
          </div>
        </div>

        <div v-if="error" class="alert alert-danger py-2 small mb-3">
          <i class="bi bi-exclamation-triangle me-2"></i>{{ error }}
        </div>

        <button type="submit" class="btn w-100 btn-csm-primary fw-semibold" :disabled="loading">
          <span v-if="loading"><span class="csm-spinner me-2"></span>Memproses...</span>
          <span v-else><i class="bi bi-box-arrow-in-right me-2"></i>Masuk</span>
        </button>
      </form>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import { useToast } from 'vue-toastification'

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()

const form = ref({ email: '', password: '' })
const loading = ref(false)
const error = ref('')
const showPwd = ref(false)

async function doLogin() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(form.value.email, form.value.password)
    toast.success(`Selamat datang, ${auth.user.name}!`)
    router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Login gagal'
  } finally {
    loading.value = false
  }
}
</script>