<!--
  SignerPickerModal.vue
  Modal pilih penandatangan sebelum export PDF.
  Versi Vue (menggantikan SignerPickerModal.jsx).

  Props:
    modelValue  — boolean (v-model) buka/tutup modal
    signers     — array user dari GET /api/users/signable
    maxSigners  — jumlah max slot (default: 3)

  Events:
    update:modelValue — tutup modal
    confirm(ids)      — array user_id yang dipilih, dalam urutan slot

  Contoh pemakaian:
    <SignerPickerModal
      v-model="showModal"
      :signers="signers"
      @confirm="onConfirm"
    />

    function onConfirm(signerIds) {
      // signerIds: [3, 7, 12] — urutan = Dibuat oleh, Diperiksa, Disetujui
      downloadPdf(signerIds)
    }
-->
<template>
  <Teleport to="body">
    <Transition name="fade-modal">
      <div v-if="modelValue" class="signer-overlay" @click.self="$emit('update:modelValue', false)">
        <div class="signer-box">

          <!-- Header -->
          <div class="modal-header border-bottom">
            <h6 class="modal-title fw-bold">
              <i class="bi bi-pen me-2 text-primary"></i>Pilih Penandatangan PDF
            </h6>
            <button type="button" class="btn-close" @click="$emit('update:modelValue', false)"></button>
          </div>

          <!-- Slot yang sudah dipilih -->
          <div class="p-3 pb-1">
            <p class="text-muted small mb-2">
              Pilih hingga <strong>{{ maxSigners }}</strong> penandatangan.
              Urutan menentukan posisi di PDF.
            </p>

            <!-- Slot kosong / terisi -->
            <div class="d-flex flex-column gap-1 mb-3" style="min-height:80px;">
              <div v-if="!selected.length" class="border border-dashed rounded-3 text-center py-3 text-muted small">
                Belum ada penandatangan — PDF akan menggunakan placeholder kosong
              </div>
              <div
                v-for="(u, idx) in selected"
                :key="u.id"
                class="d-flex align-items-center gap-2 px-3 py-2 rounded-3 border"
                :class="slotClass(idx)"
              >
                <span class="fw-semibold small" style="min-width:105px;">{{ SLOT_LABELS[idx] }}</span>
                <span class="flex-fill fw-medium small text-truncate">{{ u.name }}</span>
                <span class="text-muted small d-none d-sm-block text-truncate" style="max-width:120px;">{{ u.position || u.role }}</span>
                <!-- Reorder -->
                <button class="btn btn-xs p-0 px-1 text-muted" @click="moveUp(idx)" :disabled="idx===0">↑</button>
                <button class="btn btn-xs p-0 px-1 text-muted" @click="moveDown(idx)" :disabled="idx===selected.length-1">↓</button>
                <button class="btn btn-xs p-0 px-1 text-danger" @click="remove(u.id)">
                  <i class="bi bi-x"></i>
                </button>
              </div>
            </div>

            <!-- Daftar user tersedia -->
            <p class="small fw-semibold text-muted text-uppercase mb-1" style="font-size:10px;letter-spacing:.5px;">
              User dengan tanda tangan tersedia
            </p>

            <!-- Kosong / loading -->
            <div v-if="!signers.length" class="alert alert-warning py-2 small mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Belum ada user yang mengupload tanda tangan.
              Minta setiap user upload TTD di halaman <strong>Profil</strong>.
            </div>

            <div v-else class="border rounded-3 overflow-auto" style="max-height:210px;">
              <button
                v-for="u in signers"
                :key="u.id"
                type="button"
                class="d-flex align-items-center gap-3 w-100 px-3 py-2 text-start border-bottom border-light"
                :class="isSelected(u.id)
                  ? slotClass(selected.findIndex(s=>s.id===u.id)) + ' border-0'
                  : isDisabled(u.id) ? 'bg-light text-muted' : 'bg-white'"
                :disabled="isDisabled(u.id)"
                @click="toggle(u)"
                style="font-size:12px;"
              >
                <!-- Avatar inisial -->
                <div
                  class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                  :class="isSelected(u.id) ? avatarClass(selected.findIndex(s=>s.id===u.id)) : 'bg-secondary'"
                  style="width:30px;height:30px;font-size:11px;"
                >
                  {{ u.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div class="flex-fill min-w-0">
                  <div class="fw-semibold text-truncate">{{ u.name }}</div>
                  <div class="text-muted" style="font-size:11px;">{{ u.position || u.role }}</div>
                </div>
                <span v-if="u.warehouse" class="text-muted d-none d-sm-block" style="font-size:11px;">{{ u.warehouse }}</span>
                <!-- Slot badge -->
                <span
                  v-if="isSelected(u.id)"
                  class="badge flex-shrink-0"
                  :class="slotBadgeClass(selected.findIndex(s=>s.id===u.id))"
                  style="font-size:10px;"
                >
                  Slot {{ selected.findIndex(s=>s.id===u.id)+1 }}
                </span>
              </button>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer border-top d-flex align-items-center justify-content-between">
            <span class="text-muted small">{{ selected.length }}/{{ maxSigners }} dipilih</span>
            <div class="d-flex gap-2">
              <button class="btn btn-secondary btn-sm" @click="$emit('update:modelValue', false)">Batal</button>
              <button class="btn btn-danger btn-sm" @click="confirm">
                <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  signers:    { type: Array,   default: () => [] },
  maxSigners: { type: Number,  default: 3 },
})

