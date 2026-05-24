<template>
  <div>
    <div class="csm-card">
      <div class="csm-card-header">
        <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Konfigurasi Price Intelligence</h6>
      </div>
      <div class="csm-card-body">
        <div v-if="loading" class="text-center py-4">
          <div class="spinner-border text-primary spinner-border-sm"></div>
        </div>
        <template v-else>
          <div class="row g-4">

            <!-- Threshold Kenaikan Harga -->
            <div class="col-12">
              <div class="p-3 rounded" style="background:rgba(26,58,92,0.04);border:1px solid rgba(26,58,92,0.1);">
                <h6 class="fw-bold mb-3" style="color:#1a3a5c;">
                  <i class="bi bi-arrow-up-circle me-2"></i>Threshold Kenaikan Harga
                </h6>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">
                      🟡 Batas Waspada (%)
                      <span class="text-muted fw-normal">— naik di atas ini = Waspada</span>
                    </label>
                    <div class="input-group input-group-sm">
                      <input v-model="form.threshold_up_low" type="number" class="form-control" min="1" max="100" step="1" />
                      <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Default: 5%</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">
                      🟠 Batas Kritis (%)
                      <span class="text-muted fw-normal">— naik di atas ini = Kritis</span>
                    </label>
                    <div class="input-group input-group-sm">
                      <input v-model="form.threshold_up_high" type="number" class="form-control" min="1" max="100" step="1" />
                      <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Default: 20%</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">
                      🔴 Batas Sangat Kritis (%)
                      <span class="text-muted fw-normal">— naik di atas ini = Sangat Kritis</span>
                    </label>
                    <div class="input-group input-group-sm">
                      <input v-model="form.threshold_up_critical" type="number" class="form-control" min="1" max="200" step="1" />
                      <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Default: 50%</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Digest Harian -->
            <div class="col-12">
              <div class="p-3 rounded" style="background:rgba(26,58,92,0.04);border:1px solid rgba(26,58,92,0.1);">
                <h6 class="fw-bold mb-3" style="color:#1a3a5c;">
                  <i class="bi bi-envelope me-2"></i>Digest Harian
                </h6>
                <div class="row g-3 align-items-end">
                  <div class="col-md-3">
                    <label class="form-label small fw-semibold">Aktifkan Digest Harian</label>
                    <div class="form-check form-switch mt-2">
                      <input v-model="digestEnabled" class="form-check-input" type="checkbox"
                        @change="form.digest_enabled = digestEnabled ? '1' : '0'" />
                      <label class="form-check-label small">
                        {{ digestEnabled ? 'Aktif' : 'Nonaktif' }}
                      </label>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small fw-semibold">Jam Kirim Digest</label>
                    <input v-model="form.digest_time" type="time" class="form-control form-control-sm"
                      :disabled="!digestEnabled" />
                    <div class="form-text">Notifikasi dikirim setiap hari pada jam ini</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Budget Alert -->
            <div class="col-12">
              <div class="p-3 rounded" style="background:rgba(26,58,92,0.04);border:1px solid rgba(26,58,92,0.1);">
                <h6 class="fw-bold mb-3" style="color:#1a3a5c;">
                  <i class="bi bi-cash-stack me-2"></i>Budget Alert
                </h6>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">
                      Threshold Budget Alert (%)
                      <span class="text-muted fw-normal">— alert jika total bulan ini melebihi rata-rata sebesar ini</span>
                    </label>
                    <div class="input-group input-group-sm">
                      <input v-model="form.budget_alert_threshold" type="number" class="form-control" min="1" max="200" step="1" />
                      <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Default: 20%</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">
                      Rata-rata Berapa Bulan Terakhir
                      <span class="text-muted fw-normal">— pembanding untuk budget alert</span>
                    </label>
                    <div class="input-group input-group-sm">
                      <input v-model="form.budget_alert_months" type="number" class="form-control" min="1" max="12" step="1" />
                      <span class="input-group-text">bulan</span>
                    </div>
                    <div class="form-text">Default: 3 bulan</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Deteksi Anomali -->
            <div class="col-12">
              <div class="p-3 rounded" style="background:rgba(26,58,92,0.04);border:1px solid rgba(26,58,92,0.1);">
                <h6 class="fw-bold mb-3" style="color:#1a3a5c;">
                  <i class="bi bi-exclamation-triangle me-2"></i>Deteksi Anomali
                </h6>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">
                      Kenaikan Berturut-turut
                      <span class="text-muted fw-normal">— anomali jika naik N kali berturut</span>
                    </label>
                    <div class="input-group input-group-sm">
                      <input v-model="form.consecutive_increase_count" type="number" class="form-control" min="2" max="10" step="1" />
                      <span class="input-group-text">kali</span>
                    </div>
                    <div class="form-text">Default: 3 kali</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold">
                      Threshold Selisih PO vs Terima (%)
                      <span class="text-muted fw-normal">— anomali jika selisih harga PO vs aktual melebihi ini</span>
                    </label>
                    <div class="input-group input-group-sm">
                      <input v-model="form.po_vs_receive_threshold" type="number" class="form-control" min="1" max="100" step="1" />
                      <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Default: 5%</div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Preview severity -->
          <div class="mt-4 p-3 rounded" style="background:#f8fafc;border:1px dashed #cbd5e1;">
            <div class="small fw-semibold mb-2 text-muted">Preview Klasifikasi Severity:</div>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge bg-secondary" style="font-size:0.75rem;">= Normal (0 – {{ form.threshold_up_low }}%)</span>
              <span class="badge bg-warning text-dark" style="font-size:0.75rem;">🟡 Waspada ({{ form.threshold_up_low }} – {{ form.threshold_up_high }}%)</span>
              <span class="badge text-white" style="background:#f97316;font-size:0.75rem;">🟠 Kritis ({{ form.threshold_up_high }} – {{ form.threshold_up_critical }}%)</span>
              <span class="badge bg-danger" style="font-size:0.75rem;">🔴 Sangat Kritis (> {{ form.threshold_up_critical }}%)</span>
              <span class="badge bg-success" style="font-size:0.75rem;">🟢 Turun (< 0%)</span>
            </div>
          </div>

          <!-- Save button -->
          <div class="mt-4 d-flex justify-content-end gap-2">
            <button class="btn btn-secondary btn-sm" @click="loadSettings">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
            </button>
            <button class="btn btn-primary btn-sm" @click="save" :disabled="saving">
              <span v-if="saving" class="csm-spinner me-1"></span>
              <i v-else class="bi bi-check-lg me-1"></i>Simpan Konfigurasi
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const toast   = useToast()
const loading = ref(true)
const saving  = ref(false)
const digestEnabled = ref(true)

const form = reactive({
  threshold_up_low:          '5',
  threshold_up_high:         '20',
  threshold_up_critical:     '50',
  digest_time:               '08:00',
  digest_enabled:            '1',
  budget_alert_threshold:    '20',
  budget_alert_months:       '3',
  consecutive_increase_count:'3',
  po_vs_receive_threshold:   '5',
})

async function loadSettings() {
  loading.value = true
  try {
    const r = await axios.get('/price-intelligence/settings')
    const settings = r.data.data
    settings.forEach(s => {
      if (s.key in form) form[s.key] = s.value
    })
    digestEnabled.value = form.digest_enabled === '1'
  } catch {} finally { loading.value = false }
}

async function save() {
  saving.value = true
  try {
    const settings = Object.entries(form).map(([key, value]) => ({ key, value: String(value) }))
    await axios.put('/price-intelligence/settings', { settings })
    toast.success('Konfigurasi berhasil disimpan')
  } catch {
    toast.error('Gagal menyimpan konfigurasi')
  } finally { saving.value = false }
}

onMounted(loadSettings)
</script>
