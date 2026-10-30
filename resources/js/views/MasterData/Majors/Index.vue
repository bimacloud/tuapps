<template>
  <admin-layout>
    <div class="space-y-5 sm:space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Daftar Jurusan</h2>
        <button
          v-if="isBendahara"
          @click="openModal()"
          class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white transition bg-brand-500 rounded-lg hover:bg-brand-600"
        >
          Tambah Jurusan
        </button>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="max-w-full overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kode Jurusan</p>
                </th>
                <th class="px-5 py-3 text-left">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Nama Jurusan</p>
                </th>
                <th class="px-5 py-3 text-left">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                </th>
                <th v-if="isBendahara" class="px-5 py-3 text-right">
                  <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Aksi</p>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="loading">
                <td colspan="4" class="px-5 py-4 text-center">Loading...</td>
              </tr>
              <tr v-else-if="items.length === 0">
                <td colspan="4" class="px-5 py-4 text-center">Tidak ada data.</td>
              </tr>
              <tr v-for="item in items" :key="item.id" class="border-t border-gray-100 dark:border-gray-800">
                <td class="px-5 py-4">
                  <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ item.code }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="block text-gray-500 text-theme-sm dark:text-gray-400">{{ item.name }}</span>
                </td>
                <td class="px-5 py-4">
                  <span :class="[ 'rounded-full px-2 py-0.5 text-theme-xs font-medium', item.status ? 'bg-success-50 text-success-700' : 'bg-error-50 text-error-700' ]">
                    {{ item.status ? 'Aktif' : 'Nonaktif' }}
                  </span>
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
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md dark:bg-gray-800">
          <form @submit.prevent="saveItem">
            <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
              <h3 class="font-medium text-gray-800 dark:text-white/90">
                {{ form.id ? 'Edit Jurusan' : 'Tambah Jurusan' }}
              </h3>
              <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600">×</button>
            </div>
            <div class="p-5 space-y-4">
              <div v-if="errorMessage" class="p-3 text-sm text-error-700 bg-error-50 rounded-lg">
                {{ errorMessage }}
              </div>
              
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Kode Jurusan (cth: TJKT)</label>
                <input v-model="form.code" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
              </div>

              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Nama Jurusan</label>
                <input v-model="form.name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
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
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const form = ref({
  id: null,
  code: '',
  name: '',
  status: true
})

const fetchItems = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/majors')
    items.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch data', error)
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  errorMessage.value = ''
  if (item) {
    form.value = { ...item, status: Boolean(item.status) }
  } else {
    form.value = { id: null, code: '', name: '', status: true }
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
      await axios.put(`/api/v1/majors/${form.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/majors', form.value)
    }
    closeModal()
    fetchItems()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan saat menyimpan'
  } finally {
    saving.value = false
  }
}

const deleteItem = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) return
  
  try {
    await axios.delete(`/api/v1/majors/${id}`)
    fetchItems()
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal menghapus data')
  }
}

onMounted(() => {
  fetchItems()
})
</script>
