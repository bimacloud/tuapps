<template>
  <admin-layout>
    <PageHeader
      title="Tahun Pelajaran"
      description="Hanya satu tahun pelajaran yang aktif. Tahun aktif tampil di kepala halaman dan menjadi acuan beranda serta kartu pembayaran siswa."
    >
      <template v-if="canEditData" #actions>
        <button type="button" class="btn btn-secondary" :disabled="exporting" @click="exportData">
          {{ exporting ? 'Menyiapkan file...' : 'Unduh data' }}
        </button>
        <button type="button" class="btn btn-secondary" @click="showImportModal = true">Impor dari Excel</button>
        <button type="button" class="btn btn-primary" @click="openModal()">Tambah tahun pelajaran</button>
      </template>
    </PageHeader>
    <YearReminder :show-link="false" class="mb-6" />

    <section
      v-if="!loading && !loadError && items.length"
      class="panel mb-4 flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      aria-labelledby="active-year-heading"
    >
      <div v-if="activeYear" class="min-w-0">
        <h2 id="active-year-heading" class="font-sans text-sm font-semibold text-tinta-2">
          Tahun pelajaran aktif
        </h2>
        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
          <span class="font-display text-2xl font-bold leading-none">{{ activeYear.name }}</span>
          <StatusTag :status="true" />
        </p>
        <p class="mt-1 text-sm text-tinta-2">
          {{ formatDate(activeYear.start_date) }} s.d. {{ formatDate(activeYear.end_date) }}
        </p>
      </div>
      <div v-else class="min-w-0">
        <h2 id="active-year-heading" class="font-sans font-semibold text-belum">
          Belum ada tahun pelajaran aktif.
        </h2>
        <p class="mt-1 text-sm text-tinta-2">
          {{ canEditData ? 'Pilih "Jadikan aktif" pada tahun yang sedang berjalan.' : 'Administrasi perlu menandai tahun yang sedang berjalan sebagai tahun aktif.' }}
        </p>
      </div>
    </section>

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Mengambil daftar tahun pelajaran." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchItems">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        title="Belum ada tahun pelajaran."
        :message="canEditData ? 'Tambahkan tahun pelajaran yang sedang berjalan dan tandai sebagai aktif. Kelas baru membutuhkan tahun pelajaran.' : 'Administrasi belum menambahkan tahun pelajaran.'"
      >
        <button v-if="canEditData" type="button" class="btn btn-primary" @click="openModal()">Tambah tahun pelajaran</button>
      </DataState>

      <div v-else class="overflow-x-auto">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col">Tahun pelajaran</th>
              <th scope="col">Mulai</th>
              <th scope="col">Selesai</th>
              <th scope="col">Status</th>
              <th v-if="canEditData" scope="col"><span class="sr-only">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="font-display text-lg font-bold whitespace-nowrap">{{ item.name }}</td>
              <td class="whitespace-nowrap">{{ formatDate(item.start_date) }}</td>
              <td class="whitespace-nowrap">{{ formatDate(item.end_date) }}</td>
              <td>
                <StatusTag :status="Boolean(item.is_active)" />
              </td>
              <td v-if="canEditData" class="text-right">
                <div class="flex justify-end gap-1">
                  <button
                    v-if="!item.is_active"
                    type="button"
                    class="btn btn-ghost btn-sm"
                    :disabled="activatingId === item.id"
                    @click="activateItem(item)"
                  >
                    {{ activatingId === item.id ? 'Mengaktifkan...' : 'Jadikan aktif' }}<span class="sr-only"> {{ item.name }}</span>
                  </button>
                  <button type="button" class="btn btn-ghost btn-sm" @click="openModal(item)">
                    Ubah<span class="sr-only"> {{ item.name }}</span>
                  </button>
                  <button
                    v-if="!item.is_active"
                    type="button"
                    class="btn btn-ghost btn-sm !text-galat"
                    @click="deleteItem(item)"
                  >
                    Hapus<span class="sr-only"> {{ item.name }}</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <BaseModal
      :open="showModal"
      :title="form.id ? 'Ubah tahun pelajaran' : 'Tambah tahun pelajaran'"
      @close="closeModal"
    >
      <form id="academic-year-form" class="space-y-4" @submit.prevent="saveItem">
        <p
          v-if="formError"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ formError }}
        </p>

        <div>
          <label for="year-name" class="field-label">Nama tahun pelajaran</label>
          <input
            id="year-name"
            v-model="form.name"
            type="text"
            required
            maxlength="255"
            class="field-input"
            placeholder="Tahun awal/tahun akhir"
            :aria-invalid="fieldError('name') ? 'true' : 'false'"
            :aria-describedby="fieldError('name') ? 'year-name-error' : 'year-name-hint'"
          />
          <p v-if="fieldError('name')" id="year-name-error" class="field-error">{{ fieldError('name') }}</p>
          <p v-else id="year-name-hint" class="field-hint">Tulis dengan garis miring, misalnya 2025/2026.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="year-start" class="field-label">Tanggal mulai</label>
            <input
              id="year-start"
              v-model="form.start_date"
              type="date"
              required
              class="field-input"
              :aria-invalid="fieldError('start_date') ? 'true' : 'false'"
              :aria-describedby="fieldError('start_date') ? 'year-start-error' : undefined"
            />
            <p v-if="fieldError('start_date')" id="year-start-error" class="field-error">{{ fieldError('start_date') }}</p>
          </div>
          <div>
            <label for="year-end" class="field-label">Tanggal selesai</label>
            <input
              id="year-end"
              v-model="form.end_date"
              type="date"
              required
              class="field-input"
              :aria-invalid="fieldError('end_date') ? 'true' : 'false'"
              :aria-describedby="fieldError('end_date') ? 'year-end-error' : undefined"
            />
            <p v-if="fieldError('end_date')" id="year-end-error" class="field-error">{{ fieldError('end_date') }}</p>
          </div>
        </div>

        <div>
          <label class="flex min-h-11 items-center gap-3">
            <input v-model="form.is_active" type="checkbox" class="field-check" aria-describedby="year-active-hint" />
            <span class="font-medium">Jadikan tahun pelajaran aktif</span>
          </label>
          <p id="year-active-hint" class="field-hint">
            {{ activeYear && activeYear.id !== form.id
              ? `Tahun aktif saat ini ${activeYear.name}. Jika dicentang, ${activeYear.name} otomatis menjadi nonaktif.`
              : 'Hanya satu tahun yang bisa aktif dalam satu waktu.' }}
          </p>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="closeModal">Batal</button>
        <button type="submit" form="academic-year-form" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan tahun pelajaran' }}
        </button>
      </template>
    </BaseModal>

    <ImportExcelModal
      :open="showImportModal"
      dataset="academic-years"
      title="Tahun Pelajaran"
      :notes="['Hanya satu baris yang boleh berisi Aktif = Ya.']"
      @close="showImportModal = false"
      @imported="fetchItems"
    />
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useSchoolStore } from '@/stores/school'
import { useNotice } from '@/composables/useNotice'
import { errorMessage, formatDate } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import ImportExcelModal from '@/components/app/ImportExcelModal.vue'
import { downloadFile } from '@/utils/download'
import YearReminder from '@/components/app/YearReminder.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const authStore = useAuthStore()
const schoolStore = useSchoolStore()
const notice = useNotice()
const canEditData = computed(() => authStore.hasRole('Administrasi'))

