<template>
  <BaseModal
    :open="open"
    size="xl"
    :title="`Impor ${title} dari Excel`"
    :description="preview ? 'Langkah 2 dari 2: periksa hasil pembacaan file.' : 'Langkah 1 dari 2: pilih file, sistem akan memeriksanya dulu.'"
    @close="close"
  >
    <form v-if="!preview" :id="formId" class="space-y-4" @submit.prevent="check">
      <div class="rounded-[var(--radius-kontrol)] bg-krem px-4 py-3 text-sm">
        <p class="font-semibold">Ketentuan file</p>
        <ul class="mt-1 list-disc space-y-1 pl-5 text-tinta-2">
          <li>Pakai template dari tombol di bawah. Kolom dan cara mengisinya ada di sheet <strong class="text-tinta">Petunjuk</strong>.</li>
          <li>Format .xlsx, .xls, atau .csv, paling besar 5 MB. Baris pertama berisi judul kolom.</li>
          <li v-for="note in notes" :key="note">{{ note }}</li>
        </ul>
        <button type="button" class="btn btn-secondary btn-sm mt-3" :disabled="downloading" @click="downloadTemplate">
          {{ downloading ? 'Menyiapkan template...' : `Unduh template ${title}` }}
        </button>
      </div>
      <div>
        <label :for="`${formId}-file`" class="field-label">File Excel {{ title.toLowerCase() }}</label>
        <input
          :id="`${formId}-file`"
          :key="inputKey"
          type="file"
          accept=".xlsx,.xls,.csv"
          required
          class="field-input py-1.5 file:mr-3 file:rounded-[var(--radius-kontrol)] file:border-0 file:bg-bata-muda file:px-3 file:py-2 file:font-semibold file:text-bata-tua"
          :aria-invalid="fileError ? 'true' : 'false'"
          :aria-describedby="fileError ? `${formId}-error` : undefined"
          @change="onFileChange"
        />
        <p v-if="fileError" :id="`${formId}-error`" class="field-error" role="alert">{{ fileError }}</p>
      </div>
    </form>

    <div v-else class="space-y-4">
      <div>
        <p class="font-semibold">
          {{ preview.summary.total }} baris terbaca: {{ preview.summary.create }} baru,
          {{ preview.summary.update }} diperbarui, {{ preview.summary.invalid }} dilewati.
        </p>
        <p v-if="preview.summary.invalid" class="mt-1 text-sm text-tinta-2">
          Baris yang dilewati tidak ikut disimpan. Perbaiki di file Excel lalu impor ulang; baris yang sudah masuk akan
          diperbarui, bukan dobel.
        </p>
        <p v-if="saveableCount === 0" class="field-error" role="alert">
          Tidak ada baris yang bisa diimpor. Perbaiki file sesuai keterangan di tabel, lalu pilih file lagi.
        </p>
      </div>

      <div class="overflow-x-auto rounded-[var(--radius-kontrol)] border border-garis">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col" class="num w-16">Baris</th>
              <th scope="col">Hasil</th>
              <th scope="col">Data</th>
              <th scope="col">Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in preview.rows" :key="row.row">
              <td class="num text-tinta-2">{{ row.row }}</td>
              <td>
                <StatusTag v-if="row.action === 'create'" status="APPROVED" label="Baru" />
                <StatusTag v-else-if="row.action === 'update'" status="PENDING" label="Diperbarui" />
                <StatusTag v-else status="REJECTED" label="Dilewati" />
              </td>
              <td>{{ row.label || '-' }}</td>
              <td>
                <ul v-if="row.errors.length" class="space-y-0.5 text-sm font-medium text-galat">
                  <li v-for="err in row.errors" :key="err">{{ err }}</li>
                </ul>
                <span v-else class="text-sm text-tinta-2">Tidak ada masalah</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <template #footer>
      <template v-if="!preview">
        <button type="button" class="btn btn-secondary" @click="close">Batal</button>
        <button type="submit" :form="formId" class="btn btn-primary" :disabled="!file || busy">
          {{ busy ? 'Memeriksa file...' : 'Periksa file' }}
        </button>
      </template>
      <template v-else>
        <button type="button" class="btn btn-secondary" :disabled="busy" @click="reset">Pilih file lain</button>
        <button type="button" class="btn btn-primary" :disabled="busy || saveableCount === 0" @click="save">
          {{ busy ? 'Mengimpor...' : `Simpan ${saveableCount} baris` }}
        </button>
      </template>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue'
import axios from 'axios'
import { useNotice } from '@/composables/useNotice'
import { errorMessage } from '@/utils/format'
import { downloadFile } from '@/utils/download'
import BaseModal from './BaseModal.vue'
import StatusTag from './StatusTag.vue'

interface PreviewRow {
  row: number
  label: string
  action: 'create' | 'update' | null
  errors: string[]
  is_valid: boolean
}

interface Preview {
  summary: { total: number; create: number; update: number; invalid: number }
  rows: PreviewRow[]
}

const props = withDefaults(
  defineProps<{
    open: boolean
    /** API dataset key, e.g. "students". */
    dataset: string
    /** Human name, e.g. "Siswa". */
    title: string
    /** Extra rules shown above the file input. */
    notes?: string[]
  }>(),
  { notes: () => [] },
)

const emit = defineEmits<{ close: []; imported: [] }>()

const notice = useNotice()
const formId = `import-${useId()}`
const file = ref<File | null>(null)
const preview = ref<Preview | null>(null)
const fileError = ref('')
const busy = ref(false)
const downloading = ref(false)
const inputKey = ref(0)

const saveableCount = computed(() => (preview.value ? preview.value.summary.create + preview.value.summary.update : 0))

const reset = () => {
  file.value = null
  preview.value = null
  fileError.value = ''
  inputKey.value++
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) reset()
  },
)

const close = () => emit('close')

const onFileChange = (event: Event) => {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null
  fileError.value = ''
}

const upload = (action: 'preview' | 'import') => {
  const formData = new FormData()
  formData.append('file', file.value as File)
  return axios.post(`/api/v1/setup/${props.dataset}/${action}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

const check = async () => {
  if (!file.value) return
  busy.value = true
  fileError.value = ''
  try {
    preview.value = (await upload('preview')).data
  } catch (error) {
    fileError.value = errorMessage(error, 'File gagal dibaca. Pastikan formatnya .xlsx, .xls, atau .csv.')
  } finally {
    busy.value = false
  }
}

/** The file is sent again and checked again on the server; the preview is only for the user. */
const save = async () => {
  busy.value = true
  try {
    const response = await upload('import')
    notice.success(response.data.message)
    emit('imported')
    close()
  } catch (error) {
    notice.error(errorMessage(error, `Data ${props.title.toLowerCase()} gagal diimpor. Tidak ada baris yang disimpan.`))
  } finally {
    busy.value = false
  }
}

const downloadTemplate = async () => {
  downloading.value = true
  try {
    await downloadFile(`/api/v1/setup/${props.dataset}/template`, `template-${props.dataset}.xlsx`)
  } catch {
    notice.error('Template gagal diunduh. Coba lagi.')
  } finally {
    downloading.value = false
  }
}
</script>
