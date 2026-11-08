<template>
  <admin-layout>
    <PageHeader
      title="Tarif Pembayaran"
      description="Nominal tiap jenis pembayaran per tahun pelajaran. Tarif tanpa kelas berlaku untuk semua kelas."
    >
      <template v-if="isBendahara" #actions>
        <button type="button" class="btn btn-secondary" :disabled="exporting" @click="exportData">
          {{ exporting ? 'Menyiapkan file...' : 'Unduh data' }}
        </button>
        <button type="button" class="btn btn-secondary" @click="showImportModal = true">Impor dari Excel</button>
        <button type="button" class="btn btn-primary" @click="openModal()">Tambah tarif</button>
      </template>
    </PageHeader>

    <section class="panel mb-4 p-4" aria-label="Saring tarif">
      <div class="max-w-xs">
        <label for="rate-filter-year" class="field-label">Tahun pelajaran</label>
        <select id="rate-filter-year" v-model="filters.academic_year_id" class="field-input" @change="fetchData">
          <option value="">Semua tahun</option>
          <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">
            {{ ay.name }}{{ ay.is_active ? ' (aktif)' : '' }}
          </option>
        </select>
      </div>
    </section>

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Mengambil daftar tarif." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0 && filters.academic_year_id"
        state="empty"
        :title="`Belum ada tarif untuk tahun ${selectedYearName}.`"
        :message="isBendahara ? 'Tambahkan tarif untuk tahun ini, atau lihat tarif dari semua tahun.' : 'Lihat tarif dari semua tahun pelajaran.'"
      >
        <button type="button" class="btn btn-secondary" @click="showAllYears">Tampilkan semua tahun</button>
        <button v-if="isBendahara" type="button" class="btn btn-primary" @click="openModal()">Tambah tarif</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        title="Belum ada tarif."
        :message="isBendahara ? 'Tagihan belum bisa dibuat sebelum tarif diatur. Tambahkan tarif pertama.' : 'Bendahara belum mengatur tarif pembayaran.'"
      >
        <button v-if="isBendahara" type="button" class="btn btn-primary" @click="openModal()">Tambah tarif</button>
      </DataState>

      <div v-else class="overflow-x-auto">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col">Jenis pembayaran</th>
              <th scope="col">Tahun pelajaran</th>
              <th scope="col">Berlaku untuk</th>
              <th scope="col" class="!text-right">Nominal</th>
              <th scope="col" class="w-28">Status</th>
              <th v-if="isBendahara" scope="col" class="w-40"><span class="sr-only">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <span class="font-medium">{{ item.payment_type?.name || '-' }}</span>
                <span v-if="item.payment_type" class="mt-0.5 block text-sm text-tinta-2">
                  {{ item.payment_type.code }}, {{ typeLabel(item.payment_type.type) }}
                </span>
              </td>
              <td class="whitespace-nowrap">{{ item.academic_year?.name || '-' }}</td>
              <td>
                <template v-if="item.class_room">
                  {{ item.class_room.name }}
                  <span v-if="item.class_room.major" class="mt-0.5 block text-sm text-tinta-2">{{ item.class_room.major.name }}</span>
                </template>
                <span v-else class="text-tinta-2">Semua kelas</span>
              </td>
              <td class="num font-semibold">{{ formatRupiah(item.amount) }}</td>
              <td><StatusTag :status="Boolean(item.status)" /></td>
              <td v-if="isBendahara" class="text-right">
                <div class="flex justify-end gap-1">
                  <button type="button" class="btn btn-ghost btn-sm" @click="openModal(item)">
                    Ubah<span class="sr-only"> tarif {{ item.payment_type?.name }}</span>
                  </button>
                  <button type="button" class="btn btn-ghost btn-sm !text-galat" @click="deleteItem(item)">
                    Hapus<span class="sr-only"> tarif {{ item.payment_type?.name }}</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <BaseModal :open="showModal" :title="form.id ? 'Ubah tarif' : 'Tambah tarif'" @close="closeModal">
      <form id="payment-rate-form" class="space-y-4" @submit.prevent="saveItem">
        <p
          v-if="formError && !hasFieldErrors"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ formError }}
        </p>

        <div>
          <label for="rate-payment-type" class="field-label">Jenis pembayaran</label>
          <select
            id="rate-payment-type"
            v-model="form.payment_type_id"
            required
            class="field-input"
            :aria-invalid="Boolean(fieldErrors.payment_type_id)"
            :aria-describedby="fieldErrors.payment_type_id ? 'rate-payment-type-error' : (activePaymentTypes.length === 0 ? 'rate-payment-type-hint' : undefined)"
          >
            <option value="" disabled>Pilih jenis pembayaran</option>
            <option v-for="pt in activePaymentTypes" :key="pt.id" :value="pt.id">
              {{ pt.code }}, {{ pt.name }} ({{ typeLabel(pt.type) }})
            </option>
          </select>
          <p v-if="fieldErrors.payment_type_id" id="rate-payment-type-error" class="field-error">{{ fieldErrors.payment_type_id[0] }}</p>
          <p v-else-if="activePaymentTypes.length === 0" id="rate-payment-type-hint" class="field-hint">
            Belum ada jenis pembayaran aktif. Tambahkan di menu
            <router-link to="/billing/payment-types" class="link">Jenis Pembayaran</router-link>.
          </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="rate-academic-year" class="field-label">Tahun pelajaran</label>
            <select
              id="rate-academic-year"
              v-model="form.academic_year_id"
              required
              class="field-input"
              :aria-invalid="Boolean(fieldErrors.academic_year_id)"
              :aria-describedby="fieldErrors.academic_year_id ? 'rate-academic-year-error' : undefined"
            >
              <option value="" disabled>Pilih tahun pelajaran</option>
              <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">{{ ay.name }}</option>
            </select>
            <p v-if="fieldErrors.academic_year_id" id="rate-academic-year-error" class="field-error">{{ fieldErrors.academic_year_id[0] }}</p>
          </div>

          <div>
            <label for="rate-class" class="field-label">Kelas</label>
            <select
              id="rate-class"
              v-model="form.class_id"
              class="field-input"
              :aria-invalid="Boolean(fieldErrors.class_id)"
              :aria-describedby="fieldErrors.class_id ? 'rate-class-error' : 'rate-class-hint'"
            >
              <option value="">Semua kelas</option>
              <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <p v-if="fieldErrors.class_id" id="rate-class-error" class="field-error">{{ fieldErrors.class_id[0] }}</p>
            <p v-else id="rate-class-hint" class="field-hint">Pilih kelas hanya jika nominalnya berbeda dari kelas lain.</p>
          </div>
        </div>

        <div>
          <label for="rate-amount" class="field-label">Nominal (rupiah)</label>
          <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 font-semibold text-tinta-2" aria-hidden="true">Rp</span>
            <input
              id="rate-amount"
              v-model="form.amount"
              type="number"
              inputmode="numeric"
              min="1"
              step="1"
              required
              class="field-input pl-10 text-right tabular-nums"
              placeholder="Nominal tanpa titik"
              :aria-invalid="Boolean(fieldErrors.amount)"
              :aria-describedby="fieldErrors.amount ? 'rate-amount-error rate-amount-hint' : 'rate-amount-hint'"
            />
          </div>
          <p v-if="fieldErrors.amount" id="rate-amount-error" class="field-error">{{ fieldErrors.amount[0] }}</p>
          <p id="rate-amount-hint" class="field-hint">
            <template v-if="Number(form.amount) > 0">Tercatat sebagai {{ formatRupiah(form.amount) }}.</template>
            <template v-else>Ketik angka saja, tanpa titik atau Rp.</template>
          </p>
        </div>

        <label class="flex min-h-11 items-center gap-3">
          <input v-model="form.status" type="checkbox" class="field-check" />
          <span class="font-medium">Tarif aktif</span>
        </label>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="closeModal">Batal</button>
        <button type="submit" form="payment-rate-form" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan tarif' }}
        </button>
      </template>
    </BaseModal>

    <ImportExcelModal
      :open="showImportModal"
      dataset="payment-rates"
      title="Tarif Pembayaran"
      :notes="['Tarif yang sudah ada tidak ditimpa. Ubah nominalnya lewat tombol Ubah di tabel.']"
      @close="showImportModal = false"
      @imported="fetchData"
    />
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotice } from '@/composables/useNotice'
import { errorMessage, formatRupiah } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import ImportExcelModal from '@/components/app/ImportExcelModal.vue'
import { downloadFile } from '@/utils/download'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const authStore = useAuthStore()
const notice = useNotice()
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

