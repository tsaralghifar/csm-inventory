<template>
  <router-view />
</template>

<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@/store/auth'

const auth = useAuthStore()

onMounted(async () => {
  if (localStorage.getItem('csm_token') && !auth.user) {
    try { await auth.fetchUser() } catch {}
  }
})
</script>
