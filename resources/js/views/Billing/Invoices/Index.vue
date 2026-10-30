<template>
  <admin-layout>
    <div class="space-y-5 sm:space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Daftar Tagihan</h2>
        <div class="flex gap-2" v-if="isBendahara">
          <router-link to="/invoices/generate" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white transition bg-brand-500 rounded-lg hover:bg-brand-600">
            Generate Tagihan Massal
          </router-link>
        </div>
      </div>

      <!-- Filters -->
      <div class="p-4 bg-white rounded-xl border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Pencarian (No. Invoice, Nama Siswa)</label>
          <input v-model="filters.search" @keyup.enter="fetchData" type="text" placeholder="Ketik lalu Enter..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
        </div>
        <div class="w-48">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Tahun Pelajaran</label>
          <select v-model="filters.academic_year_id" @change="fetchData" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            <option value="">Semua Tahun</option>
            <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">{{ ay.name }}</option>
          </select>
        </div>
        <div class="w-48">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Kelas</label>
          <select v-model="filters.class_id" @change="fetchData" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            <option value="">Semua Kelas</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="w-40">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
          <select v-model="filters.status" @change="fetchData" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            <option value="">Semua Status</option>
            <option value="BELUM BAYAR">BELUM BAYAR</option>
            <option value="SEBAGIAN">SEBAGIAN</option>
            <option value="LUNAS">LUNAS</option>
            <option value="CANCELLED">CANCELLED</option>
          </select>
        </div>
        <div>
          <button @click="resetFilters" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Reset</button>
        </div>
      </div>

      <!-- Data Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="max-w-full overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <th class="px-5 py-3 text-left w-16">No</th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">No. Invoice</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Siswa</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kelas</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Total / Sisa</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p></th>
                <th class="px-5 py-3 text-right"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Aksi</p></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="loading">
                <td colspan="7" class="px-5 py-4 text-center">Loading...</td>
              </tr>
              <tr v-else-if="items.length === 0">
                <td colspan="7" class="px-5 py-4 text-center">Tidak ada data tagihan.</td>
              </tr>
              <tr v-for="(item, index) in items" :key="item.id" class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="px-5 py-4 text-sm text-gray-500">{{ (pagination.current_page - 1) * pagination.per_page + index + 1 }}</td>
                <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90 font-medium">
                  {{ item.invoice_number }}
                  <span class="block text-xs text-gray-500 font-normal">{{ formatDate(item.created_at) }}</span>
                </td>
                <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">
                  {{ item.student?.name }}
                  <span class="block text-xs text-gray-500 font-normal">{{ item.student?.nis }} / {{ item.student?.nisn }}</span>
                </td>
                <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">
                  {{ item.student?.class_room?.name }}
                </td>
                <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">
                  <span class="block font-medium">{{ formatRupiah(item.total_amount) }}</span>
                  <span v-if="item.remaining_amount > 0" class="block text-xs text-error-600 font-medium">Sisa: {{ formatRupiah(item.remaining_amount) }}</span>
                </td>
                <td class="px-5 py-4">
                  <span :class="getStatusClass(item.status)" class="rounded-full px-2 py-0.5 text-theme-xs font-medium">
                    {{ item.status }}
                  </span>
                </td>
                <td class="px-5 py-4 text-right">
                  <button @click="showDetail(item)" class="text-brand-500 hover:text-brand-600 text-sm">Lihat Detail</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="flex items-center justify-between px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
          <span class="text-sm text-gray-500 dark:text-gray-400">
            Menampilkan {{ pagination.from }} - {{ pagination.to }} dari {{ pagination.total }} tagihan
          </span>
          <div class="flex gap-2">
            <button 
              @click="changePage(pagination.current_page - 1)" 
              :disabled="!pagination.prev_page_url"
              class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >Prev</button>
            <button 
              @click="changePage(pagination.current_page + 1)" 
              :disabled="!pagination.next_page_url"
              class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >Next</button>
          </div>
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
const academicYears = ref([])
const classes = ref([])
const loading = ref(true)

const filters = ref({
  search: '',
  academic_year_id: '',
  class_id: '',
  status: ''
})

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
  prev_page_url: null,
  next_page_url: null
})

const formatRupiah = (number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number)
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID')
}

const getStatusClass = (status) => {
  switch(status) {
    case 'LUNAS': return 'bg-success-50 text-success-700'
    case 'BELUM BAYAR': return 'bg-error-50 text-error-700'
    case 'SEBAGIAN': return 'bg-warning-50 text-warning-700'
    case 'CANCELLED': return 'bg-gray-100 text-gray-600'
    default: return 'bg-gray-100 text-gray-600'
  }
}

const fetchDependencies = async () => {
  try {
    const [ayRes, clRes] = await Promise.all([
      axios.get('/api/v1/academic-years'),
      axios.get('/api/v1/classes')
    ])
    academicYears.value = ayRes.data.data
    classes.value = clRes.data.data
  } catch (error) {
    console.error(error)
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const params = {
      ...filters.value,
      page: pagination.value.current_page
    }
    const response = await axios.get('/api/v1/invoices', { params })
    items.value = response.data.data
    
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      per_page: response.data.per_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url
    }
  } catch (error) {
    console.error('Failed to fetch data', error)
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return
  pagination.value.current_page = page
  fetchData()
}

const resetFilters = () => {
  filters.value = { search: '', academic_year_id: '', class_id: '', status: '' }
  pagination.value.current_page = 1
  fetchData()
}

const showDetail = (item) => {
  // Arahkan ke halaman detail invoice jika sudah dibuat
  alert(`Menampilkan detail invoice ${item.invoice_number}. Fitur Detail penuh akan ada di halaman terpisah.`)
}

onMounted(async () => {
  await fetchDependencies()
  fetchData()
})
</script>
