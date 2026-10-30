<template>
  <AdminLayout>
    <PageHeader
      title="Pengaturan"
      description="Identitas sekolah yang tampil di menu samping, halaman masuk, kuitansi, dan kartu pembayaran."
    />

    <section v-if="fetching" class="panel">
      <DataState state="loading" message="Mengambil pengaturan sekolah." />
    </section>
    <section v-else-if="loadError" class="panel">
      <DataState state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchSettings()">Coba lagi</button>
      </DataState>
    </section>

    <form v-else class="max-w-3xl space-y-6" @submit.prevent="saveSettings">
      <p
        v-if="formError"
        role="alert"
        class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
      >
        {{ formError }}
      </p>

      <section class="panel px-5 py-5 sm:px-6" aria-labelledby="identitas-title">
        <h2 id="identitas-title" class="text-2xl font-bold">Identitas sekolah</h2>
        <p class="mt-1 text-sm text-tinta-2">Nama dan alamat dicetak di kepala kuitansi dan kartu pembayaran.</p>

        <div class="mt-5 space-y-4">
          <div>
            <label for="school-name" class="field-label">Nama sekolah</label>
            <input
              id="school-name"
              v-model="settings.school_name"
              type="text"
              class="field-input"
              :aria-invalid="fieldErrors.school_name ? 'true' : undefined"
              :aria-describedby="fieldErrors.school_name ? 'school-name-error' : undefined"
            />
            <p v-if="fieldErrors.school_name" id="school-name-error" class="field-error">{{ fieldErrors.school_name }}</p>
          </div>

          <div>
            <label for="school-address" class="field-label">Alamat</label>
            <textarea
              id="school-address"
              v-model="settings.school_address"
              rows="2"
              class="field-input"
              :aria-invalid="fieldErrors.school_address ? 'true' : undefined"
              :aria-describedby="fieldErrors.school_address ? 'school-address-error' : undefined"
            ></textarea>
            <p v-if="fieldErrors.school_address" id="school-address-error" class="field-error">{{ fieldErrors.school_address }}</p>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="school-phone" class="field-label">Telepon</label>
              <input
                id="school-phone"
                v-model="settings.school_phone"
                type="tel"
                class="field-input"
                :aria-invalid="fieldErrors.school_phone ? 'true' : undefined"
                :aria-describedby="fieldErrors.school_phone ? 'school-phone-error' : undefined"
              />
              <p v-if="fieldErrors.school_phone" id="school-phone-error" class="field-error">{{ fieldErrors.school_phone }}</p>
            </div>
            <div>
              <label for="school-email" class="field-label">Email</label>
              <input
                id="school-email"
                v-model="settings.school_email"
                type="email"
                class="field-input"
                :aria-invalid="fieldErrors.school_email ? 'true' : undefined"
                :aria-describedby="fieldErrors.school_email ? 'school-email-error' : undefined"
              />
              <p v-if="fieldErrors.school_email" id="school-email-error" class="field-error">{{ fieldErrors.school_email }}</p>
            </div>
          </div>

          <div>
            <label for="school-logo" class="field-label">Logo sekolah</label>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
              <div
                class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius-panel)] border border-garis bg-krem"
              >
                <img
                  v-if="previewLogo"
                  :src="previewLogo"
                  :alt="selectedLogo ? 'Pratinjau logo baru' : 'Logo sekolah saat ini'"
                  class="size-full object-contain p-1"
                />
                <span v-else class="px-2 text-center text-sm text-tinta-2">Belum ada logo</span>
              </div>
              <div class="min-w-0 flex-1">
                <input
                  id="school-logo"
                  ref="logoInput"
                  type="file"
                  accept="image/*"
                  class="field-input py-2 file:mr-3 file:rounded-[var(--radius-kontrol)] file:border-0 file:bg-krem file:px-3 file:py-1.5 file:font-semibold file:text-tinta"
                  aria-describedby="logo-hint"
                  :aria-invalid="fieldErrors.school_logo ? 'true' : undefined"
                  @change="onFileChange"
                />
                <p id="logo-hint" class="field-hint">
                  {{ selectedLogo ? `Berkas baru: ${selectedLogo.name}. Logo diganti setelah pengaturan disimpan.` : 'Gambar persegi, latar polos. Kosongkan bila logo tidak diganti.' }}
                </p>
                <p v-if="fieldErrors.school_logo" class="field-error">{{ fieldErrors.school_logo }}</p>
                <button v-if="selectedLogo" type="button" class="btn btn-ghost btn-sm mt-2" @click="clearSelectedLogo">
                  Batalkan logo baru
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="panel px-5 py-5 sm:px-6" aria-labelledby="kuitansi-title">
        <h2 id="kuitansi-title" class="text-2xl font-bold">Kuitansi</h2>
        <div class="mt-4">
          <label for="receipt-footer" class="field-label">Catatan bawah kuitansi</label>
          <textarea
            id="receipt-footer"
            v-model="settings.receipt_footer"
            rows="3"
            class="field-input"
            aria-describedby="receipt-footer-hint"
            :aria-invalid="fieldErrors.receipt_footer ? 'true' : undefined"
          ></textarea>
          <p id="receipt-footer-hint" class="field-hint">Dicetak di baris paling bawah setiap kuitansi pembayaran.</p>
          <p v-if="fieldErrors.receipt_footer" class="field-error">{{ fieldErrors.receipt_footer }}</p>
        </div>
      </section>

      <div class="flex justify-end">
        <button type="submit" class="btn btn-primary w-full sm:w-auto" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan pengaturan' }}
        </button>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { useNotice } from '@/composables/useNotice'
