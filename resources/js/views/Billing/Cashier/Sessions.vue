<template>
  <admin-layout>
    <PageHeader
      title="Riwayat Kas"
      description="Setiap sesi kas yang dibuka dan ditutup: uang yang seharusnya ada di laci, uang fisik yang dihitung, dan selisihnya."
    >
      <template v-if="isBendahara" #actions>
        <router-link to="/kasir" class="btn btn-secondary">Buka kasir</router-link>
      </template>
    </PageHeader>

    <section
      class="panel mb-4 grid grid-cols-1 items-end gap-4 p-4 sm:grid-cols-[repeat(2,minmax(0,12rem))_auto_1fr]"
      aria-label="Saring riwayat kas"
    >
      <div>
        <label for="kas-dari" class="field-label">Dari tanggal</label>
        <input id="kas-dari" v-model="filters.date_from" type="date" class="field-input" @change="applyFilters" />
      </div>
      <div>
        <label for="kas-sampai" class="field-label">Sampai tanggal</label>
        <input id="kas-sampai" v-model="filters.date_to" type="date" class="field-input" @change="applyFilters" />
      </div>
      <label class="flex min-h-11 items-center gap-3">
        <input v-model="filters.only_difference" type="checkbox" class="field-check" @change="applyFilters" />
        <span class="font-medium">Hanya yang ada selisih</span>
      </label>
      <div class="sm:text-right">
        <button v-if="hasFilters" type="button" class="btn btn-ghost" @click="clearFilters">Hapus filter</button>
      </div>
    </section>

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Mengambil riwayat kas." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchSessions">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="sessions.length === 0 && hasFilters"
        state="empty"
        title="Tidak ada sesi kas yang cocok dengan filter."
        message="Ubah rentang tanggal atau hapus filter."
      >
        <button type="button" class="btn btn-secondary" @click="clearFilters">Hapus filter</button>
      </DataState>
      <DataState
        v-else-if="sessions.length === 0"
        state="empty"
        title="Belum ada sesi kas."
        message="Sesi kas tercatat saat bendahara menekan Buka kas di halaman Kasir."
      />

      <template v-else>
        <div class="overflow-x-auto">
          <table class="table-data">
            <thead>
              <tr>
                <th scope="col">Dibuka</th>
                <th scope="col">Ditutup</th>
                <th scope="col">Petugas</th>
                <th scope="col" class="num">Saldo awal</th>
                <th scope="col" class="num">Seharusnya di laci</th>
                <th scope="col" class="num">Uang fisik</th>
                <th scope="col">Selisih</th>
                <th scope="col">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="session in sessions" :key="session.id">
                <td class="whitespace-nowrap">{{ formatDateTime(session.start_time) }}</td>
                <td class="whitespace-nowrap">
                  <template v-if="session.status === 'CLOSED'">{{ formatDateTime(session.end_time) }}</template>
                  <StatusTag v-else status="OPEN" label="Masih dibuka" />
                </td>
                <td class="whitespace-nowrap">{{ session.user?.name || '-' }}</td>
                <td class="num">{{ formatRupiah(session.starting_balance) }}</td>
                <td class="num">{{ formatRupiah(expectedOf(session)) }}</td>
                <td class="num">{{ session.status === 'CLOSED' ? formatRupiah(session.actual_balance) : '-' }}</td>
                <td>
                  <span v-if="session.status !== 'CLOSED'" class="text-tinta-2">-</span>
                  <StatusTag v-else :status="differenceTag(session).status" :label="differenceTag(session).label" />
                </td>
                <td class="min-w-[14rem]">{{ session.notes || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="meta.last_page > 1"
          class="flex flex-wrap items-center justify-between gap-3 border-t border-garis px-4 py-3"
        >
          <p class="text-sm text-tinta-2">Halaman {{ meta.current_page }} dari {{ meta.last_page }}, {{ meta.total }} sesi</p>
          <div class="flex gap-2">
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              :disabled="meta.current_page <= 1"
              @click="goToPage(meta.current_page - 1)"
            >
              Sebelumnya
            </button>
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              :disabled="meta.current_page >= meta.last_page"
              @click="goToPage(meta.current_page + 1)"
            >
              Berikutnya
            </button>
          </div>
        </div>
      </template>
    </section>
  </admin-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, formatDateTime, formatRupiah } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'

type CashierSession = {
  id: number
  start_time: string
  end_time: string | null
  starting_balance: string | number
  expected_balance: string | number | null
  actual_balance: string | number | null
  difference: string | number | null
  notes: string | null
  status: 'OPEN' | 'CLOSED'
  cash_received: number
  user?: { id: number; name: string } | null
}

const authStore = useAuthStore()
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

const sessions = ref<CashierSession[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(true)
const loadError = ref('')
const page = ref(1)

const emptyFilters = () => ({ date_from: '', date_to: '', only_difference: false })
const filters = ref(emptyFilters())
const hasFilters = computed(() =>
  Boolean(filters.value.date_from || filters.value.date_to || filters.value.only_difference),
)

/** Closed sessions keep the figure computed at closing; open ones show what the drawer should hold so far. */
const expectedOf = (session: CashierSession) =>
  session.status === 'CLOSED'
    ? session.expected_balance
    : Number(session.starting_balance || 0) + Number(session.cash_received || 0)

const differenceTag = (session: CashierSession) => {
  const difference = Number(session.difference || 0)
  if (difference === 0) return { status: 'APPROVED', label: 'Cocok' }
  if (difference > 0) return { status: 'PENDING', label: `Lebih ${formatRupiah(difference)}` }
  return { status: 'REJECTED', label: `Kurang ${formatRupiah(Math.abs(difference))}` }
}

const fetchSessions = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const params: Record<string, string | number> = { page: page.value }
    if (filters.value.date_from) params.date_from = filters.value.date_from
    if (filters.value.date_to) params.date_to = filters.value.date_to
    if (filters.value.only_difference) params.only_difference = 1
    const response = await axios.get('/api/v1/cashier-sessions', { params })
    sessions.value = response.data.data
    meta.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    }
  } catch (error) {
    loadError.value = errorMessage(error, 'Riwayat kas gagal dimuat. Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  page.value = 1
  fetchSessions()
}

const clearFilters = () => {
  filters.value = emptyFilters()
  applyFilters()
}

const goToPage = (target: number) => {
  page.value = target
  fetchSessions()
}

onMounted(fetchSessions)
</script>
