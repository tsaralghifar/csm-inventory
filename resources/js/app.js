import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import axios from 'axios'
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'
import 'bootstrap'

// Axios defaults
axios.defaults.baseURL = '/api'
axios.defaults.withCredentials = true
axios.defaults.headers.common['Accept'] = 'application/json'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// CSRF Token
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (csrfToken) axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken

// Auth token interceptor
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('csm_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Response interceptor
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      localStorage.removeItem('csm_token')
      router.push('/login')
    }
    return Promise.reject(error)
  }
)

// Global helper: bersihkan backdrop Bootstrap modal yang tersisa
window.clearModalBackdrop = () => {
  setTimeout(() => {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove())
    document.body.classList.remove('modal-open')
    document.body.style.removeProperty('overflow')
    document.body.style.removeProperty('padding-right')
  }, 400)
}

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(Toast, {
  position: 'top-right',
  timeout: 3500,
  closeOnClick: true,
})

// Global properties
app.config.globalProperties.$axios = axios
app.config.globalProperties.$formatNumber = (n) => new Intl.NumberFormat('id-ID').format(n || 0)
app.config.globalProperties.$formatCurrency = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0)
app.config.globalProperties.$formatDate = (d) => d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'

app.mount('#app')