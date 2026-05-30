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
          <div class="signer-header">
            <div class="header-icon-wrap">
              <i class="bi bi-pen-fill"></i>
            </div>
            <div>
              <h6 class="header-title">Pilih Penandatangan PDF</h6>
              <p class="header-sub">Pilih hingga <strong>{{ maxSigners }}</strong> penandatangan. Urutan menentukan posisi di PDF.</p>
            </div>
            <button class="close-btn" @click="$emit('update:modelValue', false)">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>

          <div class="signer-body">

            <!-- Slot Preview -->
            <div class="slot-section">
              <div class="section-label">
                <i class="bi bi-layout-three-columns me-1"></i>
                Urutan Tanda Tangan
              </div>

              <div class="slots-container">
                <TransitionGroup name="slot-anim" tag="div" class="slots-list">
                  <div
                    v-for="(u, idx) in filledSlots"
                    :key="'slot-' + idx"
                    class="slot-card"
                    :class="'slot-' + idx"
                  >
                    <div class="slot-number">{{ idx + 1 }}</div>
                    <div class="slot-info" v-if="u">
                      <div class="slot-avatar" :class="'avatar-' + idx">
                        {{ u.name?.charAt(0)?.toUpperCase() }}
                      </div>
                      <div class="slot-text">
                        <div class="slot-label-sm">{{ SLOT_LABELS[idx] }}</div>
                        <div class="slot-name">{{ u.name }}</div>
                        <div class="slot-role">{{ u.position || u.role }}</div>
                      </div>
                      <div class="slot-actions">
                        <button class="slot-btn" @click="moveUp(idx)" :disabled="idx === 0" title="Naik">
                          <i class="bi bi-chevron-up"></i>
                        </button>
                        <button class="slot-btn" @click="moveDown(idx)" :disabled="idx >= selected.length - 1" title="Turun">
                          <i class="bi bi-chevron-down"></i>
                        </button>
                        <button class="slot-btn slot-btn-remove" @click="remove(u.id)" title="Hapus">
                          <i class="bi bi-x"></i>
                        </button>
                      </div>
                    </div>
                    <div class="slot-empty" v-else>
                      <div class="slot-avatar-empty">
                        <i class="bi bi-person-dash"></i>
                      </div>
                      <div class="slot-text">
                        <div class="slot-label-sm">{{ SLOT_LABELS[idx] }}</div>
                        <div class="slot-empty-hint">Belum dipilih</div>
                      </div>
                    </div>
                  </div>
                </TransitionGroup>
              </div>
            </div>

            <!-- Divider -->
            <div class="section-divider"></div>

            <!-- User List -->
            <div class="users-section">
              <div class="section-label">
                <i class="bi bi-people-fill me-1"></i>
                User dengan Tanda Tangan Tersedia
              </div>

              <div v-if="!signers.length" class="empty-state">
                <div class="empty-icon"><i class="bi bi-exclamation-circle"></i></div>
                <div class="empty-text">Belum ada user yang mengupload tanda tangan.</div>
                <div class="empty-sub">Minta setiap user upload TTD di halaman <strong>Profil</strong>.</div>
              </div>

              <div v-else class="users-list">
                <button
                  v-for="u in signers"
                  :key="u.id"
                  type="button"
                  class="user-row"
                  :class="{
                    'user-selected': isSelected(u.id),
                    ['user-slot-' + selected.findIndex(s => s.id === u.id)]: isSelected(u.id),
                    'user-disabled': isDisabled(u.id),
                  }"
                  :disabled="isDisabled(u.id)"
                  @click="toggle(u)"
                >
                  <div class="user-avatar"
                    :class="isSelected(u.id) ? 'avatar-' + selected.findIndex(s => s.id === u.id) : 'avatar-default'"
                  >
                    {{ u.name?.charAt(0)?.toUpperCase() }}
                  </div>
                  <div class="user-info">
                    <div class="user-name">{{ u.name }}</div>
                    <div class="user-meta">{{ u.position || u.role }}<span v-if="u.warehouse" class="user-warehouse"> · {{ u.warehouse }}</span></div>
                  </div>
                  <div class="user-badge-wrap">
                    <span v-if="isSelected(u.id)" class="slot-badge" :class="'badge-slot-' + selected.findIndex(s => s.id === u.id)">
                      <i class="bi bi-check2 me-1"></i>Slot {{ selected.findIndex(s => s.id === u.id) + 1 }}
                    </span>
                    <span v-else-if="!isDisabled(u.id)" class="add-hint">
                      <i class="bi bi-plus"></i>
                    </span>
                  </div>
                </button>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="signer-footer">
            <div class="progress-info">
              <div class="progress-dots">
                <span
                  v-for="n in maxSigners"
                  :key="n"
                  class="progress-dot"
                  :class="{ 'dot-filled': n <= selected.length, ['dot-color-' + (n - 1)]: n <= selected.length }"
                ></span>
              </div>
              <span class="progress-text">{{ selected.length }}/{{ maxSigners }} dipilih</span>
            </div>
            <div class="footer-actions">
              <button class="btn-cancel" @click="$emit('update:modelValue', false)">Batal</button>
              <button class="btn-confirm" @click="confirm">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i>Export PDF
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  signers:    { type: Array,   default: () => [] },
  maxSigners: { type: Number,  default: 3 },
})