const typeLabels = { BULANAN: 'Bulanan', SEMESTER: 'Semester', TAHUNAN: 'Tahunan', KHUSUS: 'Khusus' }
const typeLabel = (type) => typeLabels[type] ?? type

const items = ref([])
const paymentTypes = ref([])
const academicYears = ref([])
const classes = ref([])
const loading = ref(true)
const loadError = ref('')
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref({})

const filters = ref({
  academic_year_id: '',
})

const activePaymentTypes = computed(() => paymentTypes.value.filter((pt) => pt.status))
const hasFieldErrors = computed(() => Object.keys(fieldErrors.value).length > 0)
const selectedYearName = computed(
  () => academicYears.value.find((ay) => ay.id === filters.value.academic_year_id)?.name ?? 'ini',
)

const form = ref({
  id: null,
  payment_type_id: '',
  academic_year_id: '',
  class_id: '',
  amount: '',
  status: true,
})

const fetchDependencies = async () => {
  try {
    const [ptRes, ayRes, clRes] = await Promise.all([
      axios.get('/api/v1/payment-types'),
      axios.get('/api/v1/academic-years'),
      axios.get('/api/v1/classes'),
    ])
    paymentTypes.value = ptRes.data.data
    academicYears.value = ayRes.data.data
    classes.value = clRes.data.data

    const activeYear = academicYears.value.find((y) => y.is_active)
    if (activeYear) {
      filters.value.academic_year_id = activeYear.id
    }
  } catch (error) {
    notice.error(errorMessage(error, 'Pilihan jenis pembayaran, tahun, dan kelas gagal dimuat. Muat ulang halaman.'))
  }
}

