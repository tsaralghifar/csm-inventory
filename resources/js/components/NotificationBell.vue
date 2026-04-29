<template>
  <!-- Bell icon dengan badge unread count -->
  <div class="notification-bell position-relative" ref="bellRef">
    <button
      class="btn btn-link p-1 position-relative"
      style="color: var(--csm-primary, #1a3a5c); font-size:1.25rem; line-height:1;"
      @click="toggleDropdown"
      :aria-label="`${unreadCount} notifikasi belum dibaca`"
    >
      <i class="bi bi-bell-fill"></i>
      <span
        v-if="unreadCount > 0"
        class="badge rounded-pill bg-danger position-absolute"
        style="font-size:0.6rem; top:-4px; right:-6px; min-width:18px; padding:2px 5px;"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown Panel -->
    <transition name="notif-slide">
      <div
        v-if="open"
        class="notification-panel shadow-lg"
        @click.stop
      >
        <!-- Header -->
        <div class="notif-header d-flex justify-content-between align-items-center px-3 py-2">
          <span class="fw-semibold" style="color:#1a3a5c;">
            <i class="bi bi-bell me-1"></i> Notifikasi
            <span v-if="unreadCount > 0" class="badge bg-danger ms-1 small">{{ unreadCount }}</span>
          </span>
          <button
            v-if="unreadCount > 0"
            class="btn btn-link btn-sm p-0 text-muted"
            style="font-size:0.75rem;"
            @click="handleMarkAllRead"
          >
            Tandai semua dibaca
          </button>
        </div>

        <!-- Tabs: Semua / Stok Alert -->
        <div class="notif-tabs d-flex border-bottom">
          <button
            class="notif-tab"
            :class="{ active: activeTab === 'all' }"
            @click="activeTab = 'all'"
          >Semua</button>
          <button
            class="notif-tab"
            :class="{ active: activeTab === 'stock' }"
            @click="activeTab = 'stock'"
          >
            Stok Alert
            <span v-if="lowStockCount > 0" class="badge bg-warning text-dark ms-1" style="font-size:0.6rem;">
              {{ lowStockCount }}
            </span>
          </button>
        </div>

        <!-- Body -->
        <div class="notif-body" ref="bodyRef" @scroll="onScroll">
          <!-- Loading skeleton -->
          <div v-if="loading && notifications.length === 0" class="p-3">
            <div v-for="i in 4" :key="i" class="notif-skeleton mb-2"></div>
          </div>

          <!-- Empty state -->
          <div v-else-if="filteredNotifications.length === 0" class="notif-empty">
            <i class="bi bi-check-circle text-success" style="font-size:2rem;"></i>
            <div class="mt-2 text-muted small">Tidak ada notifikasi</div>
          </div>

          <!-- Notification items -->
          <template v-else>
            <div
              v-for="notif in filteredNotifications"
              :key="notif.id"
              class="notif-item"
              :class="{ unread: !notif.read_at }"
              @click="handleNotifClick(notif)"
            >
              <!-- Level icon -->
              <div class="notif-icon-wrap" :class="`level-${notif.data?.level ?? 'info'}`">
                <i :class="notif.data?.icon ?? 'bi bi-bell'"></i>
              </div>

              <div class="notif-content">
                <div class="notif-title">{{ notif.data?.title ?? notif.type }}</div>
                <div class="notif-message">{{ notif.data?.message }}</div>
                <div class="notif-meta">
                  <span>{{ formatDate(notif.created_at) }}</span>
                  <span v-if="notif.data?.warehouse" class="ms-2 text-muted">
                    · {{ notif.data.warehouse }}
                  </span>
                </div>
              </div>

              <!-- Unread dot -->
              <div v-if="!notif.read_at" class="notif-dot"></div>
            </div>

            <!-- Load more -->
            <div v-if="hasMore" class="text-center py-2">
              <button class="btn btn-link btn-sm text-muted" @click="loadMore" :disabled="loading">
                <span v-if="loading" class="csm-spinner sm me-1"></span>
                Muat lebih banyak
              </button>
            </div>
          </template>
        </div>

        <!-- Footer -->
        <div class="notif-footer border-top text-center py-2">
          <router-link to="/stok/ho" class="btn btn-link btn-sm text-primary" @click="open = false">
            <i class="bi bi-box-seam me-1"></i>Lihat Stok Kritis
          </router-link>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'

const router = useRouter()

const {
  notifications, unreadCount, loading, hasMore,
  fetchNotifications, fetchUnreadCount,
  markRead, markAllRead,
  loadMore, startListening, stopListening, startPolling, stopPolling,
} = useNotifications()

const open      = ref(false)
const activeTab = ref('all')
const bellRef   = ref(null)

