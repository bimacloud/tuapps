<template>
  <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark p-6">
    <div class="flex flex-col md:flex-row gap-4 mb-6 justify-between items-end">
      <div class="flex flex-col md:flex-row gap-4">
        <!-- Class filter could be added here if needed -->
      </div>
      <div class="flex gap-2">
        <button @click="printPdf" class="inline-flex items-center justify-center rounded-md border border-primary py-2 px-4 text-center font-medium text-primary hover:bg-opacity-90 hover:bg-primary hover:text-white">
          Print PDF
        </button>
      </div>
    </div>

    <div class="overflow-x-auto print-area" id="print-area">
      <div class="hidden print:block mb-6 text-center">
        <h2 class="text-2xl font-bold">Laporan Tunggakan Siswa</h2>
      </div>

      <table class="w-full text-sm text-left">
        <thead class="bg-gray-2 dark:bg-meta-4">
          <tr>
            <th class="py-3 px-4 font-medium text-black dark:text-white">NIS</th>
            <th class="py-3 px-4 font-medium text-black dark:text-white">Siswa</th>
            <th class="py-3 px-4 font-medium text-black dark:text-white">Kelas</th>
            <th class="py-3 px-4 text-right font-medium text-black dark:text-white">Total Tagihan</th>
            <th class="py-3 px-4 text-right font-medium text-black dark:text-white">Dibayar</th>
            <th class="py-3 px-4 text-right font-medium text-black dark:text-white">Sisa Tunggakan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading" class="border-b border-stroke dark:border-strokedark">
            <td colspan="6" class="py-4 text-center">Memuat data...</td>
          </tr>
          <tr v-else-if="items.length === 0" class="border-b border-stroke dark:border-strokedark">
            <td colspan="6" class="py-4 text-center">Tidak ada siswa yang menunggak.</td>
          </tr>
          <tr v-for="item in items" :key="item.id" class="border-b border-stroke dark:border-strokedark">
            <td class="py-3 px-4">{{ item.student?.nis }}</td>
            <td class="py-3 px-4 font-medium text-black dark:text-white">
              <router-link :to="`/siswa/${item.student?.id}/rekap`" class="hover:text-primary">{{ item.student?.name }}</router-link>
            </td>
            <td class="py-3 px-4">{{ item.student?.class_room?.name }}</td>
            <td class="py-3 px-4 text-right">{{ formatRupiah(item.total_amount) }}</td>
            <td class="py-3 px-4 text-right text-success">{{ formatRupiah(item.paid_amount) }}</td>
            <td class="py-3 px-4 text-right font-bold text-danger">{{ formatRupiah(item.remaining_amount) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="mt-4 flex justify-between items-center print:hidden">
      <span class="text-sm text-gray-500">Hal {{ meta.current_page }} dari {{ meta.last_page }}</span>
      <div class="flex gap-2">
        <button @click="changePage(meta.current_page - 1)" :disabled="meta.current_page === 1" class="px-3 py-1 rounded bg-gray-200 dark:bg-meta-4 disabled:opacity-50">Prev</button>
        <button @click="changePage(meta.current_page + 1)" :disabled="meta.current_page === meta.last_page" class="px-3 py-1 rounded bg-gray-200 dark:bg-meta-4 disabled:opacity-50">Next</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const items = ref([])
const meta = ref(null)
const loading = ref(false)

const formatRupiah = (number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0)
}

const fetchData = async (page = 1) => {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/reports/tunggakan', {
      params: { page: page }
    })
    items.value = res.data.data
    meta.value = res.data.meta
  } catch (error) {
    console.error('Error fetching arrears report', error)
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  fetchData(page)
}

const printPdf = () => {
  window.print()
}

onMounted(() => {
  fetchData()
})
</script>
