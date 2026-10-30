<template>
  <div class="report">
    <div class="no-print mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="w-full sm:w-72">
        <label for="arrears-class" class="field-label">Kelas</label>
        <select id="arrears-class" v-model="classId" class="field-input" :disabled="classesLoading" @change="fetchData(1)">
          <option value="">Semua kelas</option>
          <option v-for="room in classes" :key="room.id" :value="String(room.id)">{{ room.name }}</option>
        </select>
        <p v-if="classesError" class="field-hint">
          Daftar kelas gagal dimuat, laporan menampilkan semua kelas.
          <button type="button" class="link min-h-[44px]" @click="fetchClasses">Muat ulang kelas</button>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="btn btn-secondary" :disabled="!canPrint" @click="printPdf">Cetak laporan</button>
      </div>
    </div>

    <div class="mb-4 hidden print:block">
      <p v-if="schoolStore.name" class="font-semibold">{{ schoolStore.name }}</p>
      <h2 class="text-2xl font-bold">Laporan Tunggakan Siswa</h2>
      <p>Kelas: {{ selectedClassName }}</p>
      <p>Dicetak: {{ formatDateTime(new Date()) }}</p>
      <p v-if="isPaged">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</p>
    </div>

    <section class="panel overflow-hidden" aria-labelledby="arrears-heading">
      <div class="no-print flex flex-wrap items-baseline justify-between gap-2 border-b border-garis px-5 py-4">
        <h2 id="arrears-heading" class="text-xl font-bold">Tagihan belum lunas, {{ selectedClassName.toLowerCase() }}</h2>
        <p v-if="!loading && !loadError && meta" class="text-sm text-tinta-2">
          <span class="font-semibold tabular-nums text-tinta">{{ formatAngka(meta.total) }}</span> tagihan, diurutkan dari sisa terbesar
        </p>
      </div>

      <DataState v-if="loading" state="loading" message="Mengambil tagihan yang belum lunas." />
      <DataState v-else-if="loadError" state="error" title="Laporan tunggakan gagal dimuat." :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData(currentPage)">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        title="Tidak ada siswa yang menunggak."
        :message="classId ? 'Semua tagihan di kelas ini sudah lunas. Pilih kelas lain atau tampilkan semua kelas.' : 'Semua tagihan yang tercatat sudah lunas.'"
      >
        <button v-if="classId" type="button" class="btn btn-secondary" @click="showAllClasses">Tampilkan semua kelas</button>
      </DataState>

      <div v-else class="overflow-x-auto">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col">NIS</th>
              <th scope="col">Siswa</th>
              <th scope="col">Kelas</th>
              <th scope="col" class="num">Total tagihan</th>
              <th scope="col" class="num">Dibayar</th>
              <th scope="col" class="num">Sisa tunggakan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="whitespace-nowrap tabular-nums">{{ item.student?.nis || '-' }}</td>
              <td>
                <router-link v-if="item.student?.id" :to="`/siswa/${item.student.id}/rekap`" class="link">
                  {{ item.student?.name }}
                </router-link>
                <span v-else>-</span>
              </td>
              <td>{{ item.student?.class_room?.name || '-' }}</td>
              <td class="num">{{ formatRupiah(item.total_amount) }}</td>
              <td class="num">{{ formatRupiah(item.paid_amount) }}</td>
              <td class="num font-bold text-belum">{{ formatRupiah(item.remaining_amount) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-garis-kuat font-bold">
              <th scope="row" colspan="3" class="px-4 py-3 text-right">{{ isPaged ? 'Jumlah halaman ini' : 'Jumlah' }}</th>
              <td class="num px-4 py-3">{{ formatRupiah(totals.tagihan) }}</td>
              <td class="num px-4 py-3">{{ formatRupiah(totals.dibayar) }}</td>
              <td class="num px-4 py-3 text-belum">{{ formatRupiah(totals.sisa) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <nav
      v-if="isPaged && !loading && !loadError"
      class="no-print mt-4 flex flex-wrap items-center justify-between gap-3"
      aria-label="Halaman laporan tunggakan"
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
import { formatRupiah, formatAngka, formatDateTime, errorMessage } from '@/utils/format'
import DataState from '@/components/app/DataState.vue'

const schoolStore = useSchoolStore()

const items = ref([])
const meta = ref(null)
const loading = ref(true)
const loadError = ref('')
const currentPage = ref(1)

const classId = ref('')
const classes = ref([])
const classesLoading = ref(false)
const classesError = ref(false)

const isPaged = computed(() => Boolean(meta.value && meta.value.last_page > 1))
const canPrint = computed(() => !loading.value && !loadError.value && items.value.length > 0)

const selectedClassName = computed(() => {
  if (!classId.value) return 'Semua kelas'
  return classes.value.find((room) => String(room.id) === classId.value)?.name ?? 'Kelas terpilih'
})

const totals = computed(() =>
  items.value.reduce(
    (acc, item) => {
      acc.tagihan += parseFloat(item.total_amount) || 0
      acc.dibayar += parseFloat(item.paid_amount) || 0
      acc.sisa += parseFloat(item.remaining_amount) || 0
      return acc
    },
    { tagihan: 0, dibayar: 0, sisa: 0 },
  ),
)

let requestId = 0

const fetchData = async (page = 1) => {
  const thisRequest = ++requestId
  currentPage.value = page
  loading.value = true
  loadError.value = ''
  try {
    const params = { page: page }
    if (classId.value) {
      params.class_id = classId.value
    }
    const res = await axios.get('/api/v1/reports/tunggakan', { params })
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

const fetchClasses = async () => {
  classesLoading.value = true
  classesError.value = false
  try {
    const res = await axios.get('/api/v1/classes')
    classes.value = res.data.data ?? []
  } catch {
    classesError.value = true
  } finally {
    classesLoading.value = false
  }
}

const changePage = (page) => {
  fetchData(page)
}

const showAllClasses = () => {
  classId.value = ''
  fetchData(1)
}

const printPdf = () => {
  window.print()
}

onMounted(() => {
  fetchData()
  fetchClasses()
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
  .report .link {
    text-decoration: none;
  }
}
</style>