const fetchData = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await axios.get('/api/v1/payment-rates', { params: filters.value })
    items.value = response.data.data
  } catch (error) {
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const showAllYears = () => {
  filters.value.academic_year_id = ''
  fetchData()
}

const openModal = (item = null) => {
  formError.value = ''
  fieldErrors.value = {}
  if (item) {
    form.value = {
      id: item.id,
      payment_type_id: item.payment_type_id,
      academic_year_id: item.academic_year_id,
      class_id: item.class_id || '',
      amount: item.amount,
      status: item.status,
    }
  } else {
    const activeYear = academicYears.value.find((y) => y.is_active)
    form.value = { id: null, payment_type_id: '', academic_year_id: activeYear ? activeYear.id : '', class_id: '', amount: '', status: true }
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}

  const payload = { ...form.value }
  if (payload.class_id === '') payload.class_id = null

  try {
    if (form.value.id) {
      await axios.put(`/api/v1/payment-rates/${form.value.id}`, payload)
    } else {
      await axios.post('/api/v1/payment-rates', payload)
    }
    notice.success(`Tarif ${formatRupiah(payload.amount)} tersimpan.`)
    closeModal()
    fetchData()
  } catch (error) {
    fieldErrors.value = error.response?.data?.errors ?? {}
    formError.value = errorMessage(error, 'Tarif gagal disimpan.')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (item) => {
  const target = item.class_room ? item.class_room.name : 'semua kelas'
  const confirmed = await notice.confirm({
    title: 'Hapus tarif?',
    message: `Tarif ${item.payment_type?.name ?? ''} untuk ${target} (${formatRupiah(item.amount)}) akan dihapus. Tagihan yang sudah dibuat tidak berubah.`,
    confirmLabel: 'Hapus tarif',
    danger: true,
  })
  if (!confirmed) return

  try {
    await axios.delete(`/api/v1/payment-rates/${item.id}`)
    notice.success('Tarif dihapus.')
    fetchData()
  } catch (error) {
    notice.error(errorMessage(error, 'Tarif gagal dihapus.'))
  }
}

// Excel export and import in the same format as the template on the Setup Awal page.
const showImportModal = ref(false)
const exporting = ref(false)
const exportData = async () => {
  exporting.value = true
  try {
    await downloadFile('/api/v1/setup/payment-rates/export', 'data-payment-rates.xlsx')
  } catch {
    notice.error('Data tarif pembayaran gagal diunduh. Coba lagi.')
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  await fetchDependencies()
  fetchData()
})
</script>
