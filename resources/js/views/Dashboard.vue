<template>
  <admin-layout>
    <!-- Bendahara: what came in today, and what to do next -->
    <template v-if="isBendahara && !isKepsek">
      <PageHeader title="Beranda" :description="`Pemasukan kas untuk ${todayLabel}.`">
        <template #actions>
          <router-link to="/kasir" class="btn btn-primary">Buka kasir</router-link>
        </template>
      </PageHeader>
      <YearReminder class="mb-6" />

      <section v-if="bendaharaLoading || bendaharaError" class="panel">
        <DataState v-if="bendaharaLoading" state="loading" message="Mengambil pemasukan hari ini." />
        <DataState v-else state="error" title="Ringkasan kas gagal dimuat." :message="bendaharaError">
          <button type="button" class="btn btn-secondary" @click="fetchBendahara">Coba lagi</button>
        </DataState>
      </section>

      <template v-else>
        <section class="panel grid gap-8 p-5 sm:p-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]" aria-labelledby="today-heading">
          <div class="min-w-0">
            <h2 id="today-heading" class="font-sans text-base font-semibold text-tinta-2">Pemasukan hari ini</h2>
            <p class="mt-2 break-words font-display text-[2.75rem] font-bold leading-none tabular-nums text-tinta sm:text-[3.75rem]">
              {{ rupiahOrDash(metricsBendahara?.pemasukan_hari_ini) }}
            </p>
            <p class="mt-3 text-tinta-2">
              <span class="font-semibold text-tinta tabular-nums">{{ angkaOrDash(metricsBendahara?.transaksi_hari_ini) }}</span>
              pembayaran berhasil sejak pukul 00.00.
            </p>
            <p class="mt-6 flex flex-wrap gap-x-5 gap-y-2">
              <router-link to="/reports/harian" class="link inline-flex min-h-[44px] items-center">Lihat laporan harian</router-link>
            </p>
          </div>

          <dl class="min-w-0 divide-y divide-garis border-t border-garis pt-2 lg:border-t-0 lg:pt-0">
            <div class="py-4 lg:pt-0">
              <dt class="text-sm font-semibold text-tinta-2">Pemasukan {{ monthLabel }}</dt>
              <dd class="mt-1 font-display text-[1.75rem] font-bold leading-tight tabular-nums">
                {{ rupiahOrDash(metricsBendahara?.pemasukan_bulan_ini) }}
              </dd>
            </div>
            <div class="py-4">
              <dt class="text-sm font-semibold text-tinta-2">Tunggakan tahun pelajaran aktif</dt>
              <dd class="mt-1 font-display text-[1.75rem] font-bold leading-tight tabular-nums text-belum">
                {{ rupiahOrDash(metricsBendahara?.total_tunggakan) }}
              </dd>
              <dd class="mt-1 text-sm text-tinta-2">
                <span class="font-semibold text-tinta tabular-nums">{{ angkaOrDash(metricsBendahara?.siswa_menunggak) }}</span>
                siswa masih punya sisa tagihan.
                <router-link to="/reports/tunggakan" class="link inline-flex min-h-[44px] items-center">Lihat daftar tunggakan</router-link>
              </dd>
            </div>
          </dl>
        </section>

        <section class="panel mt-6 overflow-hidden" aria-labelledby="recent-heading">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-garis px-5 py-4">
            <h2 id="recent-heading" class="text-xl font-bold">Lima pembayaran terakhir</h2>
          </div>

          <DataState
            v-if="recentTransactions.length === 0"
            state="empty"
            compact
            title="Belum ada pembayaran tercatat."
            message="Pembayaran yang disimpan lewat kasir akan muncul di sini."
          >
            <router-link to="/kasir" class="btn btn-secondary">Buka kasir</router-link>
          </DataState>

          <div v-else class="overflow-x-auto">
            <table class="table-data">
              <thead>
                <tr>
                  <th scope="col">Waktu</th>
                  <th scope="col">Siswa</th>
                  <th scope="col">Metode</th>
                  <th scope="col" class="num">Nominal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="trx in recentTransactions" :key="trx.id">
                  <td class="whitespace-nowrap">{{ formatDateTime(trx.payment_date) }}</td>
                  <td>
                    <span class="font-semibold">{{ trx.student?.name || '-' }}</span>
                    <span class="block text-sm text-tinta-2">{{ trx.student?.class_room?.name || 'Kelas belum diatur' }}</span>
                  </td>
                  <td>{{ trx.payment_method?.name || '-' }}</td>
                  <td class="num font-semibold">{{ rupiahOrDash(trx.total_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </template>

    <!-- Kepala Sekolah: is collection on track, and is anything waiting -->
    <template v-else-if="isKepsek">
      <PageHeader title="Beranda" description="Penagihan tahun pelajaran aktif dan permintaan yang menunggu keputusan Anda.">
        <template #actions>
          <router-link to="/approvals" class="btn btn-primary">Lihat persetujuan</router-link>
        </template>
      </PageHeader>
      <YearReminder class="mb-6" />

      <section v-if="kepsekLoading || kepsekError" class="panel">
        <DataState v-if="kepsekLoading" state="loading" message="Mengambil ringkasan penagihan." />
        <DataState v-else state="error" title="Ringkasan penagihan gagal dimuat." :message="kepsekError">
          <button type="button" class="btn btn-secondary" @click="fetchKepsek">Coba lagi</button>
        </DataState>
      </section>

      <section v-else class="panel p-5 sm:p-8" aria-labelledby="collection-heading">
        <div class="grid gap-8 lg:grid-cols-2">
          <div class="min-w-0">
            <h2 id="collection-heading" class="font-sans text-base font-semibold text-tinta-2">Tagihan yang sudah terbayar</h2>
            <p class="mt-2 font-display text-[3.25rem] font-bold leading-none tabular-nums sm:text-[4rem]">
              {{ percentOrDash(metricsKepsek?.collection_rate) }}
            </p>
            <div v-if="collectionWidth !== null" class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-garis" aria-hidden="true">
              <div class="h-full rounded-full bg-bata" :style="{ width: `${collectionWidth}%` }"></div>
            </div>
            <p class="mt-3 text-tinta-2">
              <span class="font-semibold text-tinta tabular-nums">{{ rupiahOrDash(metricsKepsek?.total_pembayaran) }}</span>
              dari tagihan
              <span class="font-semibold text-tinta tabular-nums">{{ rupiahOrDash(metricsKepsek?.total_tagihan) }}</span>.
            </p>
          </div>

          <div class="min-w-0 border-t border-garis pt-6 lg:border-t-0 lg:pt-0">
            <h2 class="font-sans text-base font-semibold text-tinta-2">Total tunggakan</h2>
            <p class="mt-2 break-words font-display text-[2.5rem] font-bold leading-none tabular-nums text-belum sm:text-[3.25rem]">
              {{ rupiahOrDash(metricsKepsek?.total_tunggakan) }}
            </p>
            <p class="mt-3 text-tinta-2">
              Sisa tagihan tahun pelajaran aktif. Siswa aktif saat ini:
              <span class="font-semibold text-tinta tabular-nums">{{ angkaOrDash(metricsKepsek?.total_siswa) }}</span>.
              <router-link to="/reports/tunggakan" class="link inline-flex min-h-[44px] items-center">Lihat siswa yang menunggak</router-link>
            </p>
          </div>
        </div>

        <div class="mt-6 border-t border-garis pt-5">
          <p v-if="approvalsLoading" class="text-tinta-2" role="status">Memeriksa permintaan persetujuan...</p>
          <p v-else-if="approvalsError" class="text-tinta-2">
            Jumlah permintaan persetujuan tidak dapat dimuat.
            <button type="button" class="link min-h-[44px]" @click="fetchPendingApprovals">Coba lagi</button>
          </p>
          <p v-else-if="pendingApprovals === 0" class="text-tinta-2">Tidak ada permintaan yang menunggu persetujuan.</p>
          <p v-else class="font-semibold">
            <span class="tabular-nums">{{ pendingApprovals }}</span> permintaan menunggu persetujuan Anda.
          </p>
        </div>
      </section>

      <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="panel min-w-0 p-5 lg:col-span-2" aria-labelledby="income-chart-heading">
          <h2 id="income-chart-heading" class="text-xl font-bold">Pemasukan per bulan, 6 bulan terakhir</h2>
          <p class="text-sm text-tinta-2">Jumlah pembayaran berhasil, dalam rupiah.</p>
          <DataState v-if="chartsLoading" state="loading" compact message="Menyiapkan grafik pemasukan." />
          <DataState v-else-if="chartsError" state="error" compact title="Grafik pemasukan gagal dimuat." :message="chartsError">
            <button type="button" class="btn btn-secondary" @click="fetchCharts">Coba lagi</button>
          </DataState>
          <DataState
            v-else-if="incomeMonths.length === 0"
            state="empty"
            compact
            title="Belum ada pemasukan dalam 6 bulan terakhir."
            message="Grafik terisi setelah ada pembayaran yang berhasil dicatat di kasir."
          />
          <div v-else role="img" :aria-label="incomeAriaLabel" class="mt-2">
            <apexchart type="bar" height="300" :options="incomeOptions" :series="incomeSeries"></apexchart>
          </div>
        </section>

        <section class="panel min-w-0 p-5" aria-labelledby="status-chart-heading">
          <h2 id="status-chart-heading" class="text-xl font-bold">Status tagihan tahun ini</h2>
          <p class="text-sm text-tinta-2">Jumlah tagihan per status, tahun pelajaran aktif.</p>
          <DataState v-if="chartsLoading" state="loading" compact message="Menyiapkan grafik status." />
          <DataState v-else-if="chartsError" state="error" compact title="Grafik status gagal dimuat." :message="chartsError">
            <button type="button" class="btn btn-secondary" @click="fetchCharts">Coba lagi</button>
          </DataState>
          <DataState
            v-else-if="statusSlices.length === 0"
            state="empty"
            compact
            title="Belum ada tagihan pada tahun pelajaran aktif."
            message="Grafik terisi setelah bendahara membuat tagihan untuk tahun pelajaran yang aktif."
          />
          <template v-else>
            <div role="img" :aria-label="statusAriaLabel" class="mt-2">
              <apexchart type="donut" height="220" :options="statusOptions" :series="statusSeries"></apexchart>
            </div>
            <ul class="mt-3 space-y-1.5 text-sm">
              <li v-for="slice in statusSlices" :key="slice.key" class="flex items-center justify-between gap-3">
                <span class="flex items-center gap-2">
                  <span class="inline-block size-3 rounded-[2px]" :style="{ backgroundColor: slice.color }" aria-hidden="true"></span>
                  {{ slice.label }}
                </span>
                <span class="font-semibold tabular-nums">{{ formatAngka(slice.total) }} tagihan</span>
              </li>
            </ul>
          </template>
        </section>

        <section class="panel min-w-0 p-5 lg:col-span-3" aria-labelledby="arrears-chart-heading">
          <h2 id="arrears-chart-heading" class="text-xl font-bold">Lima kelas dengan tunggakan terbesar</h2>
          <p class="text-sm text-tinta-2">Sisa tagihan tahun pelajaran aktif, dalam rupiah.</p>
          <DataState v-if="chartsLoading" state="loading" compact message="Menyiapkan grafik tunggakan." />
          <DataState v-else-if="chartsError" state="error" compact title="Grafik tunggakan gagal dimuat." :message="chartsError">
            <button type="button" class="btn btn-secondary" @click="fetchCharts">Coba lagi</button>
          </DataState>
          <DataState
            v-else-if="arrearsByClass.length === 0"
            state="empty"
            compact
            title="Tidak ada kelas dengan tunggakan."
            message="Semua tagihan tahun pelajaran aktif sudah lunas, atau tagihan belum dibuat."
          />
          <div v-else role="img" :aria-label="arrearsAriaLabel" class="mt-2">
            <apexchart type="bar" height="260" :options="arrearsOptions" :series="arrearsSeries"></apexchart>
          </div>
        </section>
      </div>
    </template>

    <template v-else>
      <PageHeader title="Beranda" />
      <section class="panel">
        <DataState
          state="empty"
          title="Ringkasan belum tersedia untuk akun ini."
          message="Beranda menampilkan ringkasan untuk peran Kepala Sekolah dan Bendahara. Hubungi admin sekolah bila peran Anda belum diatur."
        />
      </section>
    </template>
  </admin-layout>
</template>

<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from 'vue'
import axios from 'axios'

/** Charts only appear for Kepala Sekolah, so the charting library is fetched only when one renders. */
const apexchart = defineAsyncComponent(() => import('vue3-apexcharts'))
import { useAuthStore } from '@/stores/auth'
import { formatRupiah, formatAngka, formatDateTime, errorMessage } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import YearReminder from '@/components/app/YearReminder.vue'
import DataState from '@/components/app/DataState.vue'

const authStore = useAuthStore()
const isKepsek = computed(() => authStore.hasRole('Kepala Sekolah'))
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

/** Palette from DESIGN.md, used where ApexCharts needs literal colors. */
const color = {
  bata: '#D4621A',
  amber: '#F4AE52',
  tinta2: '#6B5444',
  garis: '#E8D9C3',
  lunas: '#1E6B3A',
  belum: '#8A3B0C',
}

const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

const todayLabel = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date())
const monthLabel = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(new Date())

const hasValue = (value) => value !== null && value !== undefined && value !== ''
const rupiahOrDash = (value) => (hasValue(value) ? formatRupiah(value) : '-')
const angkaOrDash = (value) => (hasValue(value) ? formatAngka(value) : '-')
const percentOrDash = (value) =>
  hasValue(value) ? `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%` : '-'

/** Short axis label: 1,5 jt / 750 rb. */
const singkat = (value) => {
  const number = Number(value) || 0
  if (Math.abs(number) >= 1_000_000) {
    return `${(number / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} jt`
  }
  if (Math.abs(number) >= 1_000) {
    return `${(number / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 0 })} rb`
  }
  return formatAngka(number)
}

/* Bendahara */
const metricsBendahara = ref(null)
const bendaharaLoading = ref(true)
const bendaharaError = ref('')
const recentTransactions = computed(() => metricsBendahara.value?.recent_transactions ?? [])

const fetchBendahara = async () => {
  bendaharaLoading.value = true
  bendaharaError.value = ''
  try {
    const res = await axios.get('/api/v1/dashboard/metrics-bendahara')
    metricsBendahara.value = res.data?.data ?? null
  } catch (error) {
    bendaharaError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    bendaharaLoading.value = false
  }
}

/* Kepala Sekolah */
const metricsKepsek = ref(null)
const kepsekLoading = ref(true)
const kepsekError = ref('')

const collectionWidth = computed(() => {
  const rate = metricsKepsek.value?.collection_rate
  if (!hasValue(rate)) return null
  return Math.min(100, Math.max(0, Number(rate)))
})

const fetchKepsek = async () => {
  kepsekLoading.value = true
  kepsekError.value = ''
  try {
    const res = await axios.get('/api/v1/dashboard/metrics-kepsek')
    metricsKepsek.value = res.data?.data ?? null
  } catch (error) {
    kepsekError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    kepsekLoading.value = false
  }
}

const pendingApprovals = ref(null)
const approvalsLoading = ref(true)
const approvalsError = ref(false)

const fetchPendingApprovals = async () => {
  approvalsLoading.value = true
  approvalsError.value = false
  try {
    const res = await axios.get('/api/v1/approvals', { params: { status: 'PENDING' } })
    pendingApprovals.value = Array.isArray(res.data?.data) ? res.data.data.length : null
    approvalsError.value = pendingApprovals.value === null
  } catch {
    approvalsError.value = true
  } finally {
    approvalsLoading.value = false
  }
}

/* Charts */
const charts = ref(null)
const chartsLoading = ref(true)
const chartsError = ref('')

const fetchCharts = async () => {
  chartsLoading.value = true
  chartsError.value = ''
  try {
    const res = await axios.get('/api/v1/dashboard/charts')
    charts.value = res.data?.data ?? null
  } catch (error) {
    chartsError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    chartsLoading.value = false
  }
}

/**
 * The API only returns months that had successful payments. Months inside the 6-month window
 * with no payment are shown as 0, which is what the query means.
 */
const incomeMonths = computed(() => {
  const rows = charts.value?.monthly_income ?? []
  if (rows.length === 0) return []

  const byKey = new Map(rows.map((row) => [`${row.year}-${row.month}`, Number(row.total) || 0]))
  const now = new Date()
  const months = []
  for (let offset = 5; offset >= 0; offset--) {
    const date = new Date(now.getFullYear(), now.getMonth() - offset, 1)
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    months.push({ label: `${bulan[month - 1]} ${year}`, total: byKey.get(`${year}-${month}`) ?? 0 })
  }
  return months
})

const incomeSeries = computed(() => [{ name: 'Pemasukan', data: incomeMonths.value.map((m) => m.total) }])
const incomeAriaLabel = computed(
  () => `Grafik batang pemasukan per bulan: ${incomeMonths.value.map((m) => `${m.label} ${formatRupiah(m.total)}`).join(', ')}.`,
)

const baseChart = {
  toolbar: { show: false },
  zoom: { enabled: false },
  animations: { enabled: false },
  fontFamily: 'Barlow, ui-sans-serif, sans-serif',
}

const incomeOptions = computed(() => ({
  chart: { ...baseChart, type: 'bar' },
  colors: [color.bata],
  plotOptions: { bar: { borderRadius: 3, columnWidth: '50%' } },
  dataLabels: { enabled: false },
  grid: { borderColor: color.garis },
  xaxis: {
    categories: incomeMonths.value.map((m) => m.label),
    axisBorder: { color: color.garis },
    axisTicks: { show: false },
  },
  yaxis: { labels: { formatter: singkat } },
  tooltip: { y: { formatter: (value) => formatRupiah(value) } },
  states: { hover: { filter: { type: 'darken', value: 0.9 } } },
}))

const statusMeta = {
  LUNAS: { label: 'Lunas', color: color.lunas, order: 1 },
  SEBAGIAN: { label: 'Dibayar sebagian', color: color.amber, order: 2 },
  'BELUM BAYAR': { label: 'Belum bayar', color: color.belum, order: 3 },
}

const statusSlices = computed(() =>
  (charts.value?.payment_status ?? [])
    .map((row) => {
      const key = String(row.status ?? '').toUpperCase()
      const meta = statusMeta[key] ?? { label: row.status || 'Tanpa status', color: color.tinta2, order: 9 }
      return { key, label: meta.label, color: meta.color, order: meta.order, total: Number(row.total) || 0 }
    })
    .sort((a, b) => a.order - b.order),
)

const statusSeries = computed(() => statusSlices.value.map((slice) => slice.total))
const statusAriaLabel = computed(
  () => `Grafik lingkaran status tagihan: ${statusSlices.value.map((s) => `${s.label} ${formatAngka(s.total)}`).join(', ')}.`,
)

const statusOptions = computed(() => ({
  chart: { ...baseChart, type: 'donut' },
  labels: statusSlices.value.map((slice) => slice.label),
  colors: statusSlices.value.map((slice) => slice.color),
  legend: { show: false },
  dataLabels: { enabled: false },
  stroke: { colors: ['#FFFFFF'], width: 2 },
  plotOptions: { pie: { donut: { size: '62%' } } },
  tooltip: { y: { formatter: (value) => `${formatAngka(value)} tagihan` } },
}))

const arrearsByClass = computed(() =>
  (charts.value?.arrears_by_class ?? []).map((row) => ({ name: row.name, total: Number(row.total) || 0 })),
)

const arrearsSeries = computed(() => [{ name: 'Tunggakan', data: arrearsByClass.value.map((row) => row.total) }])
const arrearsAriaLabel = computed(
  () => `Grafik batang tunggakan per kelas: ${arrearsByClass.value.map((r) => `${r.name} ${formatRupiah(r.total)}`).join(', ')}.`,
)

const arrearsOptions = computed(() => ({
  chart: { ...baseChart, type: 'bar' },
  colors: [color.tinta2],
  plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '55%' } },
  dataLabels: { enabled: false },
  grid: { borderColor: color.garis },
  xaxis: {
    categories: arrearsByClass.value.map((row) => row.name),
    labels: { formatter: singkat },
    axisBorder: { color: color.garis },
    axisTicks: { show: false },
  },
  tooltip: { y: { formatter: (value) => formatRupiah(value) } },
  states: { hover: { filter: { type: 'darken', value: 0.9 } } },
}))

onMounted(() => {
  if (isKepsek.value) {
    fetchKepsek()
    fetchCharts()
    fetchPendingApprovals()
  } else if (isBendahara.value) {
    fetchBendahara()
  }
})
</script>
