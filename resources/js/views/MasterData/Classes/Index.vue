<template>
  <admin-layout>
    <PageHeader
      title="Kelas"
      description="Rombongan belajar per tahun pelajaran. Siswa dicatat ke kelas, dan tagihan mengikuti kelasnya."
    >
      <template v-if="canEditData" #actions>
        <button type="button" class="btn btn-primary" :disabled="loading" @click="openModal()">Tambah kelas</button>
      </template>
    </PageHeader>

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Mengambil daftar kelas, jurusan, dan tahun pelajaran." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0 && missingPrerequisites.length"
        state="empty"
        title="Belum ada kelas."
        :message="`Kelas membutuhkan ${missingPrerequisites.join(' dan ')}. Tambahkan dulu di menu masing-masing.`"
      >
        <router-link v-if="!majors.length" to="/master/majors" class="btn btn-secondary">Buka menu Jurusan</router-link>
        <router-link v-if="!academicYears.length" to="/master/academic-years" class="btn btn-secondary">
          Buka menu Tahun Pelajaran
        </router-link>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        title="Belum ada kelas."
        :message="canEditData ? 'Tambahkan kelas supaya siswa bisa dicatat dan ditagih per kelas.' : 'Administrasi belum menambahkan kelas.'"
      >
        <button v-if="canEditData" type="button" class="btn btn-primary" @click="openModal()">Tambah kelas</button>
      </DataState>

      <div v-else class="overflow-x-auto">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col">Nama kelas</th>
              <th scope="col">Tingkat</th>
              <th scope="col">Jurusan</th>
              <th scope="col">Tahun pelajaran</th>
              <th scope="col">Wali kelas</th>
              <th scope="col">Status</th>
              <th v-if="canEditData" scope="col"><span class="sr-only">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="font-display text-lg font-bold whitespace-nowrap">{{ item.name }}</td>
              <td>{{ item.grade }}</td>
              <td>{{ item.major?.name || '-' }}</td>
              <td class="whitespace-nowrap">{{ item.academic_year?.name || '-' }}</td>
              <td>{{ item.homeroom_teacher || '-' }}</td>
              <td><StatusTag :status="Boolean(item.status)" /></td>
              <td v-if="canEditData" class="text-right">
                <div class="flex justify-end gap-1">
                  <button type="button" class="btn btn-ghost btn-sm" @click="openModal(item)">
                    Ubah<span class="sr-only"> {{ item.name }}</span>
                  </button>
                  <button type="button" class="btn btn-ghost btn-sm !text-galat" @click="deleteItem(item)">
                    Hapus<span class="sr-only"> {{ item.name }}</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <BaseModal :open="showModal" size="lg" :title="form.id ? 'Ubah kelas' : 'Tambah kelas'" @close="closeModal">
      <form id="class-form" class="space-y-4" @submit.prevent="saveItem">
        <p
          v-if="formError"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ formError }}
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="class-name" class="field-label">Nama kelas</label>
            <input
              id="class-name"
              v-model="form.name"
              type="text"
              required
              maxlength="255"
              class="field-input"
              placeholder="Tingkat, jurusan, dan nomor rombel"
              :aria-invalid="fieldError('name') ? 'true' : 'false'"
              :aria-describedby="fieldError('name') ? 'class-name-error' : 'class-name-hint'"
            />
            <p v-if="fieldError('name')" id="class-name-error" class="field-error">{{ fieldError('name') }}</p>
            <p v-else id="class-name-hint" class="field-hint">Nama ini dipakai saat impor siswa, misalnya X TJKT 1.</p>
          </div>
          <div>
            <label for="class-grade" class="field-label">Tingkat</label>
            <input
              id="class-grade"
              v-model="form.grade"
              type="text"
              required
              class="field-input"
              maxlength="50"
              placeholder="X, XI, atau XII"
              :aria-invalid="fieldError('grade') ? 'true' : 'false'"
              :aria-describedby="fieldError('grade') ? 'class-grade-error' : undefined"
            />
            <p v-if="fieldError('grade')" id="class-grade-error" class="field-error">{{ fieldError('grade') }}</p>
          </div>

          <div>
            <label for="class-major" class="field-label">Jurusan</label>
            <select
              id="class-major"
              v-model="form.major_id"
              required
              class="field-input"
              :aria-invalid="fieldError('major_id') ? 'true' : 'false'"
              :aria-describedby="fieldError('major_id') ? 'class-major-error' : undefined"
            >
              <option value="" disabled>Pilih jurusan</option>
              <option v-for="major in majors" :key="major.id" :value="major.id">{{ major.name }}</option>
            </select>
            <p v-if="fieldError('major_id')" id="class-major-error" class="field-error">{{ fieldError('major_id') }}</p>
            <p v-else-if="!majors.length" class="field-hint">
              Belum ada jurusan. <router-link to="/master/majors" class="link">Tambah jurusan</router-link>
            </p>
          </div>
          <div>
            <label for="class-year" class="field-label">Tahun pelajaran</label>
            <select
              id="class-year"
              v-model="form.academic_year_id"
              required
              class="field-input"
              :aria-invalid="fieldError('academic_year_id') ? 'true' : 'false'"
              :aria-describedby="fieldError('academic_year_id') ? 'class-year-error' : undefined"
            >
              <option value="" disabled>Pilih tahun pelajaran</option>
              <option v-for="year in academicYears" :key="year.id" :value="year.id">
                {{ year.name }}{{ year.is_active ? ' (aktif)' : '' }}
              </option>
            </select>
            <p v-if="fieldError('academic_year_id')" id="class-year-error" class="field-error">
              {{ fieldError('academic_year_id') }}
            </p>
            <p v-else-if="!academicYears.length" class="field-hint">
              Belum ada tahun pelajaran.
              <router-link to="/master/academic-years" class="link">Tambah tahun pelajaran</router-link>
            </p>
          </div>

          <div class="sm:col-span-2">
            <label for="class-teacher" class="field-label">Wali kelas <span class="font-normal text-tinta-2">(boleh dikosongkan)</span></label>
            <input
              id="class-teacher"
              v-model="form.homeroom_teacher"
              type="text"
              class="field-input"
              placeholder="Nama guru wali kelas"
              :aria-invalid="fieldError('homeroom_teacher') ? 'true' : 'false'"
              :aria-describedby="fieldError('homeroom_teacher') ? 'class-teacher-error' : undefined"
            />
            <p v-if="fieldError('homeroom_teacher')" id="class-teacher-error" class="field-error">
              {{ fieldError('homeroom_teacher') }}
            </p>
          </div>
        </div>

        <label class="flex min-h-11 items-center gap-3">
          <input v-model="form.status" type="checkbox" class="field-check" />
          <span class="font-medium">Kelas aktif</span>
        </label>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="closeModal">Batal</button>
        <button type="submit" form="class-form" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan kelas' }}
        </button>
      </template>
    </BaseModal>
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotice } from '@/composables/useNotice'
import { errorMessage } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const authStore = useAuthStore()
const notice = useNotice()
const canEditData = computed(() => authStore.hasRole('Administrasi'))

