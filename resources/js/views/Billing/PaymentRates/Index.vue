<template>
  <admin-layout>
    <div class="space-y-5 sm:space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Tarif Pembayaran</h2>
        <div class="flex gap-2" v-if="isBendahara">
          <button @click="openModal()" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white transition bg-brand-500 rounded-lg hover:bg-brand-600">
            Tambah Tarif
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="p-4 bg-white rounded-xl border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex flex-wrap gap-4 items-end">
        <div class="w-48">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Tahun Pelajaran</label>
          <select v-model="filters.academic_year_id" @change="fetchData" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            <option value="">Semua Tahun</option>
            <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">{{ ay.name }}</option>
          </select>
        </div>
      </div>

      <!-- Data Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="max-w-full overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <th class="px-5 py-3 text-left w-16">No</th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Jenis Pembayaran</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tahun Pelajaran</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Target Kelas</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Nominal Tarif (Rp)</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p></th>
                <th v-if="isBendahara" class="px-5 py-3 text-right"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Aksi</p></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="loading">
                <td colspan="7" class="px-5 py-4 text-center">Loading...</td>
              </tr>
              <tr v-else-if="items.length === 0">
                <td colspan="7" class="px-5 py-4 text-center">Tidak ada data tarif.</td>
              </tr>
              <tr v-for="(item, index) in items" :key="item.id" class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="px-5 py-4 text-sm text-gray-500">{{ index + 1 }}</td>
                <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90 font-medium">
                  {{ item.payment_type?.name }}
                  <span class="block text-xs text-gray-500">{{ item.payment_type?.code }} - {{ item.payment_type?.type }}</span>
                </td>
                <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">{{ item.academic_year?.name }}</td>
                <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">
                  {{ item.class_room ? item.class_room.name : 'Semua Kelas' }}
                </td>
                <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90 font-bold">
                  {{ formatRupiah(item.amount) }}
                </td>
                <td class="px-5 py-4">
                  <span :class="item.status ? 'bg-success-50 text-success-700' : 'bg-error-50 text-error-700'" class="rounded-full px-2 py-0.5 text-theme-xs font-medium">
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

      <!-- Form Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg dark:bg-gray-800 my-auto">
          <form @submit.prevent="saveItem">
            <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
              <h3 class="font-medium text-gray-800 dark:text-white/90">
                {{ form.id ? 'Edit Tarif Pembayaran' : 'Tambah Tarif Pembayaran' }}
              </h3>
              <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600">×</button>
            </div>
            
            <div class="p-5 space-y-4">
              <div v-if="errorMessage" class="p-3 text-sm text-error-700 bg-error-50 rounded-lg">
                {{ errorMessage }}
              </div>
              
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Pembayaran *</label>
                <select v-model="form.payment_type_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                  <option value="" disabled>Pilih Jenis Pembayaran</option>
                  <option v-for="pt in activePaymentTypes" :key="pt.id" :value="pt.id">{{ pt.code }} - {{ pt.name }} ({{ pt.type }})</option>
                </select>
              </div>

              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tahun Pelajaran *</label>
                <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                  <option value="" disabled>Pilih Tahun Pelajaran</option>
                  <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">{{ ay.name }}</option>
                </select>
              </div>

              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Target Kelas (Kosongkan jika berlaku untuk semua kelas)</label>
                <select v-model="form.class_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                  <option value="">Semua Kelas</option>
                  <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Nominal Tarif (Rp) *</label>
                <input v-model="form.amount" type="number" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
              </div>

              <div class="flex items-center gap-2 pt-2">
                <input v-model="form.status" type="checkbox" id="statusRate" class="w-4 h-4 text-brand-500 border-gray-300 rounded focus:ring-brand-500 dark:bg-gray-900 dark:border-gray-700" />
                <label for="statusRate" class="text-sm font-medium text-gray-700 dark:text-gray-300">Tarif Aktif</label>
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
const paymentTypes = ref([])
const academicYears = ref([])
const classes = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const filters = ref({
  academic_year_id: ''
})

const activePaymentTypes = computed(() => paymentTypes.value.filter(pt => pt.status))

const form = ref({
  id: null,
  payment_type_id: '',
  academic_year_id: '',
  class_id: '',
  amount: '',
  status: true
})

const formatRupiah = (number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number)
}

const fetchDependencies = async () => {
  try {
    const [ptRes, ayRes, clRes] = await Promise.all([
      axios.get('/api/v1/payment-types'),
      axios.get('/api/v1/academic-years'),
      axios.get('/api/v1/classes')
    ])
    paymentTypes.value = ptRes.data.data
    academicYears.value = ayRes.data.data
    classes.value = clRes.data.data
    
    // Set default filter to active academic year
    const activeYear = academicYears.value.find(y => y.is_active)
    if (activeYear) {
      filters.value.academic_year_id = activeYear.id
    }
  } catch (error) {
    console.error(error)
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/payment-rates', { params: filters.value })
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
    form.value = { 
      id: item.id,
      payment_type_id: item.payment_type_id,
      academic_year_id: item.academic_year_id,
      class_id: item.class_id || '',
      amount: item.amount,
      status: item.status
    }
  } else {
    // Default to active year
    const activeYear = academicYears.value.find(y => y.is_active)
    form.value = { id: null, payment_type_id: '', academic_year_id: activeYear ? activeYear.id : '', class_id: '', amount: '', status: true }
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  saving.value = true
  errorMessage.value = ''
  
  const payload = { ...form.value }
  if (payload.class_id === '') payload.class_id = null

  try {
    if (form.value.id) {
      await axios.put(`/api/v1/payment-rates/${form.value.id}`, payload)
    } else {
      await axios.post('/api/v1/payment-rates', payload)
    }
    closeModal()
    fetchData()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan saat menyimpan'
  } finally {
    saving.value = false
  }
}

const deleteItem = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus tarif ini?')) return
  
  try {
    await axios.delete(`/api/v1/payment-rates/${id}`)
    fetchData()
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal menghapus data')
  }
}

onMounted(async () => {
  await fetchDependencies()
  fetchData()
})
</script>
