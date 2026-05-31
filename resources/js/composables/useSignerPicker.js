/**
 * useSignerPicker.js
 *
 * Composable untuk alur TTD bertahap dengan finalisasi:
 *  1. openSignerPicker(docType, docId, onPrint) dipanggil saat user klik Print
 *  2. GET /document-signers/{type}/{id} → load status slot
 *  3. Modal terbuka menampilkan slot yang sudah terisi + kosong
 *  4. User bisa klik "Tambah TTD Saya" → POST .../slot (slot login user)
 *  5. User berwenang bisa klik "Finalisasi" → POST .../finalize → terkunci
 *  6. onPrint(resolvedSlots) dipanggil — bisa sebelum atau sesudah finalisasi
 */

import { ref, computed } from 'vue'
import axios from 'axios'

export function useSignerPicker() {
  const showSignerModal  = ref(false)
  const slots            = ref([null, null, null])   // 3 slot, null = kosong
  const isFinalized      = ref(false)
  const finalizedAt      = ref(null)
  const loading          = ref(false)
  const actionLoading    = ref(false)

  // backward compat
  const signers         = ref([])
  const lastSelectedIds = ref([])

  let _docType    = null
  let _docId      = null
  let _onPrint    = null

  // ── Load status dari DB ────────────────────────────────────────────────────

  async function loadStatus() {
    if (!_docType || !_docId) return
    loading.value = true
    try {
      const { data } = await axios.get(`/document-signers/${_docType}/${_docId}`)
      slots.value       = data.slots       ?? [null, null, null]
      isFinalized.value = data.is_finalized ?? false
      finalizedAt.value = data.finalized_at ?? null
    } catch (e) {
      console.warn('[useSignerPicker] loadStatus error:', e)
    } finally {
      loading.value = false
    }
  }

  // ── Entry point ────────────────────────────────────────────────────────────

  async function openSignerPicker(docType, docId, onPrint) {
    _docType = docType
    _docId   = String(docId)
    _onPrint = onPrint

    await loadStatus()
    showSignerModal.value = true
  }

  // ── Aksi dari modal ────────────────────────────────────────────────────────

  /** Tambah TTD user yang login ke slot tertentu */
  async function addMySlot(slot) {
    actionLoading.value = true
    try {
      const { data } = await axios.post(
        `/document-signers/${_docType}/${_docId}/slot`,
        { slot }
      )
      slots.value       = data.slots       ?? slots.value
      isFinalized.value = data.is_finalized ?? false
      return { success: true, message: data.message }
    } catch (e) {
      const msg = e.response?.data?.message ?? 'Gagal menambahkan TTD.'
      return { success: false, message: msg }
    } finally {
      actionLoading.value = false
    }
  }

  /** Finalisasi dokumen — kunci permanen */
  async function finalizeDoc() {
    actionLoading.value = true
    try {
      const { data } = await axios.post(`/document-signers/${_docType}/${_docId}/finalize`)
      slots.value       = data.slots       ?? slots.value
      isFinalized.value = true
      finalizedAt.value = data.finalized_at ?? null
      return { success: true, message: data.message }
    } catch (e) {
      const msg = e.response?.data?.message ?? 'Gagal memfinalisasi.'
      return { success: false, message: msg }
    } finally {
      actionLoading.value = false
    }
  }

  /** Print dengan slot yang ada sekarang (terisi dan kosong) */
  function printNow() {
    showSignerModal.value = false
    if (_onPrint) _onPrint(slots.value)
  }

  /** Batal / tutup modal */
  function closeModal() {
    showSignerModal.value = false
  }

  // backward compat — tidak dipakai tapi jaga agar tidak error
  function saveLastSelected() {}
  function resetSignerCache() {}
  function onModalConfirm() {}

  async function fetchSigners() {
    try {
      const { data } = await axios.get('/users/signable')
      signers.value = data.data ?? []
    } catch {}
  }

  return {
    showSignerModal,
    slots,
    isFinalized,
    finalizedAt,
    loading,
    actionLoading,
    signers,
    lastSelectedIds,
    loadStatus,
    openSignerPicker,
    addMySlot,
    finalizeDoc,
    printNow,
    closeModal,
    saveLastSelected,
    resetSignerCache,
    onModalConfirm,
    fetchSigners,
  }
}