const items = ref([])
const loading = ref(true)
const loadError = ref('')
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref({})
const activatingId = ref(null)

const activeYear = computed(() => items.value.find((item) => item.is_active) || null)

/** API dates are serialized as ISO timestamps (app timezone UTC); date inputs need YYYY-MM-DD. */
const toDateInput = (value) => (value ? String(value).substring(0, 10) : '')

const emptyForm = () => ({ id: null, name: '', start_date: '', end_date: '', is_active: false })
const form = ref(emptyForm())

const fieldError = (key) => fieldErrors.value[key]?.[0] || ''

const fetchItems = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await axios.get('/api/v1/academic-years')
    items.value = response.data.data
    schoolStore.activeYear = activeYear.value
    schoolStore.years = items.value
  } catch (error) {
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  formError.value = ''
  fieldErrors.value = {}
  form.value = item
    ? {
        ...item,
        start_date: toDateInput(item.start_date),
        end_date: toDateInput(item.end_date),
        is_active: Boolean(item.is_active),
      }
    : emptyForm()
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  const replacesActiveYear =
    form.value.is_active && activeYear.value && activeYear.value.id !== form.value.id
  if (replacesActiveYear) {
    const confirmed = await notice.confirm({
      title: 'Ganti tahun pelajaran aktif?',
      message: `${form.value.name || 'Tahun ini'} akan menjadi tahun aktif dan ${activeYear.value.name} menjadi nonaktif.`,
      confirmLabel: 'Simpan dan jadikan aktif',
    })
    if (!confirmed) return
  }

  saving.value = true
  formError.value = ''
  fieldErrors.value = {}

  try {
    if (form.value.id) {
      await axios.put(`/api/v1/academic-years/${form.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/academic-years', form.value)
    }
    notice.success(`Tahun pelajaran ${form.value.name} tersimpan.`)
    closeModal()
    fetchItems()
  } catch (error) {
    fieldErrors.value = error.response?.data?.errors || {}
    formError.value = errorMessage(error, 'Tahun pelajaran gagal disimpan.')
  } finally {
    saving.value = false
  }
}

const activateItem = async (item) => {
  const confirmed = await notice.confirm({
    title: `Jadikan ${item.name} tahun aktif?`,
    message: activeYear.value
      ? `${activeYear.value.name} akan menjadi nonaktif. Beranda, kepala halaman, dan kartu pembayaran akan memakai ${item.name}.`
      : `Beranda, kepala halaman, dan kartu pembayaran akan memakai ${item.name}.`,
    confirmLabel: 'Jadikan aktif',
  })
  if (!confirmed) return

  activatingId.value = item.id
  try {
    await axios.put(`/api/v1/academic-years/${item.id}`, {
      ...item,
      start_date: toDateInput(item.start_date),
      end_date: toDateInput(item.end_date),
      is_active: true,
    })
    notice.success(`${item.name} sekarang tahun pelajaran aktif.`)
    fetchItems()
  } catch (error) {
    notice.error(errorMessage(error, 'Tahun pelajaran gagal diaktifkan.'))
  } finally {
    activatingId.value = null
  }
}

const deleteItem = async (item) => {
  const confirmed = await notice.confirm({
    title: 'Hapus tahun pelajaran?',
    message: `${item.name} akan dihapus. Tahun yang sudah dipakai di data kelas tidak bisa dihapus.`,
    confirmLabel: 'Hapus tahun pelajaran',
    danger: true,
  })
  if (!confirmed) return

  try {
    await axios.delete(`/api/v1/academic-years/${item.id}`)
    notice.success(`Tahun pelajaran ${item.name} dihapus.`)
    fetchItems()
  } catch (error) {
    notice.error(errorMessage(error, 'Tahun pelajaran gagal dihapus.'))
  }
}

// Excel export and import in the same format as the template on the Setup Awal page.
const showImportModal = ref(false)
const exporting = ref(false)
const exportData = async () => {
  exporting.value = true
  try {
    await downloadFile('/api/v1/setup/academic-years/export', 'data-academic-years.xlsx')
  } catch {
    notice.error('Data tahun pelajaran gagal diunduh. Coba lagi.')
  } finally {
    exporting.value = false
  }
}

onMounted(fetchItems)
</script>
