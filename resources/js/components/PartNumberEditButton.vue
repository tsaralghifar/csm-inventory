<template>
  <!-- ─── Tombol edit inline di tabel item PO ─────────────────────────────── -->
  <button
    v-if="canEdit"
    class="btn btn-link btn-sm p-0 ms-1 text-secondary"
    title="Koreksi Part Number"
    @click.stop="openModal"
  >
    <i class="bi bi-pencil-square"></i>
  </button>

  <!-- ─── Modal konfirmasi koreksi PN ──────────────────────────────────────── -->
  <teleport to="body">
    <div
      class="modal fade"
      :id="modalId"
      tabindex="-1"
      data-bs-backdrop="static"
      ref="modalEl"
    >
      <div class="modal-dialog modal-md">
        <div class="modal-content">
          <!-- Header -->
          <div class="modal-header" style="background:#1a3a5c;">
            <h6 class="modal-title text-white">
              <i class="bi bi-pencil-square me-2"></i>Koreksi Part Number
            </h6>
            <button
              type="button"
              class="btn-close btn-close-white"
              @click="closeModal"
            ></button>
          </div>

          <!-- Body -->
          <div class="modal-body">
            <!-- Info barang -->
            <div class="small text-muted mb-3">
              <strong>{{ item.nama_barang }}</strong>
              <span v-if="item.kode_unit" class="ms-2 text-secondary">
                <code>{{ item.kode_unit }}</code>
              </span>
            </div>

            <!-- PN lama → baru -->
            <div class="mb-3">
              <label class="form-label form-label-sm fw-semibold">Part Number Lama</label>
              <input
                class="form-control form-control-sm bg-light"
                :value="item.part_number || '-'"
                readonly
              />
            </div>

            <div class="mb-3">
              <label class="form-label form-label-sm fw-semibold">
                Part Number Baru <span class="text-danger">*</span>
              </label>
              <input
                v-model="form.new_part_number"
                class="form-control form-control-sm"
                :class="{ 'is-invalid': errors.new_part_number }"
                placeholder="Masukkan part number baru..."
                maxlength="100"
                @keyup.enter="submit"
              />
              <div v-if="errors.new_part_number" class="invalid-feedback">
                {{ errors.new_part_number }}
              </div>
            </div>

            <!-- Update master barang? -->
            <div class="form-check mb-2">
              <input
                class="form-check-input"
                type="checkbox"
                :id="'chkMaster_' + item.id"
                v-model="form.update_master"
              />
              <label class="form-check-label small" :for="'chkMaster_' + item.id">
                Perbarui juga part number di <strong>Master Barang</strong>
              </label>
            </div>

            <!-- Warning update master -->
            <transition name="fade">
              <div
                v-if="form.update_master"
                class="alert alert-warning py-2 px-3 small mb-3"
              >
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <strong>Perhatian:</strong> Perubahan ini akan memperbarui part number di data master
                barang. Barang yang sama mungkin digunakan di PO atau PM lain — pastikan
                perubahan ini memang berlaku secara global.
              </div>
            </transition>

            <!-- Catatan — wajib jika PO sudah dikirim ke vendor -->
            <div class="mb-1">
              <label class="form-label form-label-sm fw-semibold">
                Catatan / Alasan
                <span v-if="requiresNotes" class="text-danger">*</span>
                <span v-else class="text-muted fw-normal">(opsional)</span>
              </label>
              <textarea
                v-model="form.notes"
                class="form-control form-control-sm"
                :class="{ 'is-invalid': errors.notes }"
                rows="2"
                maxlength="500"
                :placeholder="requiresNotes
                  ? 'Wajib diisi — PO sudah dikirim ke vendor'
                  : 'Misal: konfirmasi perubahan PN dari supplier'"
              ></textarea>
              <div v-if="errors.notes" class="invalid-feedback">{{ errors.notes }}</div>
            </div>

            <!-- Cakupan perubahan -->
            <div class="mt-3 p-2 rounded bg-light small text-muted">
              <div class="fw-semibold mb-1 text-dark">
                <i class="bi bi-info-circle me-1"></i>Cakupan perubahan:
              </div>
              <ul class="mb-0 ps-3">
                <li>Item di PO ini (selalu)</li>
                <li v-if="item.permintaan_material_item_id">
                  Item di PM terkait (otomatis)
                </li>
                <li :class="form.update_master ? 'text-warning fw-semibold' : 'text-muted'">
                  Master Barang
                  <span v-if="!form.update_master">(tidak diubah)</span>
                  <span v-else class="badge bg-warning text-dark ms-1">akan diubah</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              @click="closeModal"
              :disabled="loading"
            >
              Batal
            </button>
            <button
              type="button"
              class="btn btn-primary btn-sm"
              @click="submit"
              :disabled="loading"
            >
              <span
                v-if="loading"
                class="spinner-border spinner-border-sm me-1"
              ></span>
              <i v-else class="bi bi-check-lg me-1"></i>
              Simpan Perubahan
            </button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { Modal }                   from 'bootstrap'
