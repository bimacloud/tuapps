<template>
  <div class="report">
    <form class="no-print mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-[repeat(2,minmax(0,16rem))_1fr] lg:items-end" @submit.prevent="fetchData">
      <div>
        <label for="recap-year" class="field-label">Tahun pelajaran</label>
        <select id="recap-year" v-model="yearId" class="field-input" :disabled="optionsLoading" @change="onYearChange">
          <option v-for="year in years" :key="year.id" :value="year.id">{{ year.name }}{{ year.is_active ? ' (aktif)' : '' }}</option>
        </select>
      </div>
      <div>
        <label for="recap-class" class="field-label">Kelas</label>
        <select id="recap-class" v-model="classId" class="field-input" :disabled="optionsLoading || !classesOfYear.length" @change="fetchData">
          <option v-if="!classesOfYear.length" value="">Belum ada kelas di tahun ini</option>
          <option v-for="item in classesOfYear" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>
      </div>
      <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1 lg:justify-end">
        <button type="button" class="btn btn-secondary" :disabled="!canExport" @click="printLandscape">Cetak laporan</button>
        <button type="button" class="btn btn-secondary" :disabled="!canExport || exporting" @click="exportExcel">
          {{ exporting ? 'Menyiapkan Excel...' : 'Unduh Excel' }}
        </button>
      </div>
    </form>

    <div v-if="recap" class="mb-4 hidden print:block">
      <p v-if="schoolStore.name" class="font-semibold">{{ schoolStore.name }}</p>
      <h2 class="text-2xl font-bold">Rekap Pembayaran Kelas {{ recap.class.name }}</h2>
      <p>Tahun pelajaran {{ recap.academic_year.name }}. Angka di sel adalah sisa yang belum dibayar.</p>
    </div>

    <section class="panel overflow-hidden" aria-labelledby="recap-heading">
      <div class="no-print flex flex-wrap items-center justify-between gap-3 border-b border-garis px-5 py-4">
        <div class="min-w-0 flex-1 basis-[20rem]">
          <h2 id="recap-heading" class="text-xl font-bold">
            {{ recap ? `Kelas ${recap.class.name}, ${recap.academic_year.name}` : 'Rekap kelas' }}
          </h2>
          <p v-if="recap" class="text-sm text-tinta-2">
            {{ recap.total.students }} siswa,
            <span class="font-semibold text-tinta">{{ recap.total.students_with_arrears }} masih menunggak</span>,
            total tunggakan <span class="font-semibold tabular-nums text-belum">{{ formatRupiah(recap.total.remaining) }}</span>
            dari tagihan {{ formatRupiah(recap.total.billed) }}.
          </p>
        </div>
        <label v-if="recap" class="field-check shrink-0 whitespace-nowrap">
          <input v-model="onlyArrears" type="checkbox" />
          <span>Hanya siswa yang menunggak</span>
        </label>
      </div>

      <DataState v-if="optionsLoading || loading" state="loading" message="Menyusun rekap kelas." />
      <DataState v-else-if="loadError" state="error" title="Rekap kelas gagal dimuat." :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="!classId"
        state="empty"
        title="Pilih kelas."
        message="Kelas dibuat per tahun pelajaran. Bila daftarnya kosong, tambahkan kelas untuk tahun ini di menu Kelas."
      />
      <DataState
        v-else-if="recap && !visibleStudents.length"
        state="empty"
        :title="onlyArrears ? 'Tidak ada siswa yang menunggak.' : 'Belum ada siswa atau tagihan di kelas ini.'"
        :message="onlyArrears ? 'Semua tagihan di kelas ini sudah lunas.' : 'Tagihan muncul di sini setelah dibuat di menu Tagihan.'"
      />

      <template v-else-if="recap">
        <ul class="no-print flex flex-wrap gap-x-4 gap-y-1 border-b border-garis px-5 py-3 text-sm text-tinta-2" aria-label="Arti isi sel">
          <li><span class="rounded-[4px] bg-lunas-muda px-1.5 font-semibold text-lunas">Lunas</span> sudah dibayar penuh</li>
          <li><span class="rounded-[4px] bg-amber-muda px-1.5 font-semibold text-tinta">sisa 50.000</span> dicicil, sisanya segini</li>
          <li><span class="rounded-[4px] bg-bata-muda px-1.5 font-semibold text-belum">150.000</span> belum dibayar sama sekali</li>
          <li><span class="font-semibold">–</span> tidak ditagih</li>
        </ul>
        <div class="overflow-x-auto">
          <table class="recap-table w-full border-collapse text-sm">
            <thead>
              <tr class="bg-krem">
                <th scope="col" rowspan="2" class="sticky-col left-0 min-w-[13rem] text-left">Siswa</th>
                <th scope="colgroup" :colspan="monthlyColumns.length" class="text-center">SPP bulanan</th>
                <th v-if="otherColumns.length" scope="colgroup" :colspan="otherColumns.length" class="text-center">Semester dan tahunan</th>
                <th scope="col" rowspan="2" class="text-right">Tunggakan</th>
              </tr>
              <tr class="bg-krem">
                <th v-for="column in recap.columns" :key="column.key" scope="col" class="text-center" :title="column.label">
                  {{ column.group === 'bulanan' ? column.label.slice(0, 3) : column.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(student, index) in visibleStudents" :key="student.id">
                <th scope="row" class="sticky-col left-0 text-left font-semibold">
                  <span class="mr-1 font-normal tabular-nums text-tinta-2">{{ index + 1 }}.</span>
                  <router-link :to="`/siswa/${student.id}/rekap`" class="link">{{ student.name }}</router-link>
                  <span class="block text-xs font-normal text-tinta-2 tabular-nums">
                    {{ student.nis || 'NIS -' }}<template v-if="student.status !== 'AKTIF'"> · {{ student.status.toLowerCase() }}</template>
                  </span>
                </th>
                <td
                  v-for="column in recap.columns"
                  :key="column.key"
                  class="text-center tabular-nums whitespace-nowrap"
                  :class="cellClass(student.cells[column.key])"
                  :title="cellTitle(student.cells[column.key], column.label)"
                >
                  {{ cellText(student.cells[column.key]) }}
                </td>
                <td class="text-right font-bold tabular-nums" :class="student.total.remaining > 0 ? 'text-belum' : 'text-lunas'">
                  {{ student.total.remaining > 0 ? formatAngka(student.total.remaining) : 'Lunas' }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-garis-kuat">
                <th scope="row" class="sticky-col left-0 text-right">Siswa belum lunas</th>
                <td v-for="column in recap.columns" :key="column.key" class="text-center tabular-nums">
                  {{ recap.column_totals[column.key]?.unpaid || '–' }}
                </td>
                <td class="text-right tabular-nums">{{ recap.total.students_with_arrears }}</td>
              </tr>
              <tr class="font-bold">
                <th scope="row" class="sticky-col left-0 text-right">Total tunggakan</th>
                <td v-for="column in recap.columns" :key="column.key" class="text-center tabular-nums text-belum">
                  {{ recap.column_totals[column.key]?.remaining ? formatAngka(recap.column_totals[column.key].remaining) : '–' }}
                </td>
                <td class="text-right tabular-nums text-belum">{{ formatAngka(recap.total.remaining) }}</td>
              </tr>
              <tr>
                <th scope="row" class="sticky-col left-0 text-right">Sudah dibayar</th>
                <td v-for="column in recap.columns" :key="column.key" class="text-center tabular-nums">
                  {{ recap.column_totals[column.key]?.paid ? formatAngka(recap.column_totals[column.key].paid) : '–' }}
                </td>
                <td class="text-right tabular-nums">{{ formatAngka(recap.total.paid) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useSchoolStore } from '@/stores/school'
import { useNotice } from '@/composables/useNotice'
import { errorMessage, formatAngka, formatRupiah } from '@/utils/format'
import { downloadFile } from '@/utils/download'
import DataState from '@/components/app/DataState.vue'

const schoolStore = useSchoolStore()
const notice = useNotice()

const years = ref([])
const classes = ref([])
const yearId = ref('')
const classId = ref('')
const recap = ref(null)
const optionsLoading = ref(true)
const loading = ref(false)
const loadError = ref('')
const exporting = ref(false)
const onlyArrears = ref(false)

const classesOfYear = computed(() => classes.value.filter((item) => item.academic_year_id === yearId.value))
const monthlyColumns = computed(() => (recap.value?.columns ?? []).filter((column) => column.group === 'bulanan'))
const otherColumns = computed(() => (recap.value?.columns ?? []).filter((column) => column.group !== 'bulanan'))
const visibleStudents = computed(() =>
  (recap.value?.students ?? []).filter((student) => !onlyArrears.value || student.total.remaining > 0),
)
const canExport = computed(() => Boolean(recap.value) && !loading.value && !loadError.value)

const cellText = (cell) => {
  if (!cell) return '–'
  if (cell.status === 'LUNAS') return 'Lunas'
  if (cell.status === 'SEBAGIAN') return `sisa ${formatAngka(cell.remaining)}`
  return formatAngka(cell.remaining)
}

const cellClass = (cell) => {
  if (!cell) return 'text-tinta-2'
  if (cell.status === 'LUNAS') return 'bg-lunas-muda font-semibold text-lunas'
  if (cell.status === 'SEBAGIAN') return 'bg-amber-muda font-semibold text-tinta'
  return 'bg-bata-muda font-semibold text-belum'
}

const cellTitle = (cell, label) => {
  if (!cell) return `${label}: tidak ditagih`
  return `${label}: tagihan ${formatRupiah(cell.billed)}, dibayar ${formatRupiah(cell.paid)}, sisa ${formatRupiah(cell.remaining)}`
}

let requestId = 0

const fetchData = async () => {
  if (!yearId.value || !classId.value) {
    recap.value = null
    return
  }
  const thisRequest = ++requestId
  loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get('/api/v1/reports/rekap-kelas', {
      params: { academic_year_id: yearId.value, class_id: classId.value },
    })
    if (thisRequest !== requestId) return
    recap.value = res.data.data
  } catch (error) {
    if (thisRequest !== requestId) return
    recap.value = null
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    if (thisRequest === requestId) loading.value = false
  }
}

const onYearChange = () => {
  classId.value = classesOfYear.value[0]?.id ?? ''
  fetchData()
}

const fetchOptions = async () => {
  optionsLoading.value = true
  loadError.value = ''
  try {
    const [yearRes, classRes] = await Promise.all([axios.get('/api/v1/academic-years'), axios.get('/api/v1/classes')])
    years.value = yearRes.data.data ?? []
    classes.value = classRes.data.data ?? []
    yearId.value = (years.value.find((year) => year.is_active) ?? years.value[0])?.id ?? ''
    classId.value = classesOfYear.value[0]?.id ?? ''
  } catch (error) {
    loadError.value = errorMessage(error, 'Daftar tahun pelajaran dan kelas gagal dimuat.')
  } finally {
    optionsLoading.value = false
  }
  fetchData()
}

/** The class table is wide, so print it landscape without changing other pages' print setup. */
const printLandscape = () => {
  const style = document.createElement('style')
  style.textContent = '@page { size: A4 landscape; margin: 10mm; }'
  document.head.appendChild(style)
  window.addEventListener('afterprint', () => style.remove(), { once: true })
  window.print()
}

const exportExcel = async () => {
  exporting.value = true
  try {
    await downloadFile('/api/v1/reports/rekap-kelas', `rekap-kelas-${recap.value.class.name}-${recap.value.academic_year.name}.xlsx`.replace(/[\\/\s]+/g, '-'), {
      academic_year_id: yearId.value,
      class_id: classId.value,
      export: 1,
    })
  } catch {
    notice.error('Excel rekap kelas gagal diunduh. Coba lagi.')
  } finally {
    exporting.value = false
  }
}

onMounted(fetchOptions)
</script>

<style scoped>
.recap-table th,
.recap-table td {
  border: 1px solid var(--color-garis);
  padding: 6px 8px;
}
.recap-table thead th {
  font-weight: 600;
}
.sticky-col {
  position: sticky;
  z-index: 1;
  background: var(--color-kertas);
}
thead .sticky-col {
  background: var(--color-krem);
}
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
  .recap-table {
    font-size: 8pt;
  }
  .recap-table th,
  .recap-table td {
    background: #fff !important;
    border: 1px solid #000 !important;
    padding: 2px 3px !important;
  }
  .sticky-col {
    position: static;
  }
}
</style>
