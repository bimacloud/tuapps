<template>
  <admin-layout>
    <div class="space-y-6">
      
      <!-- Kepala Sekolah Metrics -->
      <template v-if="isKepsek">
        <div class="grid grid-cols-12 gap-4 md:gap-6">
          <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="rounded-sm border border-stroke bg-white py-6 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark">
              <h3 class="text-gray-500 text-sm font-medium mb-2">Total Siswa Aktif</h3>
              <p class="text-2xl font-bold text-black dark:text-white">{{ metricsKepsek?.total_siswa }}</p>
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="rounded-sm border border-stroke bg-white py-6 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark">
              <h3 class="text-gray-500 text-sm font-medium mb-2">Total Tagihan</h3>
              <p class="text-2xl font-bold text-black dark:text-white">{{ formatRupiah(metricsKepsek?.total_tagihan) }}</p>
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="rounded-sm border border-stroke bg-white py-6 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark">
              <h3 class="text-gray-500 text-sm font-medium mb-2">Total Pembayaran</h3>
              <p class="text-2xl font-bold text-black dark:text-white">{{ formatRupiah(metricsKepsek?.total_pembayaran) }}</p>
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="rounded-sm border border-stroke bg-white py-6 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark">
              <h3 class="text-gray-500 text-sm font-medium mb-2">Total Tunggakan</h3>
              <p class="text-2xl font-bold text-danger">{{ formatRupiah(metricsKepsek?.total_tunggakan) }}</p>
            </div>
          </div>
          <div class="col-span-12">
            <div class="rounded-sm border border-stroke bg-white py-4 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark flex justify-between items-center">
              <span class="font-medium">Collection Rate (Tingkat Ketertiban Bayar)</span>
              <span class="text-xl font-bold" :class="metricsKepsek?.collection_rate > 70 ? 'text-success' : 'text-warning'">{{ metricsKepsek?.collection_rate }}%</span>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-12 gap-4 md:gap-6 mt-6">
          <div class="col-span-12 xl:col-span-8">
            <div class="rounded-sm border border-stroke bg-white p-7.5 shadow-default dark:border-strokedark dark:bg-boxdark">
              <h4 class="mb-4 text-xl font-semibold text-black dark:text-white">Pemasukan 6 Bulan Terakhir</h4>
              <apexchart type="bar" height="350" :options="chartOptionsIncome" :series="seriesIncome"></apexchart>
            </div>
          </div>
          <div class="col-span-12 xl:col-span-4">
            <div class="rounded-sm border border-stroke bg-white p-7.5 shadow-default dark:border-strokedark dark:bg-boxdark h-full">
              <h4 class="mb-4 text-xl font-semibold text-black dark:text-white">Status Pembayaran</h4>
              <apexchart type="pie" height="350" :options="chartOptionsStatus" :series="seriesStatus"></apexchart>
            </div>
          </div>
        </div>
      </template>

      <!-- Bendahara Metrics -->
      <template v-else-if="isBendahara">
        <div class="grid grid-cols-12 gap-4 md:gap-6">
          <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="rounded-sm border border-stroke bg-white py-6 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark border-l-4 border-l-primary">
              <h3 class="text-gray-500 text-sm font-medium mb-2">Transaksi Hari Ini</h3>
              <p class="text-2xl font-bold text-black dark:text-white">{{ metricsBendahara?.transaksi_hari_ini }}</p>
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="rounded-sm border border-stroke bg-white py-6 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark border-l-4 border-l-success">
              <h3 class="text-gray-500 text-sm font-medium mb-2">Pemasukan Hari Ini</h3>
              <p class="text-2xl font-bold text-success">{{ formatRupiah(metricsBendahara?.pemasukan_hari_ini) }}</p>
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="rounded-sm border border-stroke bg-white py-6 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark border-l-4 border-l-success">
              <h3 class="text-gray-500 text-sm font-medium mb-2">Pemasukan Bulan Ini</h3>
              <p class="text-2xl font-bold text-black dark:text-white">{{ formatRupiah(metricsBendahara?.pemasukan_bulan_ini) }}</p>
            </div>
          </div>
          <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="rounded-sm border border-stroke bg-white py-6 px-7.5 shadow-default dark:border-strokedark dark:bg-boxdark border-l-4 border-l-danger">
              <h3 class="text-gray-500 text-sm font-medium mb-2">Total Tunggakan</h3>
              <p class="text-2xl font-bold text-danger">{{ formatRupiah(metricsBendahara?.total_tunggakan) }}</p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-12 gap-4 md:gap-6 mt-6">
          <div class="col-span-12 xl:col-span-8">
            <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
              <div class="border-b border-stroke py-4 px-6.5 dark:border-strokedark">
                <h3 class="font-medium text-black dark:text-white">Transaksi Terbaru</h3>
              </div>
              <div class="p-6.5">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="text-left bg-gray-2 dark:bg-meta-4">
                      <th class="py-2 px-4 font-medium text-black dark:text-white">Waktu</th>
                      <th class="py-2 px-4 font-medium text-black dark:text-white">Siswa</th>
                      <th class="py-2 px-4 font-medium text-black dark:text-white">Metode</th>
                      <th class="py-2 px-4 text-right font-medium text-black dark:text-white">Nominal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="trx in metricsBendahara?.recent_transactions" :key="trx.id" class="border-b border-stroke dark:border-strokedark">
                      <td class="py-3 px-4">{{ formatDate(trx.payment_date) }}</td>
                      <td class="py-3 px-4">{{ trx.student?.name }} <br><span class="text-xs text-gray-500">{{ trx.student?.class_room?.name }}</span></td>
                      <td class="py-3 px-4">{{ trx.payment_method?.name }}</td>
                      <td class="py-3 px-4 text-right font-bold text-success">{{ formatRupiah(trx.total_amount) }}</td>
                    </tr>
                    <tr v-if="!metricsBendahara?.recent_transactions?.length">
                      <td colspan="4" class="py-4 text-center text-gray-500">Belum ada transaksi</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-span-12 xl:col-span-4">
            <div class="rounded-sm border border-stroke bg-white p-7.5 shadow-default dark:border-strokedark dark:bg-boxdark h-full text-center flex flex-col justify-center items-center">
              <div class="mb-4 text-warning">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
              </div>
              <h4 class="mb-2 text-2xl font-semibold text-black dark:text-white">{{ metricsBendahara?.siswa_menunggak }} Siswa</h4>
              <p class="text-gray-500 mb-6">Memiliki tagihan tertunggak saat ini</p>
              
              <router-link to="/kasir" class="inline-flex items-center justify-center rounded-md bg-primary py-3 px-6 text-center font-medium text-white hover:bg-opacity-90">
                + Buka Kasir Pembayaran
              </router-link>
            </div>
          </div>
        </div>
      </template>

    </div>
  </admin-layout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import AdminLayout from '../components/layout/AdminLayout.vue'
