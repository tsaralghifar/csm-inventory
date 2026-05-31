<!--
  SignerPickerModal.vue — Versi 3 (TTD Bertahap + Finalisasi)

  Props:
    modelValue  — boolean v-model buka/tutup
    slots       — array 3 elemen dari useSignerPicker (null = slot kosong)
    isFinalized — boolean: apakah dokumen sudah dikunci
    loading     — boolean: sedang load data dari server
    actionLoading — boolean: sedang proses addSlot / finalize

  Events:
    update:modelValue — tutup modal
    add-slot(n)       — user minta tambah TTD di slot n (1-3)
    finalize          — user klik Finalisasi
    print             — user klik Print PDF
-->
<template>
  <Teleport to="body">
    <Transition name="fade-modal">
      <div v-if="modelValue" class="signer-overlay" @click.self="close">
        <div class="signer-box">

          <!-- ── Header ── -->
          <div class="signer-header">
            <div class="header-icon-wrap">
              <i class="bi bi-pen-fill"></i>
            </div>
            <div>
              <h6 class="header-title">Tanda Tangan Dokumen</h6>
              <p class="header-sub" v-if="!isFinalized">
                Tambahkan TTD Anda ke salah satu slot, lalu Finalisasi untuk mengunci.
              </p>
              <p class="header-sub finalized-sub" v-else>
                <i class="bi bi-lock-fill me-1"></i>
                Dokumen telah difinalisasi — tanda tangan tidak dapat diubah.
              </p>
            </div>
            <button class="close-btn" @click="close">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>

          <!-- ── Body ── -->
          <div class="signer-body">

            <!-- Loading skeleton -->
            <div v-if="loading" class="loading-state">
              <div class="spinner-border spinner-border-sm text-primary me-2"></div>
              Memuat status tanda tangan...
            </div>

            <template v-else>

              <!-- Slot cards -->
              <div class="slots-section">
                <div class="section-label">
                  <i class="bi bi-layout-three-columns me-1"></i>
                  Status Tanda Tangan
                </div>

                <div class="slots-list">
                  <div
                    v-for="(slot, idx) in slots"
                    :key="idx"
                    class="slot-card"
                    :class="{
                      'slot-filled': slot !== null,
                      'slot-empty': slot === null,
                      ['slot-color-' + idx]: true,
                    }"
                  >
                    <!-- Nomor slot -->
                    <div class="slot-num" :class="'num-' + idx">{{ idx + 1 }}</div>

                    <!-- Isi slot -->
                    <div class="slot-content" v-if="slot">
                      <div class="slot-avatar" :class="'avatar-' + idx">
                        {{ slot.name?.charAt(0)?.toUpperCase() }}
                      </div>
                      <div class="slot-text">
                        <div class="slot-label-sm">{{ SLOT_LABELS[idx] }}</div>
                        <div class="slot-name">{{ slot.name }}</div>
                        <div class="slot-role">{{ slot.position }}</div>
                      </div>
                      <div class="slot-badge-wrap">
                        <span class="slot-badge-done">
                          <i class="bi bi-check-circle-fill me-1"></i>Ditandatangani
                        </span>
                        <span class="slot-date">{{ formatDate(slot.signed_at) }}</span>
                      </div>
                    </div>

                    <!-- Slot kosong -->
                    <div class="slot-content slot-empty-content" v-else>
                      <div class="slot-avatar avatar-empty">
                        <i class="bi bi-person-dash"></i>
                      </div>
                      <div class="slot-text">
                        <div class="slot-label-sm">{{ SLOT_LABELS[idx] }}</div>
                        <div class="slot-name-empty">Belum ditandatangani</div>
                      </div>
                      <!-- Tombol tambah TTD (hanya jika belum finalized) -->
                      <button
                        v-if="!isFinalized"
                        class="btn-add-slot"
                        :disabled="actionLoading"
                        @click="$emit('add-slot', idx + 1)"
                        :title="`Tambahkan TTD Anda ke slot ${idx + 1}`"
                      >
                        <span v-if="actionLoading" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="bi bi-plus-circle me-1"></i>
                        Tambah TTD Saya
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Banner finalized -->
              <div v-if="isFinalized" class="finalized-banner">
                <i class="bi bi-shield-lock-fill me-2"></i>
                <div>
                  <strong>Dokumen dikunci secara permanen.</strong>
                  <span v-if="finalizedAt"> Difinalisasi pada {{ formatDate(finalizedAt) }}.</span>
                </div>
              </div>

              <!-- Info belum ada TTD sama sekali -->
              <div v-if="!isFinalized && filledCount === 0" class="no-ttd-info">
                <i class="bi bi-info-circle me-2"></i>
                Belum ada tanda tangan. Klik <strong>"Tambah TTD Saya"</strong> di slot yang diinginkan, atau klik <strong>"Preview PDF"</strong> untuk melihat dokumen tanpa tanda tangan.
              </div>

            </template>
          </div>

          <!-- ── Footer ── -->
          <div class="signer-footer">
            <div class="footer-left">
              <span class="slot-count">{{ filledCount }}/3 ditandatangani</span>
            </div>
            <div class="footer-right">
              <button class="btn-cancel" @click="close" :disabled="actionLoading">
                Tutup
              </button>

              <!-- Finalisasi — hanya jika ada TTD dan belum final dan berwenang -->
              <button
                v-if="!isFinalized && filledCount > 0 && canFinalize"
                class="btn-finalize"
                :disabled="actionLoading"
                @click="$emit('finalize')"
              >
                <span v-if="actionLoading" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-lock-fill me-1"></i>
                Finalisasi
              </button>

              <!-- Print PDF -->
              <button
                class="btn-print"
                :disabled="loading || actionLoading"
                @click="$emit('print')"
              >
                <i class="bi bi-file-earmark-pdf-fill me-1"></i>
                {{ filledCount === 0 ? 'Preview PDF' : 'Export PDF' }}
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/store/auth'