const items = ref([])
const majors = ref([])
const academicYears = ref([])
const loading = ref(true)
const loadError = ref('')
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref({})

const missingPrerequisites = computed(() => {
  const missing = []
  if (!majors.value.length) missing.push('jurusan')
  if (!academicYears.value.length) missing.push('tahun pelajaran')
  return missing
})

const activeYearId = computed(() => academicYears.value.find((year) => year.is_active)?.id || '')

const emptyForm = () => ({
  id: null,
  name: '',
  grade: '',
  major_id: '',
  academic_year_id: activeYearId.value,
  homeroom_teacher: '',
  status: true,
})
const form = ref(emptyForm())

const fieldError = (key) => fieldErrors.value[key]?.[0] || ''

const fetchData = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [classesRes, majorsRes, yearsRes] = await Promise.all([
      axios.get('/api/v1/classes'),
      axios.get('/api/v1/majors'),
      axios.get('/api/v1/academic-years'),
    ])
    items.value = classesRes.data.data
    majors.value = majorsRes.data.data
    academicYears.value = yearsRes.data.data
  } catch (error) {
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const refreshClasses = async () => {
  try {
    const response = await axios.get('/api/v1/classes')
    items.value = response.data.data
  } catch (error) {
    notice.error(errorMessage(error, 'Daftar kelas gagal dimuat ulang. Muat ulang halaman.'))
  }
}

const openModal = (item = null) => {
  formError.value = ''
  fieldErrors.value = {}
  form.value = item
    ? {
        ...item,
        status: Boolean(item.status),
        major_id: item.major_id || '',
        academic_year_id: item.academic_year_id || '',
      }
    : emptyForm()
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}

  try {
    if (form.value.id) {
      await axios.put(`/api/v1/classes/${form.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/classes', form.value)
    }
    notice.success(`Kelas ${form.value.name} tersimpan.`)
    closeModal()
    refreshClasses()
  } catch (error) {
    fieldErrors.value = error.response?.data?.errors || {}
    formError.value = errorMessage(error, 'Kelas gagal disimpan.')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (item) => {
  const confirmed = await notice.confirm({
    title: 'Hapus kelas?',
    message: `Kelas ${item.name} akan dihapus. Kelas yang sudah berisi siswa tidak bisa dihapus.`,
    confirmLabel: 'Hapus kelas',
    danger: true,
  })
  if (!confirmed) return

  try {
    await axios.delete(`/api/v1/classes/${item.id}`)
    notice.success(`Kelas ${item.name} dihapus.`)
    refreshClasses()
  } catch (error) {
    notice.error(errorMessage(error, 'Kelas gagal dihapus.'))
  }
}

onMounted(fetchData)
</script>
