<template>
  <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark p-6">
    <div class="flex flex-col md:flex-row gap-4 mb-6 justify-between items-end">
      <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
        <div class="w-full md:w-64">
          <label class="mb-2 block text-sm font-medium text-black dark:text-white">Jenis Rekapitulasi</label>
          <select v-model="reportType" @change="fetchData" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-2 px-4 font-medium outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input dark:focus:border-primary">
            <option value="per-kelas">Per Kelas</option>
            <option value="per-jurusan">Per Jurusan</option>
            <option value="per-jenis">Per Jenis Tagihan</option>
          </select>
        </div>
      </div>
      <div class="flex gap-2">
        <button @click="printPdf" class="inline-flex items-center justify-center rounded-md border border-primary py-2 px-4 text-center font-medium text-primary hover:bg-opacity-90 hover:bg-primary hover:text-white">
          Print PDF
        </button>
      </div>
    </div>

    <div class="overflow-x-auto print-area" id="print-area">
      <div class="hidden print:block mb-6 text-center">
        <h2 class="text-2xl font-bold">Laporan Rekapitulasi Pembayaran</h2>
        <p class="text-gray-500 uppercase">{{ reportType.replace('-', ' ') }}</p>
      </div>

      <table class="w-full text-sm text-left">
        <thead class="bg-gray-2 dark:bg-meta-4">
          <tr>
            <th class="py-3 px-4 font-medium text-black dark:text-white">Kategori</th>
            <th v-if="reportType !== 'per-jenis'" class="py-3 px-4 font-medium text-center text-black dark:text-white">Jml Siswa</th>
            <th class="py-3 px-4 text-right font-medium text-black dark:text-white">Total Tagihan</th>
            <th class="py-3 px-4 text-right font-medium text-black dark:text-white">Terbayar</th>
            <th class="py-3 px-4 text-right font-medium text-black dark:text-white">Tunggakan</th>
            <th class="py-3 px-4 text-center font-medium text-black dark:text-white">% Lunas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading" class="border-b border-stroke dark:border-strokedark">
            <td colspan="6" class="py-4 text-center">Memuat data...</td>
          </tr>
          <tr v-else-if="items.length === 0" class="border-b border-stroke dark:border-strokedark">
            <td colspan="6" class="py-4 text-center">Tidak ada data rekapitulasi.</td>
          </tr>
          <tr v-for="item in items" :key="item.id" class="border-b border-stroke dark:border-strokedark">
            <td class="py-3 px-4 font-medium">
              {{ item.class_name || item.major_name || item.name }}
            </td>
            <td v-if="reportType !== 'per-jenis'" class="py-3 px-4 text-center">{{ item.total_students }}</td>
            <td class="py-3 px-4 text-right">{{ formatRupiah(item.total_tagihan) }}</td>
            <td class="py-3 px-4 text-right text-success">{{ formatRupiah(item.total_pembayaran) }}</td>
            <td class="py-3 px-4 text-right text-danger">{{ formatRupiah(item.total_tunggakan) }}</td>
            <td class="py-3 px-4 text-center">
              <span class="inline-flex rounded-full bg-opacity-10 py-1 px-3 text-xs font-medium" :class="item.collection_rate > 70 ? 'bg-success text-success' : 'bg-warning text-warning'">
                {{ item.collection_rate }}%
              </span>
            </td>
          </tr>
        </tbody>
        <tfoot v-if="items.length > 0">
          <tr class="font-bold bg-gray-50 dark:bg-meta-4">
            <td :colspan="reportType !== 'per-jenis' ? 2 : 1" class="py-3 px-4 text-right">TOTAL KESELURUHAN</td>
            <td class="py-3 px-4 text-right">{{ formatRupiah(totals.tagihan) }}</td>
            <td class="py-3 px-4 text-right text-success">{{ formatRupiah(totals.pembayaran) }}</td>
            <td class="py-3 px-4 text-right text-danger">{{ formatRupiah(totals.tunggakan) }}</td>
            <td class="py-3 px-4 text-center">
              {{ totals.tagihan > 0 ? Math.round((totals.pembayaran / totals.tagihan) * 100) : 0 }}%
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const reportType = ref('per-kelas')
const items = ref([])
const loading = ref(false)

const totals = computed(() => {
  return items.value.reduce((acc, item) => {
    acc.tagihan += parseFloat(item.total_tagihan)
    acc.pembayaran += parseFloat(item.total_pembayaran)
    acc.tunggakan += parseFloat(item.total_tunggakan)
    return acc
  }, { tagihan: 0, pembayaran: 0, tunggakan: 0 })
})

const formatRupiah = (number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0)
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await axios.get(`/api/v1/reports/${reportType.value}`)
    items.value = res.data.data
  } catch (error) {
    console.error('Error fetching aggregated report', error)
  } finally {
    loading.value = false
  }
}

const printPdf = () => {
  window.print()
}

onMounted(() => {
  fetchData()
})
</script>
