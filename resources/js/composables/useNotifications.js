// composables/useNotifications.js
// Composable untuk mengelola notifikasi in-app (bell icon, dropdown, realtime update)
//
// Penggunaan di komponen manapun:
//   import { useNotifications } from '@/composables/useNotifications'
//   const { notifications, unreadCount, fetchUnreadCount, markAllRead } = useNotifications()

import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/store/auth'

const notifications  = ref([])
const unreadCount    = ref(0)
const loading        = ref(false)
const hasMore        = ref(false)
const currentPage    = ref(1)

// Shared state — singleton-like agar semua komponen pakai state yang sama
let echoChannel = null
let pollInterval = null

export function useNotifications() {
  const auth = useAuthStore()

  // ── Fetch ──────────────────────────────────────────────────────────────────

  async function fetchNotifications(page = 1, append = false) {
    if (loading.value) return
    loading.value = true
    try {
      const res = await axios.get('/notifications', {
        params: { page, per_page: 15 },
      })
      const items = res.data.data ?? []
      notifications.value  = append ? [...notifications.value, ...items] : items
      unreadCount.value    = res.data.meta?.unread_count ?? 0
      hasMore.value        = page < (res.data.meta?.last_page ?? 1)
      currentPage.value    = page
    } catch (e) {
      console.error('[useNotifications] fetch error:', e)
    } finally {
      loading.value = false
    }
  }

  async function fetchUnreadCount() {
    try {
      const res = await axios.get('/notifications/unread-count')
      unreadCount.value = res.data.count ?? 0
    } catch (e) {
      console.error('[useNotifications] unreadCount error:', e)
    }
  }

  async function loadMore() {
    if (!hasMore.value || loading.value) return
    await fetchNotifications(currentPage.value + 1, true)
  }

  // ── Mark read ──────────────────────────────────────────────────────────────

  async function markRead(id) {
    try {
      await axios.post(`/notifications/${id}/read`)
      const notif = notifications.value.find(n => n.id === id)
      if (notif) {
        notif.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch (e) {
      console.error('[useNotifications] markRead error:', e)
    }
  }

  async function markAllRead() {
    try {
      await axios.post('/notifications/read-all')
      notifications.value.forEach(n => {
        if (!n.read_at) n.read_at = new Date().toISOString()
      })
      unreadCount.value = 0
    } catch (e) {
      console.error('[useNotifications] markAllRead error:', e)
    }
  }

  // ── Realtime via Reverb (Echo) ─────────────────────────────────────────────

  function startListening() {
    if (!window.Echo || !auth.user?.id) return

    // Hentikan listener lama jika ada
    stopListening()

    echoChannel = window.Echo.private(`App.Models.User.${auth.user.id}`)
      .notification((payload) => {
        // Tambahkan notifikasi baru ke paling atas
        notifications.value.unshift({
          id:         payload.id ?? crypto.randomUUID(),
          type:       payload.type,
          data:       payload,
          read_at:    null,
          created_at: new Date().toISOString(),
        })
        unreadCount.value += 1

        // Tampilkan toast untuk notifikasi stok menipis
        if (payload.type === 'low_stock') {
          showToast(payload)
        }
      })
  }

  function stopListening() {
    if (echoChannel) {
      window.Echo?.leave(echoChannel.name)
      echoChannel = null
    }
  }

  // ── Polling fallback (jika Reverb tidak tersedia) ─────────────────────────

  function startPolling(intervalMs = 60_000) {
    stopPolling()
    pollInterval = setInterval(fetchUnreadCount, intervalMs)
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }

  // ── Toast helper ───────────────────────────────────────────────────────────

  function showToast(payload) {
    // Gunakan SweetAlert2 yang sudah ada di project
    if (!window.Swal) return

    const colorMap = { minus: '#dc3545', critical: '#fd7e14', low: '#0dcaf0' }
    const iconMap  = { minus: 'error', critical: 'warning', low: 'info' }

    window.Swal.fire({
      toast:             true,
      position:          'top-end',
      icon:              iconMap[payload.level] ?? 'warning',
      title:             payload.title,
      text:              payload.message,
      showConfirmButton: false,
      timer:             6000,
      timerProgressBar:  true,
      iconColor:         colorMap[payload.level],
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', window.Swal.stopTimer)
        toast.addEventListener('mouseleave', window.Swal.resumeTimer)
        // Klik toast → navigasi ke stok
        toast.style.cursor = 'pointer'
        toast.addEventListener('click', () => {
          window.location.href = payload.url ?? '/stok/ho'
          window.Swal.close()
        })
      },
    })
  }

  // ── Computed ───────────────────────────────────────────────────────────────

  const unreadNotifications = computed(() =>
    notifications.value.filter(n => !n.read_at)
  )

  const groupedByDate = computed(() => {
    const groups = {}
    notifications.value.forEach(n => {
      const date = new Date(n.created_at).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric',
      })
      if (!groups[date]) groups[date] = []
      groups[date].push(n)
    })
    return groups
  })

  return {
    // State
    notifications,
    unreadCount,
    unreadNotifications,
    groupedByDate,
    loading,
    hasMore,

    // Actions
    fetchNotifications,
    fetchUnreadCount,
    loadMore,
    markRead,
    markAllRead,
    startListening,
    stopListening,
    startPolling,
    stopPolling,
    showToast,
  }
}