import VueApexCharts from 'vue3-apexcharts'

const authStore = useAuthStore()
const isKepsek = computed(() => authStore.hasRole('Kepala Sekolah'))
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

const metricsKepsek = ref({
  total_siswa: 0,
  total_tagihan: 0,
  total_pembayaran: 0,
  total_tunggakan: 0,
  collection_rate: 0
})

const metricsBendahara = ref({
  pemasukan_hari_ini: 0,
  transaksi_hari_ini: 0,
  pemasukan_bulan_ini: 0,
  total_tunggakan: 0,
  siswa_menunggak: 0,
  recent_transactions: []
})

const seriesIncome = ref([{ name: 'Pendapatan', data: [] }])
const chartOptionsIncome = ref({
  chart: { type: 'bar' },
  colors: ['#3C50E0'],
  xaxis: { categories: [] },
  dataLabels: { enabled: false },
})

const seriesStatus = ref([])
const chartOptionsStatus = ref({
  chart: { type: 'pie' },
  labels: [],
  colors: ['#10B981', '#F59E0B', '#EF4444'], // Lunas, Sebagian, Belum
})

const formatRupiah = (number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0)
}

const formatDate = (dateString) => {
  if(!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const fetchMetrics = async () => {
  try {
    if (isKepsek.value) {
      const res = await axios.get('/api/v1/dashboard/metrics-kepsek')
      metricsKepsek.value = res.data?.data || {
        total_siswa: 0, total_tagihan: 0, total_pembayaran: 0, total_tunggakan: 0, collection_rate: 0
      }

      const chartsRes = await axios.get('/api/v1/dashboard/charts')
      const charts = chartsRes.data?.data
      
      if (charts) {
        // Map Monthly Income
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        seriesIncome.value[0].data = charts.monthly_income?.map(m => m.total) || []
        chartOptionsIncome.value = {
          ...chartOptionsIncome.value,
          xaxis: { categories: charts.monthly_income?.map(m => months[m.month - 1] + ' ' + m.year) || [] }
        }

        // Map Payment Status
        seriesStatus.value = charts.payment_status?.map(s => s.total) || []
        chartOptionsStatus.value = {
          ...chartOptionsStatus.value,
          labels: charts.payment_status?.map(s => s.status) || []
        }
      }
    } 
    else if (isBendahara.value) {
      const res = await axios.get('/api/v1/dashboard/metrics-bendahara')
      metricsBendahara.value = res.data?.data || {
        pemasukan_hari_ini: 0, transaksi_hari_ini: 0, pemasukan_bulan_ini: 0, total_tunggakan: 0, siswa_menunggak: 0, recent_transactions: []
      }
    }
  } catch (error) {
    console.error('Failed to load metrics', error)
  }
}

onMounted(() => {
  fetchMetrics()
})
</script>
