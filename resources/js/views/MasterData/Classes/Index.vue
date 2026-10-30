<template>
  <admin-layout>
    <div class="space-y-5 sm:space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Daftar Kelas</h2>
        <button
          v-if="isBendahara"
          @click="openModal()"
          class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white transition bg-brand-500 rounded-lg hover:bg-brand-600"
        >
          Tambah Kelas
        </button>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="max-w-full overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Nama Kelas</p>
                </th>
                <th class="px-5 py-3 text-left">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tingkat</p>
                </th>
                <th class="px-5 py-3 text-left">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Jurusan</p>
                </th>
                <th class="px-5 py-3 text-left">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Wali Kelas</p>
                </th>
                <th class="px-5 py-3 text-left">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tahun Ajaran</p>
                </th>
                <th v-if="isBendahara" class="px-5 py-3 text-right">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Aksi</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="loading">
                <td colspan="6" class="px-5 py-4 text-center">Loading...</td>
              </tr>
              <tr v-else-if="items.length === 0">
                <td colspan="6" class="px-5 py-4 text-center">Tidak ada data.</td>
              </tr>
              <tr v-for="item in items" :key="item.id" class="border-t border-gray-100 dark:border-gray-800">
                <td class="px-5 py-4">
                  <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ item.name }}</span>
                  <span class="block text-gray-400 text-xs mt-1">{{ item.status ? 'Aktif' : 'Nonaktif' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="block text-gray-500 text-theme-sm dark:text-gray-400">{{ item.grade }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="block text-gray-500 text-theme-sm dark:text-gray-400">{{ item.major?.name }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="block text-gray-500 text-theme-sm dark:text-gray-400">{{ item.homeroom_teacher || '-' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="block text-gray-500 text-theme-sm dark:text-gray-400">{{ item.academic_year?.name }}</span>
                </td>
                <td v-if="isBendahara" class="px-5 py-4 text-right">
                  <button @click="openModal(item)" class="text-brand-500 hover:text-brand-600 mr-3 text-sm">Edit</button>
                  <button @click="deleteItem(item.id)" class="text-error-500 hover:text-error-600 text-sm">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg dark:bg-gray-800">
          <form @submit.prevent="saveItem">
            <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
              <h3 class="font-medium text-gray-800 dark:text-white/90">
                {{ form.id ? 'Edit Kelas' : 'Tambah Kelas' }}
              </h3>
              <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600">×</button>
            </div>
            <div class="p-5 space-y-4">
              <div v-if="errorMessage" class="p-3 text-sm text-error-700 bg-error-50 rounded-lg">
                {{ errorMessage }}
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Nama Kelas (cth: X TJKT 1)</label>
                  <input v-model="form.name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                </div>
                <div>
                  <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tingkat (cth: X)</label>
                  <input v-model="form.grade" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Jurusan</label>
                  <select v-model="form.major_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                    <option value="" disabled>Pilih Jurusan</option>
                    <option v-for="major in majors" :key="major.id" :value="major.id">{{ major.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tahun Ajaran</label>
                  <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                    <option value="" disabled>Pilih Tahun Ajaran</option>
                    <option v-for="year in academicYears" :key="year.id" :value="year.id">{{ year.name }}</option>
                  </select>
                </div>
              </div>

              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Wali Kelas</label>
                <input v-model="form.homeroom_teacher" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
              </div>
              
              <div>
                <label class="flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.status" class="w-5 h-5 border-gray-300 rounded text-brand-500 focus:ring-brand-500 dark:bg-gray-900 dark:border-gray-700" />
                  <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">Status Aktif</span>
                </label>
              </div>
            </div>
            <div class="flex justify-end p-5 border-t border-gray-200 dark:border-gray-700 gap-3">
              <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">Batal</button>
              <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-50">
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const authStore = useAuthStore()
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

const items = ref([])
const majors = ref([])
const academicYears = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const form = ref({
  id: null,
  name: '',
  grade: '',
  major_id: '',
  academic_year_id: '',
  homeroom_teacher: '',
  status: true
})

const fetchData = async () => {
  loading.value = true
  try {
    const [classesRes, majorsRes, yearsRes] = await Promise.all([
      axios.get('/api/v1/classes'),
      axios.get('/api/v1/majors'),
      axios.get('/api/v1/academic-years')
    ])
    items.value = classesRes.data.data
    majors.value = majorsRes.data.data
    academicYears.value = yearsRes.data.data
  } catch (error) {
    console.error('Failed to fetch data', error)
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  errorMessage.value = ''
  if (item) {
    form.value = { 
      ...item, 
      status: Boolean(item.status),
      major_id: item.major_id || '',
      academic_year_id: item.academic_year_id || ''
    }
  } else {
    form.value = { 
      id: null, 
      name: '', 
      grade: '', 
      major_id: '', 
      academic_year_id: '', 
      homeroom_teacher: '', 
      status: true 
    }
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  saving.value = true
  errorMessage.value = ''
  
  try {
    if (form.value.id) {
      await axios.put(`/api/v1/classes/${form.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/classes', form.value)
    }
    closeModal()
    // only refresh classes
    const res = await axios.get('/api/v1/classes')
    items.value = res.data.data
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan saat menyimpan'
  } finally {
    saving.value = false
  }
}

const deleteItem = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) return
  
  try {
    await axios.delete(`/api/v1/classes/${id}`)
    const res = await axios.get('/api/v1/classes')
    items.value = res.data.data
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal menghapus data')
  }
}

onMounted(() => {
  fetchData()
})
</script>
