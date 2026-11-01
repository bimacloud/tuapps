<template>
  <admin-layout>
    <PageHeader
      title="Buat Tagihan"
      description="Membuat tagihan satu jenis pembayaran untuk semua siswa aktif di satu kelas, memakai tarif aktif tahun tersebut."
    >
      <template #actions>
        <router-link to="/invoices" class="btn btn-secondary">Kembali ke daftar tagihan</router-link>
      </template>
    </PageHeader>

    <div class="max-w-3xl space-y-4">
      <section
        v-if="result"
        class="panel border-l-4 !border-l-lunas p-5"
        role="status"
        aria-labelledby="generate-result-title"
      >
        <h2 id="generate-result-title" class="text-xl font-bold">Tagihan selesai dibuat</h2>
        <p class="mt-1 text-sm text-tinta-2">{{ result.context }}</p>
        <dl v-if="result.hasCounts" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div class="rounded-[var(--radius-kontrol)] bg-lunas-muda px-4 py-3">
            <dt class="text-sm font-semibold text-lunas">Dibuat</dt>
            <dd class="font-display text-3xl font-bold tabular-nums text-tinta">{{ result.generated }} tagihan</dd>
          </div>
          <div class="rounded-[var(--radius-kontrol)] bg-redup px-4 py-3">
            <dt class="text-sm font-semibold text-tinta-2">Dilewati karena sudah ada</dt>
            <dd class="font-display text-3xl font-bold tabular-nums text-tinta">{{ result.skipped }} siswa</dd>
          </div>
        </dl>
        <p v-else class="mt-3">{{ result.message }}</p>
      </section>

      <section class="panel">
        <DataState v-if="depsLoading" state="loading" message="Mengambil tahun pelajaran, kelas, dan jenis pembayaran." />
        <DataState v-else-if="depsError" state="error" :message="depsError">
          <button type="button" class="btn btn-secondary" @click="fetchDependencies">Coba lagi</button>
        </DataState>

        <form v-else class="p-5" @submit.prevent="generateInvoices">
          <p class="mb-5 text-sm text-tinta-2">
            Siswa yang sudah punya tagihan dengan jenis dan periode yang sama dilewati, jadi halaman ini aman dipakai
            ulang untuk kelas yang sama.
          </p>

          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
              <label for="generate-year" class="field-label">Tahun pelajaran</label>
              <select
                id="generate-year"
                v-model="form.academic_year_id"
                required
                class="field-input"
                :aria-invalid="Boolean(fieldErrors.academic_year_id)"
                :aria-describedby="fieldErrors.academic_year_id ? 'generate-year-error' : undefined"
              >
                <option value="" disabled>Pilih tahun pelajaran</option>
                <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">
                  {{ ay.name }}{{ ay.is_active ? ' (aktif)' : '' }}
                </option>
              </select>
              <p v-if="fieldErrors.academic_year_id" id="generate-year-error" class="field-error">{{ fieldErrors.academic_year_id[0] }}</p>
            </div>

            <div>
              <label for="generate-class" class="field-label">Kelas</label>
              <select
                id="generate-class"
                v-model="form.class_id"
                required
                class="field-input"
                :aria-invalid="Boolean(fieldErrors.class_id)"
                :aria-describedby="fieldErrors.class_id ? 'generate-class-error' : (classes.length === 0 ? 'generate-class-hint' : undefined)"
              >
                <option value="" disabled>Pilih kelas</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <p v-if="fieldErrors.class_id" id="generate-class-error" class="field-error">{{ fieldErrors.class_id[0] }}</p>
              <p v-else-if="classes.length === 0" id="generate-class-hint" class="field-hint">
                Belum ada kelas. Minta Administrasi menambahkannya di menu
                <router-link to="/master/classes" class="link">Kelas</router-link>.
              </p>
            </div>

            <div>
              <label for="generate-type" class="field-label">Jenis pembayaran</label>
              <select
                id="generate-type"
                v-model="form.payment_type_id"
                required
                class="field-input"
                :aria-invalid="Boolean(fieldErrors.payment_type_id)"
                :aria-describedby="fieldErrors.payment_type_id ? 'generate-type-error' : (activePaymentTypes.length === 0 ? 'generate-type-hint' : undefined)"
              >
                <option value="" disabled>Pilih jenis pembayaran</option>
                <option v-for="pt in activePaymentTypes" :key="pt.id" :value="pt.id">
                  {{ pt.code }}, {{ pt.name }} ({{ typeLabel(pt.type) }})
                </option>
              </select>
              <p v-if="fieldErrors.payment_type_id" id="generate-type-error" class="field-error">{{ fieldErrors.payment_type_id[0] }}</p>
              <p v-else-if="activePaymentTypes.length === 0" id="generate-type-hint" class="field-hint">
                Belum ada jenis pembayaran aktif. Tambahkan di menu
                <router-link to="/billing/payment-types" class="link">Jenis Pembayaran</router-link>.
              </p>
            </div>

            <div>
              <template v-if="isMonthly">
                <label for="generate-period" class="field-label">Bulan</label>
                <select
                  id="generate-period"
                  v-model="form.period"
                  required
                  class="field-input"
                  :aria-invalid="Boolean(fieldErrors.period)"
                  :aria-describedby="fieldErrors.period ? 'generate-period-error' : undefined"
                >
                  <option value="" disabled>Pilih bulan</option>
                  <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
                </select>
              </template>
              <template v-else>
                <label for="generate-period" class="field-label">Periode</label>
                <input
                  id="generate-period"
                  v-model="form.period"
                  type="text"
                  required
                  maxlength="50"
                  class="field-input"
                  placeholder="Misalnya Semester 1 atau Genap"
                  :aria-invalid="Boolean(fieldErrors.period)"
                  :aria-describedby="fieldErrors.period ? 'generate-period-error generate-period-hint' : 'generate-period-hint'"
                />
                <p id="generate-period-hint" class="field-hint">
                  Tulis dengan ejaan yang sama setiap kali. Tagihan ganda dicek dari teks periode ini.
                </p>
              </template>
              <p v-if="fieldErrors.period" id="generate-period-error" class="field-error">{{ fieldErrors.period[0] }}</p>
            </div>

            <div>
              <label for="generate-due-date" class="field-label">
                Jatuh tempo <span class="font-normal text-tinta-2">(opsional)</span>
              </label>
              <input
                id="generate-due-date"
                v-model="form.due_date"
                type="date"
                class="field-input"
                :aria-invalid="Boolean(fieldErrors.due_date)"
                :aria-describedby="fieldErrors.due_date ? 'generate-due-date-error' : undefined"
              />
              <p v-if="fieldErrors.due_date" id="generate-due-date-error" class="field-error">{{ fieldErrors.due_date[0] }}</p>
            </div>
          </div>

          <p
            v-if="generateError && !hasFieldErrors"
            class="mt-5 rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
            role="alert"
          >
            {{ generateError }}
          </p>

          <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-garis pt-5">
            <button type="submit" class="btn btn-primary" :disabled="generating">
              {{ generating ? 'Membuat tagihan...' : 'Buat tagihan kelas ini' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useNotice } from '@/composables/useNotice'
import { errorMessage } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'

const notice = useNotice()

const typeLabels = { BULANAN: 'Bulanan', SEMESTER: 'Semester', TAHUNAN: 'Tahunan', KHUSUS: 'Khusus' }
const typeLabel = (type) => typeLabels[type] ?? type

const academicYears = ref([])
const classes = ref([])
const paymentTypes = ref([])
const depsLoading = ref(true)
const depsError = ref('')

const generating = ref(false)
const generateError = ref('')
const fieldErrors = ref({})
const result = ref(null)

/** School year order, as on the printed payment card. */
const months = ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni']

const form = ref({
  academic_year_id: '',
  class_id: '',
  payment_type_id: '',
  period: '',
  due_date: '',
})

const activePaymentTypes = computed(() => paymentTypes.value.filter((pt) => pt.status))

const selectedPaymentType = computed(() => {
  if (!form.value.payment_type_id) return null
  return activePaymentTypes.value.find((pt) => pt.id === form.value.payment_type_id)
})

const isMonthly = computed(() => !selectedPaymentType.value || selectedPaymentType.value.type === 'BULANAN')
const hasFieldErrors = computed(() => Object.keys(fieldErrors.value).length > 0)

const fetchDependencies = async () => {
  depsLoading.value = true
  depsError.value = ''
  try {
    const [ayRes, clRes, ptRes] = await Promise.all([
      axios.get('/api/v1/academic-years'),
      axios.get('/api/v1/classes'),
      axios.get('/api/v1/payment-types'),
    ])
    academicYears.value = ayRes.data.data
    classes.value = clRes.data.data
    paymentTypes.value = ptRes.data.data

    const activeYear = academicYears.value.find((y) => y.is_active)
    if (activeYear && !form.value.academic_year_id) {
      form.value.academic_year_id = activeYear.id
    }
  } catch (error) {
    depsError.value = errorMessage(error, 'Pilihan tahun, kelas, dan jenis pembayaran gagal dimuat. Periksa koneksi, lalu coba lagi.')
  } finally {
    depsLoading.value = false
  }
}

const generateInvoices = async () => {
  const className = classes.value.find((c) => c.id === form.value.class_id)?.name ?? 'terpilih'
  const typeName = selectedPaymentType.value?.name ?? 'terpilih'
  const yearName = academicYears.value.find((ay) => ay.id === form.value.academic_year_id)?.name ?? ''
  const period = form.value.period

  const confirmed = await notice.confirm({
    title: `Buat tagihan kelas ${className}?`,
    message: `Tagihan ${typeName} periode ${period} dibuat untuk semua siswa aktif di kelas ${className}. Siswa yang sudah punya tagihan ini dilewati.`,
    confirmLabel: 'Buat tagihan',
  })
  if (!confirmed) return

  generating.value = true
  generateError.value = ''
  fieldErrors.value = {}
  result.value = null

  try {
    const response = await axios.post('/api/v1/invoices/generate', form.value)
    const data = response.data
    result.value = {
      hasCounts: typeof data.generated === 'number' && typeof data.skipped === 'number',
      generated: data.generated,
      skipped: data.skipped,
      message: data.message,
      context: [`Kelas ${className}`, typeName, `periode ${period}`, yearName].filter(Boolean).join(', '),
    }
    notice.success(`Tagihan ${typeName} ${period} untuk kelas ${className} selesai dibuat.`)

    // Clear only the period so the next month can be generated right away.
    form.value.period = ''
  } catch (error) {
    fieldErrors.value = error.response?.data?.errors ?? {}
    generateError.value = errorMessage(error, 'Tagihan gagal dibuat. Coba lagi.')
  } finally {
    generating.value = false
  }
}

onMounted(fetchDependencies)
</script>
