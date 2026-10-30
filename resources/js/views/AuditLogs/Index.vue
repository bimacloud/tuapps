<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const logs = ref<any[]>([])
const loading = ref(true)

// Pagination
const currentPage = ref(1)
const lastPage = ref(1)

// Filters
const filterModule = ref('')
const filterDate = ref('')

const fetchData = async (page = 1) => {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/audit-logs', {
      params: {
        page,
        module: filterModule.value,
        date: filterDate.value
      }
    })
    logs.value = res.data.data
    currentPage.value = res.data.current_page
    lastPage.value = res.data.last_page
  } catch (error) {
    console.error('Error fetching audit logs', error)
  } finally {
    loading.value = false
  }
}

watch([filterModule, filterDate], () => {
  fetchData(1)
})

const formatDate = (dateString: string) => {
  if (!dateString) return '-'
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(dateString))
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="text-title-md2 font-semibold text-black dark:text-white">Audit Log Sistem</h2>
    </div>

    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
      <div class="p-4 md:p-6 xl:p-7.5">
        
        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-4 mb-6">
          <div class="w-full sm:w-1/3">
            <select v-model="filterModule" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-2 px-4 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input">
              <option value="">Semua Modul</option>
              <option value="PAYMENT">Pembayaran</option>
              <option value="CASHIER_SESSION">Sesi Kasir</option>
              <option value="APPROVAL">Persetujuan</option>
              <option value="AUTH">Otentikasi</option>
            </select>
          </div>
          <div class="w-full sm:w-1/3">
            <input v-model="filterDate" type="date" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-2 px-4 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" />
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full table-auto text-sm">
            <thead>
              <tr class="bg-gray-2 text-left dark:bg-meta-4">
                <th class="py-4 px-4 font-medium text-black dark:text-white xl:px-6">Waktu</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Pengguna</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Modul</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Aksi</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white w-1/3">Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="5" class="text-center py-8">Memuat data...</td>
              </tr>
              <tr v-else-if="logs.length === 0">
                <td colspan="5" class="text-center py-8 text-gray-500">Tidak ada log ditemukan.</td>
              </tr>
              <tr v-else v-for="log in logs" :key="log.id" class="border-b border-stroke dark:border-strokedark hover:bg-gray-50 dark:hover:bg-meta-4/20">
                <td class="py-4 px-4 xl:px-6">{{ formatDate(log.created_at) }}</td>
                <td class="py-4 px-4 font-medium">{{ log.user?.name || 'Sistem' }}</td>
                <td class="py-4 px-4">
                  <span class="inline-flex rounded bg-primary/10 py-1 px-2 text-xs font-medium text-primary">
                    {{ log.module }}
                  </span>
                </td>
                <td class="py-4 px-4">{{ log.action }}</td>
                <td class="py-4 px-4 text-gray-600 dark:text-gray-400">{{ log.description }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="mt-6 flex justify-end gap-2">
          <button @click="fetchData(currentPage - 1)" :disabled="currentPage === 1" class="rounded px-3 py-1 text-sm bg-gray-2 dark:bg-meta-4 hover:bg-gray-3 disabled:opacity-50">
            &larr; Prev
          </button>
          <span class="px-3 py-1 text-sm font-medium">Page {{ currentPage }} of {{ lastPage }}</span>
          <button @click="fetchData(currentPage + 1)" :disabled="currentPage === lastPage" class="rounded px-3 py-1 text-sm bg-gray-2 dark:bg-meta-4 hover:bg-gray-3 disabled:opacity-50">
            Next &rarr;
          </button>
        </div>

      </div>
    </div>
  </div>
</template>