// ── Computed ─────────────────────────────────────────────────────────────────

const filteredNotifications = computed(() => {
  if (activeTab.value === 'stock') {
    return notifications.value.filter(n => n.data?.type === 'low_stock')
  }
  return notifications.value
})

const lowStockCount = computed(() =>
  notifications.value.filter(n => n.data?.type === 'low_stock' && !n.read_at).length
)

// ── Toggle & close on outside click ─────────────────────────────────────────

function toggleDropdown() {
  open.value = !open.value
  if (open.value && notifications.value.length === 0) {
    fetchNotifications()
  }
}

function onOutsideClick(e) {
  if (bellRef.value && !bellRef.value.contains(e.target)) {
    open.value = false
  }
}

// ── Actions ──────────────────────────────────────────────────────────────────

async function handleNotifClick(notif) {
  if (!notif.read_at) await markRead(notif.id)
  open.value = false
  if (notif.data?.url) router.push(notif.data.url)
}

async function handleMarkAllRead() {
  await markAllRead()
}

// ── Infinite scroll ───────────────────────────────────────────────────────────

function onScroll(e) {
  const el = e.target
  if (el.scrollHeight - el.scrollTop - el.clientHeight < 60) {
    loadMore()
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d    = new Date(dateStr)
  const now  = new Date()
  const diff = Math.floor((now - d) / 1000)

  if (diff < 60)   return 'Baru saja'
  if (diff < 3600) return `${Math.floor(diff / 60)} mnt lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
  fetchUnreadCount()
  startListening()
  startPolling(60_000) // fallback polling setiap 1 menit
  document.addEventListener('click', onOutsideClick)
})

onUnmounted(() => {
  stopListening()
  stopPolling()
  document.removeEventListener('click', onOutsideClick)
})
</script>

<style scoped>
/* ── Bell & Panel ─────────────────────────────────────────────────────────── */
.notification-bell { display: inline-block; }

.notification-panel {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 360px;
  max-height: 520px;
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.notif-header {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  min-height: 48px;
}

/* ── Tabs ────────────────────────────────────────────────────────────────── */
.notif-tabs { background: #f8fafc; }
.notif-tab {
  flex: 1;
  border: none;
  background: transparent;
  padding: 8px 12px;
  font-size: 0.8rem;
  color: #6b7280;
  cursor: pointer;
  transition: all .15s;
  border-bottom: 2px solid transparent;
}
.notif-tab.active {
  color: #1a3a5c;
  font-weight: 600;
  border-bottom-color: #1a3a5c;
}

/* ── Body (scrollable) ────────────────────────────────────────────────────── */
.notif-body {
  flex: 1;
  overflow-y: auto;
  max-height: 360px;
}

/* ── Items ───────────────────────────────────────────────────────────────── */
.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  transition: background .12s;
  position: relative;
}
.notif-item:hover { background: #f8fafc; }
.notif-item.unread { background: #eff6ff; }
.notif-item.unread:hover { background: #dbeafe; }

.notif-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
}
.notif-icon-wrap.level-minus    { background: #fee2e2; color: #dc2626; }
.notif-icon-wrap.level-critical { background: #fef3c7; color: #d97706; }
.notif-icon-wrap.level-low      { background: #dbeafe; color: #2563eb; }
.notif-icon-wrap.level-info     { background: #e0f2fe; color: #0284c7; }

.notif-content { flex: 1; min-width: 0; }
.notif-title   { font-size: 0.82rem; font-weight: 600; color: #1e293b; line-height: 1.3; }
.notif-message { font-size: 0.77rem; color: #64748b; margin-top: 2px; line-height: 1.4; }
.notif-meta    { font-size: 0.7rem; color: #94a3b8; margin-top: 4px; }

.notif-dot {
  width: 8px;
  height: 8px;
  background: #3b82f6;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 4px;
}

/* ── Empty & Skeleton ────────────────────────────────────────────────────── */
.notif-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #94a3b8;
}
.notif-skeleton {
  height: 56px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 8px;
}
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Footer ──────────────────────────────────────────────────────────────── */
.notif-footer { background: #f8fafc; }

/* ── Transition ──────────────────────────────────────────────────────────── */
.notif-slide-enter-active,
.notif-slide-leave-active { transition: opacity .15s, transform .15s; }
.notif-slide-enter-from,
.notif-slide-leave-to    { opacity: 0; transform: translateY(-8px); }

/* ── Spinner sm ──────────────────────────────────────────────────────────── */
.csm-spinner.sm {
  width: 12px;
  height: 12px;
  border-width: 2px;
}

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 480px) {
  .notification-panel {
    width: calc(100vw - 16px);
    right: -8px;
  }
}
</style>