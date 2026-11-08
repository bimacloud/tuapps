<template>
  <div class="report">
    <form class="no-print mb-4 space-y-3" @submit.prevent="fetchData">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="grid gap-4 sm:grid-cols-[repeat(2,minmax(0,12rem))_auto] sm:items-end">
          <div>
            <label for="income-from" class="field-label">Dari tanggal</label>
            <input id="income-from" v-model="dateFrom" type="date" required class="field-input" :max="dateTo || undefined" />
          </div>
          <div>
            <label for="income-to" class="field-label">Sampai tanggal</label>
            <input
              id="income-to"
              v-model="dateTo"
              type="date"
              required
              class="field-input"
              :min="dateFrom || undefined"
              :aria-invalid="rangeError ? 'true' : undefined"
              :aria-describedby="rangeError ? 'income-range-error' : undefined"
            />
          </div>
          <button type="submit" class="btn btn-primary">Tampilkan</button>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-secondary" :disabled="!canExport" @click="printReport">Cetak laporan</button>
          <button type="button" class="btn btn-secondary" :disabled="!canExport || exporting" @click="exportExcel">
            {{ exporting ? 'Menyiapkan Excel...' : 'Unduh Excel' }}
          </button>
        </div>
      </div>
      <p v-if="rangeError" id="income-range-error" class="field-error" role="alert">{{ rangeError }}</p>
      <div class="flex flex-wrap gap-2" aria-label="Pilihan cepat periode">
        <button v-for="preset in presets" :key="preset.label" type="button" class="btn btn-ghost btn-sm" @click="applyPreset(preset)">
          {{ preset.label }}
        </button>
      </div>
    </form>

    <div v-if="recap" class="mb-4 hidden print:block">
      <p v-if="schoolStore.name" class="font-semibold">{{ schoolStore.name }}</p>
      <h2 class="text-2xl font-bold">Rekap Pemasukan</h2>
      <p>{{ periodLabel }}</p>
    </div>

    <DataState v-if="loading" state="loading" message="Menghitung pemasukan." class="panel" />
    <DataState v-else-if="loadError" state="error" title="Rekap pemasukan gagal dimuat." :message="loadError" class="panel">
      <button type="button" class="btn btn-secondary" @click="fetchData">Coba lagi</button>
    </DataState>

    <div v-else-if="recap" class="space-y-6">
      <section class="panel px-5 py-4" aria-labelledby="income-summary-heading">
        <h2 id="income-summary-heading" class="text-xl font-bold">{{ periodLabel }}</h2>
        <dl class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div>
            <dt class="text-sm text-tinta-2">Total pemasukan</dt>
            <dd class="font-display text-3xl font-bold tabular-nums">{{ formatRupiah(recap.summary.total) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Tunai</dt>
            <dd class="font-display text-2xl font-bold tabular-nums">{{ formatRupiah(recap.summary.cash) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Non-tunai (transfer, online)</dt>
            <dd class="font-display text-2xl font-bold tabular-nums">{{ formatRupiah(recap.summary.non_cash) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Transaksi</dt>
            <dd class="font-display text-2xl font-bold tabular-nums">
              {{ formatAngka(recap.summary.transactions) }}
              <span class="font-sans text-sm font-normal text-tinta-2">dari {{ formatAngka(recap.summary.students) }} siswa</span>
            </dd>
          </div>
        </dl>
        <p v-if="!recap.summary.transactions" class="mt-3 text-tinta-2">Tidak ada pembayaran berhasil pada periode ini.</p>
      </section>

      <template v-if="recap.summary.transactions">
        <div class="grid gap-6 xl:grid-cols-2">
          <section v-for="block in breakdowns" :key="block.key" class="panel overflow-hidden" :aria-labelledby="`income-${block.key}`">
            <h2 :id="`income-${block.key}`" class="border-b border-garis px-5 py-3 text-lg font-bold">{{ block.title }}</h2>
            <div class="overflow-x-auto">
              <table class="table-data">
                <thead>
                  <tr>
                    <th scope="col">{{ block.column }}</th>
                    <th scope="col" class="num">Transaksi</th>
                    <th scope="col" class="num">Total</th>
                    <th scope="col" class="num">Porsi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in recap[block.key]" :key="row.name">
                    <td class="font-semibold">{{ row.name }}</td>
                    <td class="num">{{ formatAngka(row.transactions) }}</td>
                    <td class="num">{{ formatRupiah(row.total) }}</td>
                    <td class="num text-tinta-2">{{ share(row.total) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="block.note" class="border-t border-garis px-5 py-2 text-sm text-tinta-2">{{ block.note }}</p>
          </section>
        </div>

        <section class="panel overflow-hidden" aria-labelledby="income-days">
          <h2 id="income-days" class="border-b border-garis px-5 py-3 text-lg font-bold">Per hari</h2>
          <div class="overflow-x-auto">
            <table class="table-data">
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col" class="num">Transaksi</th>
                  <th scope="col" class="num">Tunai</th>
                  <th scope="col" class="num">Non-tunai</th>
                  <th scope="col" class="num">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="day in recap.per_day" :key="day.date">
                  <td class="whitespace-nowrap">{{ formatDayName(day.date) }}</td>
                  <td class="num">{{ formatAngka(day.transactions) }}</td>
                  <td class="num">{{ formatRupiah(day.cash) }}</td>
                  <td class="num">{{ formatRupiah(day.non_cash) }}</td>
                  <td class="num font-semibold">{{ formatRupiah(day.total) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-garis-kuat font-bold">
                  <th scope="row" class="px-4 py-3 text-left">Jumlah</th>
                  <td class="num px-4 py-3">{{ formatAngka(recap.summary.transactions) }}</td>
                  <td class="num px-4 py-3">{{ formatRupiah(recap.summary.cash) }}</td>
                  <td class="num px-4 py-3">{{ formatRupiah(recap.summary.non_cash) }}</td>
                  <td class="num px-4 py-3">{{ formatRupiah(recap.summary.total) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>
      </template>

      <section class="panel overflow-hidden" aria-labelledby="income-cash">
        <div class="border-b border-garis px-5 py-3">
          <h2 id="income-cash" class="text-lg font-bold">Cocok kas</h2>
          <p class="text-sm text-tinta-2">Uang tunai yang tercatat dibandingkan dengan uang fisik yang dihitung saat tutup kas, untuk kas yang dibuka pada periode ini.</p>
        </div>
        <dl class="grid gap-4 px-5 py-4 sm:grid-cols-2 xl:grid-cols-4">
          <div>
            <dt class="text-sm text-tinta-2">Kas ditutup</dt>
            <dd class="text-xl font-bold tabular-nums">{{ recap.cash_check.sessions }}</dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Seharusnya di laci</dt>
            <dd class="text-xl font-bold tabular-nums">{{ formatRupiah(recap.cash_check.expected) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Uang fisik</dt>
            <dd class="text-xl font-bold tabular-nums">{{ formatRupiah(recap.cash_check.actual) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Selisih</dt>
            <dd class="text-xl font-bold tabular-nums" :class="differenceClass(recap.cash_check.difference)">
              {{ differenceText(recap.cash_check.difference) }}
            </dd>
          </div>
        </dl>
        <p v-if="recap.cash_check.open_sessions" class="mx-5 mb-4 rounded-[var(--radius-kontrol)] bg-amber-muda px-3 py-2 text-sm font-semibold">
          {{ recap.cash_check.open_sessions }} kas belum ditutup, jadi uang fisiknya belum dihitung.
        </p>
        <div v-if="recap.cash_check.mismatches.length" class="overflow-x-auto border-t border-garis">
          <table class="table-data">
            <caption class="px-5 py-2 text-left font-semibold">Kas yang tidak cocok</caption>
            <thead>
              <tr>
                <th scope="col">Dibuka</th>
                <th scope="col">Kasir</th>
                <th scope="col" class="num">Seharusnya</th>
                <th scope="col" class="num">Uang fisik</th>
                <th scope="col" class="num">Selisih</th>
                <th scope="col">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="session in recap.cash_check.mismatches" :key="session.opened_at">
                <td class="whitespace-nowrap">{{ formatDateTime(session.opened_at) }}</td>
                <td>{{ session.cashier || '-' }}</td>
                <td class="num">{{ formatRupiah(session.expected) }}</td>
                <td class="num">{{ formatRupiah(session.actual) }}</td>
                <td class="num font-semibold" :class="differenceClass(session.difference)">{{ differenceText(session.difference) }}</td>
                <td class="min-w-[12rem]">{{ session.notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else-if="recap.cash_check.sessions" class="border-t border-garis px-5 py-3 text-sm font-semibold text-lunas">
          Semua kas yang ditutup pada periode ini cocok.
        </p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useSchoolStore } from '@/stores/school'
import { useNotice } from '@/composables/useNotice'
import { errorMessage, formatAngka, formatDate, formatDateTime, formatRupiah } from '@/utils/format'
import { downloadFile } from '@/utils/download'
import DataState from '@/components/app/DataState.vue'

const schoolStore = useSchoolStore()
const notice = useNotice()

/** Local calendar date (YYYY-MM-DD), not UTC. */
const localDate = (date) => {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const now = new Date()
const today = localDate(now)
const dateFrom = ref(localDate(new Date(now.getFullYear(), now.getMonth(), 1)))
const dateTo = ref(today)
const recap = ref(null)
const loading = ref(true)
const loadError = ref('')
const rangeError = ref('')
const exporting = ref(false)

const presets = computed(() => {
  const list = [
    { label: 'Hari ini', from: today, to: today },
    { label: 'Bulan ini', from: localDate(new Date(now.getFullYear(), now.getMonth(), 1)), to: today },
    {
      label: 'Bulan lalu',
      from: localDate(new Date(now.getFullYear(), now.getMonth() - 1, 1)),
      to: localDate(new Date(now.getFullYear(), now.getMonth(), 0)),
    },
  ]
  const year = schoolStore.activeYear
  if (year?.start_date) {
    const end = year.end_date && year.end_date < today ? year.end_date : today
    list.push({ label: `Tahun pelajaran ${year.name}`, from: year.start_date.slice(0, 10), to: end.slice(0, 10) })
  }
  return list
})

const breakdowns = [
  { key: 'per_type', title: 'Per jenis pembayaran', column: 'Jenis' },
  { key: 'per_method', title: 'Per metode', column: 'Metode' },
  { key: 'per_cashier', title: 'Per kasir', column: 'Kasir' },
  {
    key: 'per_class',
    title: 'Per kelas',
    column: 'Kelas',
    note: 'Kelas dihitung dari kelas siswa saat tagihannya dibuat. Satu transaksi bisa terhitung di dua kelas bila melunasi tagihan dari dua tahun.',
  },
]

const periodLabel = computed(() => {
  if (!recap.value) return ''
  const { from, to } = recap.value.period
  return from === to ? `Pemasukan ${formatDate(from)}` : `Pemasukan ${formatDate(from)} s.d. ${formatDate(to)}`
})

const canExport = computed(() => Boolean(recap.value) && !loading.value && !loadError.value)

const share = (total) => {
  const sum = recap.value?.summary.total || 0
  return sum > 0 ? `${Math.round((total / sum) * 100)}%` : '-'
}

const formatDayName = (date) =>
  new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${date}T00:00:00`))

const differenceText = (value) => {
  if (!value) return 'Cocok'
  return value > 0 ? `Lebih ${formatRupiah(value)}` : `Kurang ${formatRupiah(Math.abs(value))}`
}

const differenceClass = (value) => (!value ? 'text-lunas' : value > 0 ? 'text-tinta' : 'text-galat')

let requestId = 0

const fetchData = async () => {
  rangeError.value = ''
  if (!dateFrom.value || !dateTo.value) {
    rangeError.value = 'Isi kedua tanggal.'
    return
  }
  if (dateTo.value < dateFrom.value) {
    rangeError.value = 'Tanggal akhir tidak boleh sebelum tanggal awal.'
    return
  }
  const thisRequest = ++requestId
  loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get('/api/v1/reports/pemasukan', { params: { date_from: dateFrom.value, date_to: dateTo.value } })
    if (thisRequest !== requestId) return
    recap.value = res.data.data
  } catch (error) {
    if (thisRequest !== requestId) return
    if (error?.response?.status === 422) {
      rangeError.value = errorMessage(error)
      recap.value = null
    } else {
      loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
    }
  } finally {
    if (thisRequest === requestId) loading.value = false
  }
}

const applyPreset = (preset) => {
  dateFrom.value = preset.from
  dateTo.value = preset.to
  fetchData()
}

const printReport = () => window.print()

const exportExcel = async () => {
  exporting.value = true
  try {
    await downloadFile('/api/v1/reports/pemasukan', `rekap-pemasukan-${dateFrom.value}-${dateTo.value}.xlsx`, {
      date_from: dateFrom.value,
      date_to: dateTo.value,
      export: 1,
    })
  } catch {
    notice.error('Excel rekap pemasukan gagal diunduh. Coba lagi.')
  } finally {
    exporting.value = false
  }
}

onMounted(fetchData)
</script>

<style scoped>
@media print {
  .report,
  .report * {
    color: #000 !important;
  }
  .report .panel {
    border: 1px solid #000;
    border-radius: 0;
    break-inside: avoid;
  }
  .report .overflow-x-auto {
    overflow: visible;
  }
  .report table {
    font-size: 9pt;
  }
  .report th,
  .report td {
    background: #fff !important;
    padding: 3px 6px !important;
  }
}
</style>
