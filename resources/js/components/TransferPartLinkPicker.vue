<template>
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">
      Link ke Transfer Part Darurat
      <span class="text-gray-400 font-normal text-xs ml-1">(opsional — jika PO ini untuk mengganti part yang dipinjam)</span>
    </label>

    <!-- Sudah dipilih -->
    <div v-if="selected" class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
      <span class="text-amber-500 text-sm">⚡</span>
      <div class="flex-1">
        <div class="font-mono text-sm font-medium text-amber-800">{{ selected.mr_number }}</div>
        <div class="text-xs text-amber-600">
          Unit {{ selected.unit_from_kode }} → {{ selected.unit_to_kode }}
          &nbsp;·&nbsp;{{ selected.items?.length }} part
        </div>
      </div>
      <button @click="clear" type="button" class="text-amber-400 hover:text-amber-600 text-sm">✕</button>
    </div>

    <!-- Belum dipilih -->
    <div v-else>
      <button
        type="button"
        @click="open = !open"
        class="w-full flex items-center justify-between border rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-50"
      >
        <span>Pilih Transfer Part (opsional)</span>
        <span>{{ open ? '▲' : '▼' }}</span>
      </button>

      <!-- Dropdown list -->
      <div v-if="open" class="border rounded-lg mt-1 bg-white shadow-sm max-h-52 overflow-y-auto">
        <div v-if="loading" class="px-3 py-3 text-xs text-gray-400 text-center">Memuat...</div>
        <div v-else-if="!options.length" class="px-3 py-3 text-xs text-gray-400 text-center">
          Tidak ada Transfer Part yang menunggu PO pengganti
        </div>
        <div
          v-for="tp in options"
          :key="tp.id"
          @click="select(tp)"
          class="px-3 py-2 hover:bg-amber-50 cursor-pointer border-b last:border-0"
        >
          <div class="flex items-center justify-between">
            <span class="font-mono text-xs font-medium text-amber-700">{{ tp.mr_number }}</span>
            <span class="text-xs text-gray-400">{{ formatDate(tp.created_at) }}</span>
          </div>
          <div class="text-xs text-gray-600 mt-0.5">
            <span class="bg-gray-100 px-1 rounded">{{ tp.unit_from_kode }}</span>
            <span class="mx-1 text-gray-400">→</span>
            <span class="bg-gray-100 px-1 rounded">{{ tp.unit_to_kode }}</span>
            <span class="ml-2 text-gray-400">{{ tp.from_warehouse?.name }} → {{ tp.to_warehouse?.name }}</span>
          </div>
          <div class="text-xs text-amber-600 italic mt-0.5 truncate">{{ tp.alasan_urgent }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

const props  = defineProps({ modelValue: [Number, null] })
const emit   = defineEmits(['update:modelValue'])

const open    = ref(false)
const loading = ref(false)
const options = ref([])
const selected = ref(null)

onMounted(fetchOptions)

watch(open, (val) => { if (val && !options.value.length) fetchOptions() })

async function fetchOptions() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/transfer-part/unlinked')
    options.value = data.data
  } finally {
    loading.value = false
  }
}

function select(tp) {
  selected.value = tp
  open.value = false
  emit('update:modelValue', tp.id)
}

function clear() {
  selected.value = null
  emit('update:modelValue', null)
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
}
</script>
