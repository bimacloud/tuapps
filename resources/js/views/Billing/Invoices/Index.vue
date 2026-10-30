<template>
  <admin-layout>
    <PageHeader title="Daftar Tagihan" description="Tagihan siswa per jenis pembayaran dan periode, beserta sisa yang belum dibayar.">
      <template v-if="isBendahara" #actions>
        <router-link to="/billing/invoices/generate" class="btn btn-primary">Buat tagihan</router-link>
      </template>
    </PageHeader>

    <section class="panel mb-4 p-4" aria-label="Saring tagihan">
      <form class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))]" @submit.prevent="applyFilters">
        <div class="sm:col-span-2 lg:col-span-1">
          <label for="invoice-search" class="field-label">Cari nomor tagihan atau siswa</label>
          <div class="flex gap-2">
            <input
              id="invoice-search"
              v-model="filters.search"
              type="search"
              class="field-input min-w-0"
              placeholder="Nomor tagihan, nama, atau NIS"
            />
            <button type="submit" class="btn btn-secondary shrink-0">Cari</button>
          </div>
        </div>
        <div>
          <label for="invoice-filter-year" class="field-label">Tahun pelajaran</label>
          <select id="invoice-filter-year" v-model="filters.academic_year_id" class="field-input" @change="applyFilters">
            <option value="">Semua tahun</option>
            <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">{{ ay.name }}</option>
          </select>
        </div>
        <div>
          <label for="invoice-filter-class" class="field-label">Kelas</label>
          <select id="invoice-filter-class" v-model="filters.class_id" class="field-input" @change="applyFilters">
            <option value="">Semua kelas</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label for="invoice-filter-status" class="field-label">Status</label>
          <select id="invoice-filter-status" v-model="filters.status" class="field-input" @change="applyFilters">
            <option value="">Semua status</option>
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
      </form>
      <div v-if="hasFilterInput" class="mt-3">
        <button type="button" class="btn btn-ghost btn-sm" @click="resetFilters">Hapus semua saringan</button>
      </div>
    </section>

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Mengambil daftar tagihan." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0 && queriedWithFilters"
        state="empty"
        title="Tidak ada tagihan yang cocok."
        message="Saringan yang dipilih tidak menemukan tagihan. Ubah saringan atau hapus semuanya."
      >
        <button type="button" class="btn btn-secondary" @click="resetFilters">Hapus semua saringan</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        title="Belum ada tagihan."
        :message="isBendahara ? 'Tagihan dibuat per kelas dari tarif yang sudah diatur.' : 'Bendahara belum membuat tagihan.'"
      >
        <router-link v-if="isBendahara" to="/billing/invoices/generate" class="btn btn-primary">Buat tagihan</router-link>
      </DataState>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="table-data">
            <thead>
              <tr>
                <th scope="col">Siswa</th>
                <th scope="col">Jenis dan periode</th>
                <th scope="col" class="!text-right">Total</th>
                <th scope="col" class="!text-right">Sisa</th>
                <th scope="col">Status</th>
                <th scope="col">No. tagihan</th>
                <th scope="col"><span class="sr-only">Aksi</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td class="min-w-44">
                  <span class="font-medium">{{ item.student?.name || '-' }}</span>
                  <span class="mt-0.5 block text-sm text-tinta-2">
                    {{ studentMeta(item.student) }}
                  </span>
                </td>
                <td class="min-w-44">
                  <span v-for="line in itemLines(item).shown" :key="line" class="block">{{ line }}</span>
                  <span v-if="itemLines(item).hidden > 0" class="block text-sm text-tinta-2">
                    dan {{ itemLines(item).hidden }} lainnya
                  </span>
                </td>
                <td class="num">{{ formatRupiah(item.total_amount) }}</td>
                <td class="num font-semibold" :class="Number(item.remaining_amount) > 0 ? '' : 'text-tinta-2'">
                  {{ formatRupiah(item.remaining_amount) }}
                </td>
                <td><StatusTag :status="item.status" /></td>
                <td class="whitespace-nowrap">
                  {{ item.invoice_number }}
                  <span class="mt-0.5 block text-sm text-tinta-2">Dibuat {{ formatDate(item.created_at) }}</span>
                </td>
                <td class="text-right">
                  <button type="button" class="btn btn-ghost btn-sm" @click="showDetail(item)">
                    Rincian<span class="sr-only"> tagihan {{ item.invoice_number }}</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav
          v-if="pagination.total > pagination.per_page"
          class="flex flex-wrap items-center justify-between gap-3 border-t border-garis px-4 py-3"
          aria-label="Halaman daftar tagihan"
        >
          <p class="text-sm text-tinta-2">
            Menampilkan {{ pagination.from }} sampai {{ pagination.to }} dari {{ pagination.total }} tagihan
          </p>
          <div class="flex gap-2">
            <button
              type="button"
              class="btn btn-secondary"
              :disabled="!pagination.prev_page_url"
              @click="changePage(pagination.current_page - 1)"
            >
              Sebelumnya
            </button>
            <button
              type="button"
              class="btn btn-secondary"
              :disabled="!pagination.next_page_url"
              @click="changePage(pagination.current_page + 1)"
            >
              Berikutnya
            </button>
          </div>
        </nav>
      </template>
    </section>

    <BaseModal
      :open="Boolean(detailInvoice)"
      size="lg"
      :title="detailInvoice ? `Tagihan ${detailInvoice.invoice_number}` : ''"
      :description="detailInvoice ? detailDescription : ''"
      @close="detailInvoice = null"
    >
      <template v-if="detailInvoice">
        <dl class="mb-5 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
          <div>
            <dt class="text-sm text-tinta-2">Status</dt>
            <dd class="mt-1"><StatusTag :status="detailInvoice.status" /></dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Jatuh tempo</dt>
            <dd class="mt-1 font-medium">{{ formatDate(detailInvoice.due_date) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Total</dt>
            <dd class="mt-1 font-medium tabular-nums">{{ formatRupiah(detailInvoice.total_amount) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-tinta-2">Sisa</dt>
            <dd class="mt-1 font-semibold tabular-nums">{{ formatRupiah(detailInvoice.remaining_amount) }}</dd>
          </div>
        </dl>

        <p v-if="!detailInvoice.items?.length" class="text-tinta-2">Tagihan ini tidak memiliki rincian item.</p>
        <div v-else class="overflow-x-auto rounded-[var(--radius-kontrol)] border border-garis">
          <table class="table-data">
            <thead>
              <tr>
                <th scope="col">Jenis</th>
                <th scope="col">Periode</th>
                <th scope="col" class="!text-right">Nominal</th>
                <th scope="col" class="!text-right">Dibayar</th>
                <th scope="col" class="!text-right">Sisa</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in detailInvoice.items" :key="line.id">
                <td>
                  {{ line.payment_type?.name || line.description || '-' }}
                  <span v-if="line.payment_type && line.description" class="mt-0.5 block text-sm text-tinta-2">{{ line.description }}</span>
                </td>
                <td class="whitespace-nowrap">{{ line.period || '-' }}</td>
                <td class="num">{{ formatRupiah(line.amount) }}</td>
                <td class="num">{{ formatRupiah(line.paid_amount) }}</td>
                <td class="num font-semibold">{{ formatRupiah(line.remaining_amount) }}</td>
                <td><StatusTag :status="line.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </BaseModal>
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotice } from '@/composables/useNotice'
import { errorMessage, formatDate, formatRupiah } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const authStore = useAuthStore()
const notice = useNotice()
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

/** Values match the invoices.status enum. */
const statusOptions = [
  { value: 'BELUM BAYAR', label: 'Belum bayar' },
  { value: 'SEBAGIAN', label: 'Sebagian' },
  { value: 'LUNAS', label: 'Lunas' },
  { value: 'CANCELLED', label: 'Dibatalkan' },
]

const items = ref([])
const academicYears = ref([])
const classes = ref([])
const loading = ref(true)
const loadError = ref('')
const queriedWithFilters = ref(false)
const detailInvoice = ref(null)

const emptyFilters = () => ({ search: '', academic_year_id: '', class_id: '', status: '' })
const filters = ref(emptyFilters())

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
  prev_page_url: null,
  next_page_url: null,
})

const hasFilterInput = computed(() => Object.values(filters.value).some((value) => value !== ''))

const studentMeta = (student) => {
  if (!student) return ''
  const parts = [student.nis, student.class_room?.name].filter(Boolean)
  return parts.join(', ')
}

/**
 * Short "jenis + periode" lines for the table cell; the full list lives in the detail dialog.
 *
 * @return {{ shown: string[], hidden: number }}
 */
const itemLines = (invoice) => {
  const lines = (invoice.items ?? []).map((line) => {
    const name = line.payment_type?.name || line.description || 'Tagihan'
    return line.period ? `${name} ${line.period}` : name
  })
  return { shown: lines.slice(0, 2), hidden: Math.max(lines.length - 2, 0) }
}

const detailDescription = computed(() => {
  const invoice = detailInvoice.value
  if (!invoice) return ''
  const parts = [invoice.student?.name, invoice.student?.class_room?.name, invoice.academic_year?.name].filter(Boolean)
  return parts.join(', ')
})

const fetchDependencies = async () => {
  try {
    const [ayRes, clRes] = await Promise.all([axios.get('/api/v1/academic-years'), axios.get('/api/v1/classes')])
    academicYears.value = ayRes.data.data
    classes.value = clRes.data.data
  } catch (error) {
    notice.error(errorMessage(error, 'Pilihan tahun dan kelas gagal dimuat. Muat ulang halaman untuk menyaring.'))
  }
}

const fetchData = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const params = {
      ...filters.value,
      page: pagination.value.current_page,
    }
    const response = await axios.get('/api/v1/invoices', { params })
    items.value = response.data.data
    queriedWithFilters.value = hasFilterInput.value

    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      per_page: response.data.per_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url,
    }
  } catch (error) {
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  pagination.value.current_page = 1
  fetchData()
}

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return
  pagination.value.current_page = page
  fetchData()
}

const resetFilters = () => {
  filters.value = emptyFilters()
  pagination.value.current_page = 1
  fetchData()
}

const showDetail = (item) => {
  detailInvoice.value = item
}

onMounted(async () => {
  await fetchDependencies()
  fetchData()
})
</script>