const emit     = defineEmits(['update:modelValue', 'confirm'])
const selected = ref([])

const SLOT_LABELS = ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh']

// Filled slots — always show maxSigners rows (filled + empty placeholders)
const filledSlots = computed(() => {
  const arr = [...selected.value]
  while (arr.length < props.maxSigners) arr.push(null)
  return arr
})

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
</script>

<style scoped>
/* ── Overlay & Box ─────────────────────────────────────────────── */
.signer-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1060;
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.signer-box {
  background: #fff;
  border-radius: 16px;
  width: 100%; max-width: 540px;
  box-shadow: 0 24px 80px rgba(15,23,42,.22), 0 4px 16px rgba(15,23,42,.08);
  max-height: 90vh;
  display: flex; flex-direction: column;
  overflow: hidden;
}

/* ── Header ────────────────────────────────────────────────────── */
.signer-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 20px 20px 16px;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
  border-radius: 16px 16px 0 0;
}
.header-icon-wrap {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 16px;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(99,102,241,.35);
}
.header-title {
  font-size: 15px; font-weight: 700;
  color: #0f172a; margin: 0 0 2px;
}
.header-sub {
  font-size: 12px; color: #64748b; margin: 0;
}
.header-sub strong { color: #3b82f6; }
.close-btn {
  margin-left: auto; background: none; border: none;
  width: 30px; height: 30px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  color: #94a3b8; font-size: 14px;
  cursor: pointer; transition: all .15s;
  flex-shrink: 0;
}
.close-btn:hover { background: #f1f5f9; color: #475569; }

/* ── Body ──────────────────────────────────────────────────────── */
.signer-body {
  flex: 1; overflow-y: auto;
  padding: 0;
}

.section-label {
  font-size: 10.5px; font-weight: 700;
  letter-spacing: .6px; text-transform: uppercase;
  color: #94a3b8; margin-bottom: 10px;
}

/* ── Slot Section ──────────────────────────────────────────────── */
.slot-section { padding: 16px 20px 12px; }

.slots-list { display: flex; flex-direction: column; gap: 6px; }

.slot-card {
  display: flex; align-items: center;
  gap: 10px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  padding: 10px 12px;
  background: #fafafa;
  transition: all .2s;
}

/* Slot colors */
.slot-0 { border-color: #bfdbfe; background: #eff6ff; }
.slot-1 { border-color: #c4b5fd; background: #f5f3ff; }
.slot-2 { border-color: #a7f3d0; background: #f0fdf4; }

.slot-number {
  width: 22px; height: 22px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #64748b;
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.slot-0 .slot-number { background: #3b82f6; color: #fff; }
.slot-1 .slot-number { background: #8b5cf6; color: #fff; }
.slot-2 .slot-number { background: #10b981; color: #fff; }

.slot-info, .slot-empty {
  display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;
}

.slot-avatar {
  width: 32px; height: 32px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; color: #fff;
  flex-shrink: 0;
}
.avatar-0 { background: linear-gradient(135deg, #3b82f6, #6366f1); }
.avatar-1 { background: linear-gradient(135deg, #8b5cf6, #a855f7); }
.avatar-2 { background: linear-gradient(135deg, #10b981, #059669); }
.avatar-default { background: linear-gradient(135deg, #94a3b8, #64748b); }

.slot-avatar-empty {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: #e2e8f0;
  display: flex; align-items: center; justify-content: center;
  color: #94a3b8; font-size: 14px;
  flex-shrink: 0;
}

.slot-text { flex: 1; min-width: 0; }
.slot-label-sm { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; }
.slot-name { font-size: 13px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.slot-role { font-size: 11px; color: #64748b; }
.slot-empty-hint { font-size: 12px; color: #cbd5e1; font-style: italic; }

.slot-actions { display: flex; gap: 2px; flex-shrink: 0; }
.slot-btn {
  width: 24px; height: 24px;
  background: none; border: none;
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  color: #94a3b8; font-size: 11px;
  cursor: pointer; transition: all .15s;
}
.slot-btn:hover:not(:disabled) { background: #f1f5f9; color: #475569; }
.slot-btn:disabled { opacity: .3; cursor: not-allowed; }
.slot-btn-remove:hover:not(:disabled) { background: #fef2f2; color: #ef4444; }

/* ── Divider ────────────────────────────────────────────────────── */
.section-divider { height: 1px; background: #f1f5f9; margin: 0 20px; }

/* ── Users Section ─────────────────────────────────────────────── */
.users-section { padding: 14px 20px 4px; }

.users-list {
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  max-height: 220px;
  overflow-y: auto;
}

.user-row {
  display: flex; align-items: center; gap: 10px;
  width: 100%; padding: 10px 14px;
  background: #fff; border: none;
  border-bottom: 1px solid #f1f5f9;
  text-align: left; cursor: pointer;
  transition: all .15s;
}
.user-row:last-child { border-bottom: none; }
.user-row:hover:not(.user-disabled):not(.user-selected) { background: #f8faff; }

.user-row.user-selected { cursor: pointer; }
.user-row.user-slot-0 { background: #eff6ff; border-left: 3px solid #3b82f6; }
.user-row.user-slot-1 { background: #f5f3ff; border-left: 3px solid #8b5cf6; }
.user-row.user-slot-2 { background: #f0fdf4; border-left: 3px solid #10b981; }
.user-row.user-disabled { opacity: .4; cursor: not-allowed; }

.user-avatar {
  width: 34px; height: 34px;
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; color: #fff;
  flex-shrink: 0;
}

.user-info { flex: 1; min-width: 0; }
.user-name { font-size: 13px; font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-meta { font-size: 11px; color: #64748b; }
.user-warehouse { color: #94a3b8; }

.user-badge-wrap { flex-shrink: 0; }
.slot-badge {
  display: inline-flex; align-items: center;
  font-size: 10px; font-weight: 600;
  padding: 2px 8px; border-radius: 99px;
}
.badge-slot-0 { background: #dbeafe; color: #1d4ed8; }
.badge-slot-1 { background: #ede9fe; color: #6d28d9; }
.badge-slot-2 { background: #d1fae5; color: #065f46; }

.add-hint {
  width: 24px; height: 24px;
  border-radius: 6px;
  background: #f1f5f9;
  display: flex; align-items: center; justify-content: center;
  color: #94a3b8; font-size: 16px;
  transition: all .15s;
}
.user-row:hover .add-hint { background: #dbeafe; color: #3b82f6; }

/* ── Empty State ───────────────────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 24px 20px;
  border: 1.5px dashed #e2e8f0;
  border-radius: 10px;
  background: #fafafa;
}
.empty-icon { font-size: 28px; color: #f59e0b; margin-bottom: 8px; }
.empty-text { font-size: 13px; font-weight: 600; color: #475569; }
.empty-sub { font-size: 12px; color: #94a3b8; margin-top: 4px; }
.empty-sub strong { color: #3b82f6; }

/* ── Footer ────────────────────────────────────────────────────── */
.signer-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px;
  border-top: 1px solid #f1f5f9;
  background: #fafafa;
  border-radius: 0 0 16px 16px;
}
.progress-info { display: flex; align-items: center; gap: 8px; }
.progress-dots { display: flex; gap: 4px; }
.progress-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: #e2e8f0; transition: all .2s;
}
.dot-color-0 { background: #3b82f6; }
.dot-color-1 { background: #8b5cf6; }
.dot-color-2 { background: #10b981; }
.progress-text { font-size: 12px; color: #64748b; font-weight: 500; }

.footer-actions { display: flex; gap: 8px; }
.btn-cancel {
  padding: 8px 16px;
  border: 1.5px solid #e2e8f0;
  background: #fff; border-radius: 8px;
  font-size: 13px; font-weight: 500; color: #64748b;
  cursor: pointer; transition: all .15s;
}
.btn-cancel:hover { background: #f8faff; border-color: #cbd5e1; color: #475569; }

.btn-confirm {
  padding: 8px 18px;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  border: none; border-radius: 8px;
  font-size: 13px; font-weight: 600; color: #fff;
  cursor: pointer; transition: all .15s;
  box-shadow: 0 2px 8px rgba(239,68,68,.35);
  display: flex; align-items: center;
}
.btn-confirm:hover {
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  box-shadow: 0 4px 14px rgba(239,68,68,.45);
  transform: translateY(-1px);
}
.btn-confirm:active { transform: translateY(0); }

/* ── Transitions ───────────────────────────────────────────────── */
.fade-modal-enter-active, .fade-modal-leave-active { transition: opacity .2s ease; }
.fade-modal-enter-from, .fade-modal-leave-to { opacity: 0; }

.slot-anim-enter-active { transition: all .25s ease; }
.slot-anim-leave-active { transition: all .2s ease; }
.slot-anim-enter-from { opacity: 0; transform: translateY(-6px); }
.slot-anim-leave-to   { opacity: 0; transform: translateX(10px); }

/* ── Scrollbar ─────────────────────────────────────────────────── */
.users-list::-webkit-scrollbar { width: 4px; }
.users-list::-webkit-scrollbar-track { background: transparent; }
.users-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 99px; }
.signer-body::-webkit-scrollbar { width: 4px; }
.signer-body::-webkit-scrollbar-track { background: transparent; }
.signer-body::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 99px; }

button:disabled { cursor: not-allowed; }
</style>