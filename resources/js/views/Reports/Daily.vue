<template>
  <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark p-6">
    <div class="flex flex-col md:flex-row gap-4 mb-6 justify-between items-end">
      <div class="flex flex-col md:flex-row gap-4">
        <div>
          <label class="mb-2 block text-sm font-medium text-black dark:text-white">Tanggal</label>
          <input type="date" v-model="filterDate" @change="fetchData" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-2 px-4 font-medium outline-none transition focus:border-primary active:border-primary disabled:cursor-default disabled:bg-whiter dark:border-form-strokedark dark:bg-form-input dark:focus:border-primary" />
        </div>
      </div>
      <div class="flex gap-2">
        <button @click="printPdf" class="inline-flex items-center justify-center rounded-md border border-primary py-2 px-4 text-center font-medium text-primary hover:bg-opacity-90 hover:bg-primary hover:text-white">
          Print PDF
        </button>
        <button @click="exportCsv" class="inline-flex items-center justify-center rounded-md bg-primary py-2 px-4 text-center font-medium text-white hover:bg-opacity-90">
          Export CSV
        </button>
      </div>
    </div>

    <div class="overflow-x-auto print-area" id="print-area">
      <div class="hidden print:block mb-6 text-center">
        <h2 class="text-2xl font-bold">Laporan Harian Pembayaran</h2>
        <p class="text-gray-500">Tanggal: {{ filterDate }}</p>
      </div>

      <table class="w-full text-sm text-left">
        <thead class="bg-gray-2 dark:bg-meta-4">
          <tr>
            <th class="py-3 px-4 font-medium text-black dark:text-white">No. Transaksi</th>
            <th class="py-3 px-4 font-medium text-black dark:text-white">Waktu</th>
            <th class="py-3 px-4 font-medium text-black dark:text-white">Siswa</th>
            <th class="py-3 px-4 font-medium text-black dark:text-white">Kelas</th>
            <th class="py-3 px-4 font-medium text-black dark:text-white">Metode</th>
            <th class="py-3 px-4 font-medium text-black dark:text-white">Kasir</th>
            <th class="py-3 px-4 text-right font-medium text-black dark:text-white">Nominal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading" class="border-b border-stroke dark:border-strokedark">
            <td colspan="7" class="py-4 text-center">Memuat data...</td>
          </tr>
          <tr v-else-if="items.length === 0" class="border-b border-stroke dark:border-strokedark">
            <td colspan="7" class="py-4 text-center">Tidak ada transaksi pada tanggal ini.</td>
          </tr>
          <tr v-for="item in items" :key="item.id" class="border-b border-stroke dark:border-strokedark">
            <td class="py-3 px-4">{{ item.transaction_number }}</td>
            <td class="py-3 px-4">{{ formatDate(item.payment_date) }}</td>
            <td class="py-3 px-4">{{ item.student?.name }}</td>
            <td class="py-3 px-4">{{ item.student?.class_room?.name }}</td>
            <td class="py-3 px-4">{{ item.payment_method?.name }}</td>
            <td class="py-3 px-4">{{ item.cashier?.name }}</td>
            <td class="py-3 px-4 text-right font-medium text-success">{{ formatRupiah(item.total_amount) }}</td>
          </tr>
        </tbody>
        <tfoot v-if="items.length > 0">
          <tr class="font-bold bg-gray-50 dark:bg-meta-4">
            <td colspan="6" class="py-3 px-4 text-right">TOTAL</td>
            <td class="py-3 px-4 text-right text-success">{{ formatRupiah(totalAmount) }}</td>
          </tr>
        </tfoot>
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
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const today = new Date().toISOString().split('T')[0]
const filterDate = ref(today)
const items = ref([])
const meta = ref(null)
const loading = ref(false)

const totalAmount = computed(() => {
  return items.value.reduce((sum, item) => sum + parseFloat(item.total_amount), 0)
})

const formatRupiah = (number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0)
}

const formatDate = (dateString) => {
  if(!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const fetchData = async (page = 1) => {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/reports/harian', {
      params: {
        date: filterDate.value,
        page: page
      }
    })
    items.value = res.data.data
    meta.value = res.data.meta
  } catch (error) {
    console.error('Error fetching daily report', error)
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

const exportCsv = () => {
  window.location.href = `/api/v1/reports/harian?date=${filterDate.value}&export=1`
}

onMounted(() => {
  fetchData()
})
</script>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  .print-area, .print-area * {
    visibility: visible;
  }
  .print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
  .print\:block {
    display: block !important;
  }
  .print\:hidden {
    display: none !important;
  }
}
</style>