const emit    = defineEmits(['update:modelValue', 'confirm'])
const selected = ref([])

const SLOT_LABELS = ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh']

// Reset pilihan setiap kali modal dibuka
watch(() => props.modelValue, (val) => { if (val) selected.value = [] })

const isSelected = (id) => selected.value.some(u => u.id === id)
const isDisabled = (id) => !isSelected(id) && selected.value.length >= props.maxSigners

function toggle(u) {
  if (isSelected(u.id)) {
    selected.value = selected.value.filter(s => s.id !== u.id)
  } else if (selected.value.length < props.maxSigners) {
    selected.value = [...selected.value, u]
  }
}

function remove(id)    { selected.value = selected.value.filter(u => u.id !== id) }
function moveUp(idx)   { if (idx === 0) return; const a = [...selected.value]; [a[idx-1],a[idx]]=[a[idx],a[idx-1]]; selected.value=a }
function moveDown(idx) { const a=[...selected.value]; if(idx>=a.length-1)return; [a[idx],a[idx+1]]=[a[idx+1],a[idx]]; selected.value=a }

function confirm() {
  emit('confirm', selected.value.map(u => u.id))
  emit('update:modelValue', false)
}

// Styling per slot
const SLOT_CLASSES   = ['bg-primary bg-opacity-10 border-primary',    'bg-purple bg-opacity-10 border-secondary', 'bg-success bg-opacity-10 border-success']
const AVATAR_CLASSES = ['bg-primary', 'bg-secondary',  'bg-success']
const BADGE_CLASSES  = ['bg-primary', 'bg-secondary',  'bg-success']

const slotClass      = (idx) => SLOT_CLASSES[idx]   ?? 'bg-light'
const avatarClass    = (idx) => AVATAR_CLASSES[idx] ?? 'bg-secondary'
const slotBadgeClass = (idx) => BADGE_CLASSES[idx]  ?? 'bg-secondary'
</script>

<style scoped>
.signer-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 1060;
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.signer-box {
  background: #fff;
  border-radius: 12px;
  width: 100%; max-width: 520px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  max-height: 90vh;
  overflow-y: auto;
}
.fade-modal-enter-active, .fade-modal-leave-active { transition: opacity .2s ease; }
.fade-modal-enter-from,   .fade-modal-leave-to     { opacity: 0; }
button:disabled { cursor: not-allowed; }
</style>
