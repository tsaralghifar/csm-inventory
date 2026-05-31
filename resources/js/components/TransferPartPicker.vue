<template>
  <div class="tp-picker">
    <label class="form-label fw-semibold">
      <i class="bi bi-lightning-charge text-warning me-1"></i>
      Pengganti Transfer Part Darurat
      <span class="text-muted fw-normal small ms-1">(opsional)</span>
    </label>

    <!-- Sudah dipilih -->
    <div v-if="selected" class="tp-picker__selected">
      <div class="tp-picker__selected-badge">
        <i class="bi bi-lightning-charge-fill text-warning"></i>
        <div class="tp-picker__selected-info">
          <div class="fw-semibold font-monospace">{{ selected.mr_number }}</div>
          <div class="small text-muted">
            Unit <strong>{{ selected.unit_from_kode }}</strong>
            <i class="bi bi-arrow-right mx-1"></i>
            <strong>{{ selected.unit_to_kode }}</strong>
            &nbsp;·&nbsp;{{ selected.from_warehouse?.name }} → {{ selected.to_warehouse?.name }}
          </div>
        </div>
        <button type="button" @click="clear" class="btn btn-sm btn-link text-danger p-0 ms-auto">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <!-- Preview items dari TP yang bisa di-autofill -->
      <div v-if="selected.items?.length" class="tp-picker__items-preview">
        <div class="small text-muted mb-1">Part yang dilepas (bisa di-autofill ke daftar PM):</div>
        <div v-for="item in selected.items" :key="item.item_id" class="tp-picker__item-row">
          <span class="font-monospace text-muted small">{{ item.part_number }}</span>
          <span class="mx-1">{{ item.nama_barang }}</span>
          <span class="badge bg-light text-dark">{{ item.qty }} {{ item.satuan }}</span>
        </div>
        <button type="button" @click="$emit('autofill', selected.items)" class="btn btn-sm btn-outline-warning mt-2 w-100">
          <i class="bi bi-magic me-1"></i> Autofill daftar part dari Transfer ini
        </button>
      </div>
    </div>

    <!-- Belum dipilih -->
    <div v-else>
      <button
        type="button"
        @click="toggle"
        class="btn btn-outline-secondary btn-sm w-100 text-start"
      >
        <i class="bi bi-lightning-charge me-1"></i>
        {{ open ? 'Tutup daftar...' : 'Pilih Transfer Part yang perlu diganti' }}
        <i :class="open ? 'bi-chevron-up' : 'bi-chevron-down'" class="bi float-end mt-1"></i>
      </button>

      <div v-if="open" class="tp-picker__dropdown">
        <div v-if="loading" class="tp-picker__empty">
          <div class="spinner-border spinner-border-sm text-secondary me-2"></div> Memuat...
        </div>
        <div v-else-if="!options.length" class="tp-picker__empty">
          <i class="bi bi-check-circle text-success me-1"></i>
          Tidak ada Transfer Part yang menunggu pengganti
        </div>
        <div
          v-for="tp in options"
          :key="tp.id"
          @click="select(tp)"
          class="tp-picker__option"
        >
          <div class="d-flex justify-content-between align-items-start">
            <span class="font-monospace fw-semibold text-warning small">{{ tp.mr_number }}</span>
            <span class="text-muted small">{{ formatDate(tp.created_at) }}</span>
          </div>
          <div class="small mt-1">
            <i class="bi bi-box-arrow-up-right text-muted me-1"></i>
            Unit <strong>{{ tp.unit_from_kode }}</strong>
            <span v-if="tp.unit_from_tipe" class="text-muted">({{ tp.unit_from_tipe }})</span>
            <i class="bi bi-arrow-right mx-2 text-muted"></i>
            Unit <strong>{{ tp.unit_to_kode }}</strong>
            <span v-if="tp.unit_to_tipe" class="text-muted">({{ tp.unit_to_tipe }})</span>
          </div>
          <div class="small text-muted mt-1 fst-italic text-truncate">{{ tp.alasan_urgent }}</div>
          <div class="small text-muted mt-1">
            <i class="bi bi-boxes me-1"></i>
            {{ tp.items?.length }} part &nbsp;·&nbsp;
            {{ tp.from_warehouse?.name }} → {{ tp.to_warehouse?.name }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({ modelValue: { type: [Number, null], default: null } })
const emit  = defineEmits(['update:modelValue', 'autofill'])

const open     = ref(false)
const loading  = ref(false)
const options  = ref([])
const selected = ref(null)

onMounted(fetchOptions)

async function fetchOptions() {
  loading.value = true
  try {
    const { data } = await axios.get('/transfer-part/unlinked-pm')
    options.value = data.data ?? []
    // Kalau sudah ada modelValue, cari yang sudah dipilih
    if (props.modelValue) {
      selected.value = options.value.find(tp => tp.id === props.modelValue) ?? null
    }
  } finally {
    loading.value = false
  }
}

function toggle() {
  open.value = !open.value
  if (open.value && !options.value.length) fetchOptions()
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
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<style scoped>
.tp-picker { margin-bottom: 1rem; }

.tp-picker__selected {
  border: 1.5px solid #ffc107;
  border-radius: 8px;
  background: #fffdf0;
  padding: 10px 12px;
}

.tp-picker__selected-badge {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.tp-picker__selected-info { flex: 1; }

.tp-picker__items-preview {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed #ffc107;
}

.tp-picker__item-row {
  padding: 3px 0;
  font-size: 0.82rem;
  color: #555;
}

.tp-picker__dropdown {
  border: 1px solid #dee2e6;
  border-radius: 8px;
  margin-top: 4px;
  max-height: 280px;
  overflow-y: auto;
  background: white;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.tp-picker__option {
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid #f0f0f0;
  transition: background 0.15s;
}

.tp-picker__option:last-child { border-bottom: none; }
.tp-picker__option:hover { background: #fffdf0; }

.tp-picker__empty {
  padding: 16px;
  text-align: center;
  color: #888;
  font-size: 0.85rem;
}
</style>