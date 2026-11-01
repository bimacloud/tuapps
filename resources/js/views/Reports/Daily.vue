<template>
  <div class="report">
    <form class="no-print mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between" @submit.prevent="fetchData(1)">
      <div class="w-full sm:w-60">
        <label for="daily-date" class="field-label">Tanggal pembayaran</label>
        <input
          id="daily-date"
          v-model="filterDate"
          type="date"
          required
          class="field-input"
          :aria-invalid="dateError ? 'true' : undefined"
          :aria-describedby="dateError ? 'daily-date-error' : undefined"
          @change="fetchData(1)"
        />
        <p v-if="dateError" id="daily-date-error" class="field-error">{{ dateError }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" :disabled="!canExport" @click="printPdf">Cetak laporan</button>
        <button type="button" class="btn btn-secondary" :disabled="!canExport || exporting" @click="exportCsv">
          {{ exporting ? 'Menyiapkan CSV...' : 'Unduh CSV' }}
        </button>
      </div>
    </form>

    <div class="mb-4 hidden print:block">
      <p v-if="schoolStore.name" class="font-semibold">{{ schoolStore.name }}</p>
      <h2 class="text-2xl font-bold">Laporan Harian Pembayaran</h2>
      <p>Tanggal: {{ formatDate(filterDate) }}</p>
      <p v-if="meta && meta.last_page > 1">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</p>
    </div>

    <section class="panel overflow-hidden" aria-labelledby="daily-heading">
      <div class="no-print flex flex-wrap items-baseline justify-between gap-2 border-b border-garis px-5 py-4">
        <h2 id="daily-heading" class="text-xl font-bold">Pembayaran {{ formatDate(filterDate) }}</h2>
        <p v-if="!loading && !loadError && meta" class="text-sm text-tinta-2">
          <span class="font-semibold tabular-nums text-tinta">{{ formatAngka(meta.total) }}</span> transaksi berhasil
        </p>
      </div>

      <DataState v-if="loading" state="loading" message="Mengambil pembayaran pada tanggal ini." />
      <DataState v-else-if="loadError" state="error" title="Laporan harian gagal dimuat." :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData(currentPage)">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        :title="`Tidak ada pembayaran pada ${formatDate(filterDate)}.`"
        message="Hanya pembayaran berhasil yang masuk laporan ini. Pilih tanggal lain untuk melihat transaksi."
      >
        <button v-if="filterDate !== today" type="button" class="btn btn-secondary" @click="showToday">Lihat hari ini</button>
      </DataState>

      <div v-else class="overflow-x-auto">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col">No. transaksi</th>
              <th scope="col">Waktu</th>
              <th scope="col">Siswa</th>
              <th scope="col">Kelas</th>
              <th scope="col">Metode</th>
              <th scope="col">Kasir</th>
              <th scope="col" class="num">Nominal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="whitespace-nowrap tabular-nums">{{ item.transaction_number || '-' }}</td>
              <td class="whitespace-nowrap tabular-nums">{{ formatTime(item.payment_date) }}</td>
              <td class="font-semibold">{{ item.student?.name || '-' }}</td>
              <td>{{ item.student?.class_room?.name || '-' }}</td>
              <td>{{ item.payment_method?.name || '-' }}</td>
              <td>{{ item.cashier?.name || '-' }}</td>
              <td class="num">{{ formatRupiah(item.total_amount) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-garis-kuat font-bold">
              <th scope="row" colspan="6" class="px-4 py-3 text-right">{{ isPaged ? 'Total halaman ini' : 'Total' }}</th>
              <td class="num px-4 py-3">{{ formatRupiah(totalAmount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <nav
      v-if="isPaged && !loading && !loadError"
      class="no-print mt-4 flex flex-wrap items-center justify-between gap-3"
      aria-label="Halaman laporan harian"
    >
      <p class="text-sm text-tinta-2">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</p>
      <div class="flex gap-2">
        <button type="button" class="btn btn-secondary min-h-[44px]" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">
          Sebelumnya
        </button>
        <button
          type="button"
          class="btn btn-secondary min-h-[44px]"
          :disabled="meta.current_page >= meta.last_page"
          @click="changePage(meta.current_page + 1)"
        >
          Berikutnya
        </button>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useSchoolStore } from '@/stores/school'
import { useNotice } from '@/composables/useNotice'
import { formatRupiah, formatAngka, formatDate, errorMessage } from '@/utils/format'
import DataState from '@/components/app/DataState.vue'

const schoolStore = useSchoolStore()
const notice = useNotice()

/** Local calendar date (YYYY-MM-DD), not UTC, so early-morning visits still open today. */
const localDate = (date = new Date()) => {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const today = localDate()
const filterDate = ref(today)
const dateError = ref('')
const items = ref([])
const meta = ref(null)
const loading = ref(true)
const loadError = ref('')
const exporting = ref(false)
const currentPage = ref(1)

const isPaged = computed(() => Boolean(meta.value && meta.value.last_page > 1))
const canExport = computed(() => !loading.value && !loadError.value && items.value.length > 0)

const totalAmount = computed(() => items.value.reduce((sum, item) => sum + (parseFloat(item.total_amount) || 0), 0))

const formatTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

let requestId = 0

const fetchData = async (page = 1) => {
  if (!filterDate.value) {
    dateError.value = 'Pilih tanggal laporan.'
    return
  }
  dateError.value = ''
  const thisRequest = ++requestId
  currentPage.value = page
  loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get('/api/v1/reports/harian', {
      params: {
        date: filterDate.value,
        page: page,
      },
    })
    if (thisRequest !== requestId) return
    items.value = res.data.data
    meta.value = res.data.meta
  } catch (error) {
    if (thisRequest !== requestId) return
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    if (thisRequest === requestId) loading.value = false
  }
}

const changePage = (page) => {
  fetchData(page)
}

const showToday = () => {
  filterDate.value = today
  fetchData(1)
}

const printPdf = () => {
  window.print()
}

/**
 * The API is guarded by a bearer token, so a plain link cannot download the CSV.
 * Fetch it with axios (which sends the token) and save the blob.
 */
const exportCsv = async () => {
  exporting.value = true
  try {
    const res = await axios.get('/api/v1/reports/harian', {
      params: { date: filterDate.value, export: 1 },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'text/csv' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `laporan-harian-${filterDate.value}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (error) {
    notice.error('CSV laporan harian gagal diunduh. Coba lagi.')
  } finally {
    exporting.value = false
  }
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
