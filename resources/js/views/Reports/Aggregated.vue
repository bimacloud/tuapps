<template>
  <div class="report">
    <div class="no-print mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="w-full sm:w-72">
        <label for="aggregated-type" class="field-label">Kelompokkan menurut</label>
        <select id="aggregated-type" v-model="reportType" class="field-input" @change="fetchData">
          <option v-for="option in reportOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" :disabled="!canPrint" @click="printPdf">Cetak laporan</button>
      </div>
    </div>

    <div class="mb-4 hidden print:block">
      <p v-if="schoolStore.name" class="font-semibold">{{ schoolStore.name }}</p>
      <h2 class="text-2xl font-bold">Laporan Rekapitulasi Pembayaran</h2>
      <p>Dikelompokkan per {{ currentOption.noun }}</p>
      <p>Dicetak: {{ formatDateTime(new Date()) }}</p>
    </div>

    <section class="panel overflow-hidden" aria-labelledby="aggregated-heading">
      <div class="no-print border-b border-garis px-5 py-4">
        <h2 id="aggregated-heading" class="text-xl font-bold">Rekap tagihan per {{ currentOption.noun }}</h2>
        <p class="text-sm text-tinta-2">Tagihan, pembayaran, dan sisa tunggakan untuk setiap {{ currentOption.noun }}.</p>
      </div>

      <DataState v-if="loading" state="loading" :message="`Menghitung rekap per ${currentOption.noun}.`" />
      <DataState v-else-if="loadError" state="error" title="Rekapitulasi gagal dimuat." :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        :title="`Belum ada ${currentOption.noun} untuk direkap.`"
        :message="`Rekap muncul setelah data ${currentOption.noun} dibuat di Master Data atau Tagihan.`"
      />

      <div v-else class="overflow-x-auto">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col">{{ currentOption.column }}</th>
              <th v-if="showStudents" scope="col" class="num">Jumlah siswa</th>
              <th scope="col" class="num">Total tagihan</th>
              <th scope="col" class="num">Terbayar</th>
              <th scope="col" class="num">Tunggakan</th>
              <th scope="col" class="num">Persen lunas</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="font-semibold">{{ item.class_name || item.major_name || item.name || '-' }}</td>
              <td v-if="showStudents" class="num">{{ formatAngka(item.total_students) }}</td>
              <td class="num">{{ formatRupiah(item.total_tagihan) }}</td>
              <td class="num">{{ formatRupiah(item.total_pembayaran) }}</td>
              <td class="num text-belum">{{ formatRupiah(item.total_tunggakan) }}</td>
              <td class="num">{{ formatPercent(item.collection_rate, item.total_tagihan) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-garis-kuat font-bold">
              <th scope="row" :colspan="showStudents ? 2 : 1" class="px-4 py-3 text-right">Total keseluruhan</th>
              <td class="num px-4 py-3">{{ formatRupiah(totals.tagihan) }}</td>
              <td class="num px-4 py-3">{{ formatRupiah(totals.pembayaran) }}</td>
              <td class="num px-4 py-3 text-belum">{{ formatRupiah(totals.tunggakan) }}</td>
              <td class="num px-4 py-3">
                {{ totals.tagihan > 0 ? `${Math.round((totals.pembayaran / totals.tagihan) * 100)}%` : '-' }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useSchoolStore } from '@/stores/school'
import { formatRupiah, formatAngka, formatDateTime, errorMessage } from '@/utils/format'
import DataState from '@/components/app/DataState.vue'

const schoolStore = useSchoolStore()

const reportOptions = [
  { value: 'per-kelas', label: 'Kelas', noun: 'kelas', column: 'Kelas' },
  { value: 'per-jurusan', label: 'Jurusan', noun: 'jurusan', column: 'Jurusan' },
  { value: 'per-jenis', label: 'Jenis tagihan', noun: 'jenis tagihan', column: 'Jenis tagihan' },
]

const reportType = ref('per-kelas')
const items = ref([])
const loading = ref(true)
const loadError = ref('')

const currentOption = computed(() => reportOptions.find((option) => option.value === reportType.value) ?? reportOptions[0])
const showStudents = computed(() => reportType.value !== 'per-jenis')
const canPrint = computed(() => !loading.value && !loadError.value && items.value.length > 0)

const totals = computed(() => {
  return items.value.reduce(
    (acc, item) => {
      acc.tagihan += parseFloat(item.total_tagihan) || 0
      acc.pembayaran += parseFloat(item.total_pembayaran) || 0
      acc.tunggakan += parseFloat(item.total_tunggakan) || 0
      return acc
    },
    { tagihan: 0, pembayaran: 0, tunggakan: 0 },
  )
})

/** A rate over zero billed is meaningless, so it shows "-" instead of the API's 0. */
const formatPercent = (rate, billed) => {
  if (rate === null || rate === undefined || !(parseFloat(billed) > 0)) return '-'
  return `${Number(rate).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`
}

let requestId = 0

const fetchData = async () => {
  const thisRequest = ++requestId
  loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get(`/api/v1/reports/${reportType.value}`)
    if (thisRequest !== requestId) return
    items.value = res.data.data
  } catch (error) {
    if (thisRequest !== requestId) return
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    if (thisRequest === requestId) loading.value = false
  }
}

const printPdf = () => {
  window.print()
}

onMounted(() => {
  fetchData()
})
</script>

<style scoped>
@media print {
  .report,
  .report * {
    color: #000 !important;
  }
  .report .panel {
    border: 0;
    border-radius: 0;
  }
  .report .overflow-x-auto {
    overflow: visible;
  }
  .report table {
    font-size: 10pt;
  }
  .report th,
  .report td {
    background: #fff !important;
    border: 1px solid #000 !important;
    padding: 4px 6px !important;
  }
}
</style>