import { useSchoolStore } from '@/stores/school'
import { errorMessage } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'

type SettingsForm = {
  school_name: string
  school_address: string
  school_phone: string
  school_email: string
  receipt_footer: string
}

const notice = useNotice()
const schoolStore = useSchoolStore()

const fetching = ref(true)
const loadError = ref('')
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})

const settings = ref<SettingsForm>({
  school_name: '',
  school_address: '',
  school_phone: '',
  school_email: '',
  receipt_footer: '',
})

const currentLogo = ref<string | null>(null)
const selectedLogo = ref<File | null>(null)
const previewLogo = ref<string | null>(null)
const logoInput = ref<HTMLInputElement | null>(null)
let objectUrl: string | null = null

const releaseObjectUrl = () => {
  if (objectUrl) {
    URL.revokeObjectURL(objectUrl)
    objectUrl = null
  }
}

/** A silent refresh (after saving) keeps the form on screen instead of swapping in the loading state. */
const fetchSettings = async (silent = false) => {
  if (!silent) {
    fetching.value = true
    loadError.value = ''
  }
  try {
    const response = await axios.get('/api/v1/settings')
    const data = response.data.data || {}
    settings.value = {
      school_name: data.school_name || '',
      school_address: data.school_address || '',
      school_phone: data.school_phone || '',
      school_email: data.school_email || '',
      receipt_footer: data.receipt_footer || '',
    }
    currentLogo.value = data.school_logo || null
    previewLogo.value = currentLogo.value
  } catch (error) {
    if (!silent) {
      loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
    }
  } finally {
    fetching.value = false
  }
}

const onFileChange = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  releaseObjectUrl()
  selectedLogo.value = file
  objectUrl = URL.createObjectURL(file)
  previewLogo.value = objectUrl
}

const clearSelectedLogo = () => {
  releaseObjectUrl()
  selectedLogo.value = null
  previewLogo.value = currentLogo.value
  if (logoInput.value) logoInput.value.value = ''
}

const saveSettings = async () => {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}

  try {
    const formData = new FormData()
    formData.append('school_name', settings.value.school_name)
    formData.append('school_address', settings.value.school_address)
    formData.append('school_phone', settings.value.school_phone)
    formData.append('school_email', settings.value.school_email)
    formData.append('receipt_footer', settings.value.receipt_footer)
    if (selectedLogo.value) {
      formData.append('school_logo', selectedLogo.value)
    }

    await axios.post('/api/v1/settings', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    clearSelectedLogo()
    await Promise.all([fetchSettings(true), schoolStore.loadProfile(true)])
    notice.success('Pengaturan sekolah tersimpan.')
  } catch (error: any) {
    const errors = error?.response?.data?.errors || {}
    fieldErrors.value = Object.fromEntries(
      Object.entries(errors).map(([key, messages]) => [key, (messages as string[])[0]]),
    )
    formError.value = errorMessage(error, 'Pengaturan gagal disimpan. Periksa koneksi, lalu coba lagi.')
  } finally {
    saving.value = false
  }
}

onMounted(() => fetchSettings())
onBeforeUnmount(releaseObjectUrl)
</script>
