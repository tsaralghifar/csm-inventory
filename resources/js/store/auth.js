import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('csm_token'))

  const isLoggedIn = computed(() => !!token.value && !!user.value)
  const isSuperuser = computed(() => user.value?.is_superuser || false)
  const isAdminHO = computed(() => user.value?.is_admin_ho || false)
  const userWarehouseId = computed(() => user.value?.warehouse_id)
  const userWarehouse = computed(() => user.value?.warehouse)

  function hasPermission(permission) {
    if (!user.value) return false
    if (user.value.is_superuser) return true
    return user.value.permissions?.includes(permission) || false
  }

  function hasRole(role) {
    if (!user.value) return false
    return user.value.roles?.includes(role) || false
  }

  async function login(email, password) {
    const res = await axios.post('/auth/login', { email, password })
    token.value = res.data.token
    user.value = res.data.user
    localStorage.setItem('csm_token', token.value)
    axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
  }

  async function fetchUser() {
    if (!token.value) return
    axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
    const res = await axios.get('/auth/me')
    user.value = res.data.data
  }

  async function logout() {
    try { await axios.post('/auth/logout') } catch {}
    token.value = null
    user.value = null
    localStorage.removeItem('csm_token')
    delete axios.defaults.headers.common['Authorization']
  }

  async function changePassword(data) {
    await axios.post('/auth/change-password', data)
  }

  return {
    user, token, isLoggedIn, isSuperuser, isAdminHO,
    userWarehouseId, userWarehouse,
    hasPermission, hasRole,
    login, fetchUser, logout, changePassword,
  }
})
