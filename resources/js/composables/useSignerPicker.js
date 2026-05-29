/**
 * useSignerPicker.js
 * Composable reusable untuk semua halaman yang butuh pilih penandatangan sebelum export PDF.
 *
 * Cara pakai:
 *   import { useSignerPicker } from '@/composables/useSignerPicker'
 *   const { showSignerModal, signers, openSignerPicker, fetchSigners } = useSignerPicker()
 *
 * Di template:
 *   <SignerPickerModal
 *     v-model="showSignerModal"
 *     :signers="signers"
 *     @confirm="onSignersConfirmed"
 *   />
 *
 * Kemudian panggil openSignerPicker() saat tombol Export PDF diklik,
 * dan tangkap event @confirm untuk lanjut download.
 */

import { ref } from 'vue'
import axios from 'axios'

export function useSignerPicker() {
  const showSignerModal = ref(false)
  const signers         = ref([])     // daftar user yang sudah punya TTD
  const loadingSigners  = ref(false)

  /**
   * Fetch daftar user yang sudah upload TTD dari server.
   * Dipanggil sekali saat halaman dimuat atau saat modal akan dibuka.
   */
  async function fetchSigners() {
    if (signers.value.length) return  // cache, tidak perlu fetch ulang
    loadingSigners.value = true
    try {
      const { data } = await axios.get('/users/signable')
      signers.value = data.data ?? []
    } catch {
      signers.value = []
    } finally {
      loadingSigners.value = false
    }
  }

  /**
   * Buka modal signer picker. Fetch data kalau belum ada.
   */
  async function openSignerPicker() {
    await fetchSigners()
    showSignerModal.value = true
  }

  return {
    showSignerModal,
    signers,
    loadingSigners,
    fetchSigners,
    openSignerPicker,
  }
}