import axios                       from 'axios'

// ─── Props ───────────────────────────────────────────────────────────────────

const props = defineProps({
  /** Objek PurchaseOrder (butuh: id, status) */
  po:     { type: Object, required: true },
  /** Objek PurchaseOrderItem (butuh: id, part_number, nama_barang, kode_unit, permintaan_material_item_id) */
  item:   { type: Object, required: true },
  /** Apakah user punya permission manage-po */
  canEdit:{ type: Boolean, default: false },
})

const emit = defineEmits(['updated'])

// ─── State ────────────────────────────────────────────────────────────────────

const modalId = `modalEditPN_${props.item.id}`
const modalEl = ref(null)
let   bsModal = null

const loading = ref(false)

const form = ref({
  new_part_number: props.item.part_number || '',
  update_master:   false,
  notes:           '',
})

const errors = ref({})

// ─── Computed ─────────────────────────────────────────────────────────────────

/** Notes wajib diisi jika PO sudah melewati status draft */
const requiresNotes = computed(
  () => props.po.status !== 'draft'
)

// ─── Modal helpers ────────────────────────────────────────────────────────────

async function openModal() {
  // Reset form ke nilai terkini
  form.value = {
    new_part_number: props.item.part_number || '',
    update_master:   false,
    notes:           '',
  }
  errors.value = {}

  await nextTick()
  bsModal = new Modal(`#${modalId}`)
  bsModal.show()
}

function closeModal() {
  bsModal?.hide()
}

// ─── Submit ───────────────────────────────────────────────────────────────────

async function submit() {
  errors.value = {}

  // Validasi sisi klien
  if (!form.value.new_part_number?.trim()) {
    errors.value.new_part_number = 'Part number baru wajib diisi.'
    return
  }
  if (requiresNotes.value && !form.value.notes?.trim()) {
    errors.value.notes = 'Catatan wajib diisi karena PO sudah dikirim ke vendor.'
    return
  }

  loading.value = true
  try {
    const res = await axios.patch(
      `/purchase-orders/${props.po.id}/items/${props.item.id}/update-part-number`,
      {
        new_part_number: form.value.new_part_number.trim(),
        update_master:   form.value.update_master,
        notes:           form.value.notes?.trim() || null,
      }
    )

    closeModal()
    emit('updated', res.data.data)
  } catch (err) {
    const msg =
      err.response?.data?.errors?.notes?.[0] ||
      err.response?.data?.errors?.new_part_number?.[0] ||
      err.response?.data?.message ||
      'Gagal menyimpan perubahan.'

    // Coba tempel ke field yang sesuai
    if (msg.toLowerCase().includes('catatan') || msg.toLowerCase().includes('notes')) {
      errors.value.notes = msg
    } else {
      errors.value.new_part_number = msg
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,
.fade-leave-to    { opacity: 0; }
</style>