const props = defineProps({
  modelValue:    { type: Boolean, default: false },
  slots:         { type: Array,   default: () => [null, null, null] },
  isFinalized:   { type: Boolean, default: false },
  finalizedAt:   { type: String,  default: null },
  loading:       { type: Boolean, default: false },
  actionLoading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'add-slot', 'finalize', 'print'])

const SLOT_LABELS = ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh']

const auth = useAuthStore()

const filledCount = computed(() => props.slots.filter(s => s !== null).length)

const canFinalize = computed(() =>
  auth.isSuperuser ||
  auth.hasRole('admin_ho') ||
  auth.hasRole('manager') ||
  auth.hasRole('logistik_ho')
)

function close() {
  emit('update:modelValue', false)
}

function formatDate(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleDateString('id-ID', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
  } catch { return iso }
}
</script>

<style scoped>
/* ── Overlay & Box ─────────────────────────────────────────────────────────── */
.signer-overlay {
  position: fixed; inset: 0; z-index: 1060;
  background: rgba(15,23,42,.45);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.signer-box {
  background: #fff; border-radius: 16px;
  width: 100%; max-width: 560px;
  box-shadow: 0 24px 64px rgba(0,0,0,.18);
  display: flex; flex-direction: column;
  max-height: 90vh; overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.signer-header {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 20px 20px 16px; border-bottom: 1px solid #f1f5f9;
}
.header-icon-wrap {
  width: 40px; height: 40px; border-radius: 10px;
  background: linear-gradient(135deg,#6366f1,#8b5cf6);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 17px; flex-shrink: 0;
}
.header-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 2px; }
.header-sub   { font-size: 12px; color: #64748b; margin: 0; }
.finalized-sub { color: #0f766e; font-weight: 500; }
.close-btn { margin-left: auto; border: none; background: none; color: #94a3b8; font-size: 16px; cursor: pointer; padding: 4px; border-radius: 6px; }
.close-btn:hover { background: #f1f5f9; color: #475569; }

/* ── Body ────────────────────────────────────────────────────────────────────── */
.signer-body { padding: 16px 20px; overflow-y: auto; flex: 1; }

.loading-state { display: flex; align-items: center; color: #64748b; padding: 24px 0; font-size: 14px; }

/* ── Section label ─────────────────────────────────────────────────────────── */
.section-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 10px; display: flex; align-items: center; }

/* ── Slot cards ─────────────────────────────────────────────────────────────── */
.slots-list { display: flex; flex-direction: column; gap: 8px; }

.slot-card {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; border-radius: 12px;
  border: 1.5px solid #e2e8f0; transition: all .2s;
}
.slot-filled { border-color: transparent; }
.slot-color-0.slot-filled { background: #eff6ff; border-color: #bfdbfe; }
.slot-color-1.slot-filled { background: #faf5ff; border-color: #e9d5ff; }
.slot-color-2.slot-filled { background: #f0fdf4; border-color: #bbf7d0; }

.slot-num {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.num-0 { background: #2563eb; color: #fff; }
.num-1 { background: #7c3aed; color: #fff; }
.num-2 { background: #059669; color: #fff; }

.slot-content { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; }
.slot-empty-content { flex-wrap: wrap; }

.slot-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700; flex-shrink: 0;
}
.avatar-0 { background: #dbeafe; color: #1d4ed8; }
.avatar-1 { background: #ede9fe; color: #6d28d9; }
.avatar-2 { background: #d1fae5; color: #065f46; }
.avatar-empty { background: #f1f5f9; color: #94a3b8; font-size: 16px; }

.slot-text { flex: 1; min-width: 0; }
.slot-label-sm { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .6px; }
.slot-name { font-size: 13px; font-weight: 600; color: #0f172a; }
.slot-role { font-size: 11px; color: #64748b; }
.slot-name-empty { font-size: 13px; color: #94a3b8; font-style: italic; }

.slot-badge-wrap { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.slot-badge-done { font-size: 10px; font-weight: 600; color: #059669; background: #d1fae5; border-radius: 99px; padding: 2px 8px; white-space: nowrap; }
.slot-date { font-size: 10px; color: #94a3b8; }

.btn-add-slot {
  display: flex; align-items: center; gap: 4px;
  padding: 5px 12px; border-radius: 8px;
  border: 1.5px dashed #6366f1; background: #fff;
  color: #6366f1; font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all .15s; white-space: nowrap; flex-shrink: 0;
}
.btn-add-slot:hover:not(:disabled) { background: #eff0ff; }
.btn-add-slot:disabled { opacity: .5; cursor: not-allowed; }

/* ── Banners ─────────────────────────────────────────────────────────────────── */
.finalized-banner {
  display: flex; align-items: center; gap: 10px;
  background: #f0fdf4; border: 1px solid #bbf7d0;
  border-radius: 10px; padding: 12px 14px;
  color: #065f46; font-size: 13px; margin-top: 12px;
}
.no-ttd-info {
  display: flex; align-items: center;
  background: #eff6ff; border: 1px solid #bfdbfe;
  border-radius: 10px; padding: 10px 14px;
  color: #1e40af; font-size: 12px; margin-top: 12px;
}

/* ── Footer ─────────────────────────────────────────────────────────────────── */
.signer-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px; border-top: 1px solid #f1f5f9;
  gap: 8px;
}
.footer-right { display: flex; align-items: center; gap: 8px; }
.slot-count { font-size: 12px; color: #94a3b8; font-weight: 500; }

.btn-cancel {
  padding: 7px 16px; border-radius: 8px;
  border: 1.5px solid #e2e8f0; background: #fff;
  color: #475569; font-size: 13px; font-weight: 600;
  cursor: pointer; transition: all .15s;
}
.btn-cancel:hover:not(:disabled) { background: #f8fafc; }

.btn-finalize {
  display: flex; align-items: center;
  padding: 7px 16px; border-radius: 8px;
  border: none; background: #f59e0b; color: #fff;
  font-size: 13px; font-weight: 600;
  cursor: pointer; transition: all .15s;
}
.btn-finalize:hover:not(:disabled) { background: #d97706; }
.btn-finalize:disabled { opacity: .5; cursor: not-allowed; }

.btn-print {
  display: flex; align-items: center;
  padding: 7px 18px; border-radius: 8px;
  border: none; background: #dc2626; color: #fff;
  font-size: 13px; font-weight: 600;
  cursor: pointer; transition: all .15s;
}
.btn-print:hover:not(:disabled) { background: #b91c1c; }
.btn-print:disabled { opacity: .5; cursor: not-allowed; }

/* ── Transitions ─────────────────────────────────────────────────────────────── */
.fade-modal-enter-active, .fade-modal-leave-active { transition: all .2s ease; }
.fade-modal-enter-from, .fade-modal-leave-to { opacity: 0; }
.fade-modal-enter-from .signer-box, .fade-modal-leave-to .signer-box { transform: scale(.96) translateY(8px); }
</style>