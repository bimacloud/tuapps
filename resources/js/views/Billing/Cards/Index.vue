<template>
  <admin-layout>
    <div class="no-print">
      <PageHeader
        title="Kartu Pembayaran"
        description="Cetak kartu kosong untuk satu kelas sekaligus. Nama, kelas, dan tahun pelajaran sudah terisi; tanggal, jumlah, dan paraf ditulis bendahara setiap siswa membayar."
      >
        <template #actions>
          <button type="button" class="btn btn-primary" :disabled="!students.length || loadingCards" @click="printCards">
            {{ students.length ? `Cetak ${students.length} kartu` : 'Cetak kartu' }}
          </button>
        </template>
      </PageHeader>

      <section class="panel mb-6 grid grid-cols-1 gap-4 p-4 sm:grid-cols-2" aria-label="Pilih kelas">
        <div>
          <label for="cards-year" class="field-label">Tahun pelajaran</label>
          <select id="cards-year" v-model="selectedYearId" class="field-input" :disabled="loadingOptions" @change="onYearChange">
            <option :value="null" disabled>Pilih tahun pelajaran</option>
            <option v-for="year in academicYears" :key="year.id" :value="year.id">
              {{ year.name }}{{ year.is_active ? ' (aktif)' : '' }}
            </option>
          </select>
        </div>
        <div>
          <label for="cards-class" class="field-label">Kelas</label>
          <select id="cards-class" v-model="selectedClassId" class="field-input" :disabled="loadingOptions" @change="fetchCards">
            <option :value="null" disabled>Pilih kelas</option>
            <option v-for="classRoom in classesForYear" :key="classRoom.id" :value="classRoom.id">{{ classRoom.name }}</option>
          </select>
          <p v-if="!loadingOptions && selectedYearId && !classesForYear.length" class="field-hint">
            Belum ada kelas di tahun ini. <router-link to="/master/classes" class="link">Tambah kelas</router-link>
          </p>
        </div>
      </section>
    </div>

    <section v-if="optionsError || !selectedClassId || loadingCards || cardsError || !students.length" class="panel no-print">
      <DataState v-if="optionsError" state="error" :message="optionsError">
        <button type="button" class="btn btn-secondary" @click="loadOptions">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="!selectedClassId"
        state="empty"
        title="Pilih kelas dulu."
        message="Kartu kosong untuk semua siswa aktif di kelas itu akan tampil di sini, siap dicetak."
      />
      <DataState v-else-if="loadingCards" state="loading" message="Menyiapkan kartu untuk kelas ini." />
      <DataState v-else-if="cardsError" state="error" :message="cardsError">
        <button type="button" class="btn btn-secondary" @click="fetchCards">Coba lagi</button>
      </DataState>
      <DataState
        v-else
        state="empty"
        title="Belum ada siswa aktif di kelas ini."
        message="Kartu hanya dicetak untuk siswa berstatus Aktif."
      >
        <router-link to="/master/students" class="btn btn-secondary">Buka data siswa</router-link>
      </DataState>
    </section>

    <template v-else>
      <p class="no-print mb-4 text-sm text-tinta-2">
        {{ students.length }} kartu untuk kelas {{ cardsClassName }}, tahun pelajaran {{ cardsYearName || '-' }}. Satu lembar A4
        mendatar per siswa, dilipat di garis putus-putus.
      </p>
      <div class="kartu-stack overflow-x-auto pb-2 print:overflow-visible print:pb-0">
        <PaymentCardSheet
          v-for="student in students"
          :key="student.id"
          :student-name="student.name"
          :class-name="student.class_name"
          :year-name="cardsYearName"
          :monthly="blankMonthly"
          :annual="blankAnnual"
          blank
        />
      </div>
    </template>
  </admin-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import PaymentCardSheet, { type CardMonth, type CardSlot } from '@/components/billing/PaymentCardSheet.vue'
import { errorMessage } from '@/utils/format'

interface CardStudent {
  id: number
  name: string
  nis: string | null
  class_name: string
}

const academicYears = ref<any[]>([])
const classes = ref<any[]>([])
const loadingOptions = ref(true)
const optionsError = ref('')

const selectedYearId = ref<number | null>(null)
const selectedClassId = ref<number | null>(null)

const students = ref<CardStudent[]>([])
const cardsClassName = ref('')
const cardsYearName = ref('')
const blankMonthly = ref<CardMonth[]>([])
const blankAnnual = ref<CardSlot[]>([])
const loadingCards = ref(false)
const cardsError = ref('')

const classesForYear = computed(() =>
  classes.value.filter((classRoom) => !selectedYearId.value || classRoom.academic_year_id === selectedYearId.value),
)

const loadOptions = async () => {
  loadingOptions.value = true
  optionsError.value = ''
  try {
    const [yearsRes, classesRes] = await Promise.all([axios.get('/api/v1/academic-years'), axios.get('/api/v1/classes')])
    academicYears.value = yearsRes.data.data || []
    classes.value = classesRes.data.data || []
    selectedYearId.value = academicYears.value.find((year) => year.is_active)?.id ?? academicYears.value[0]?.id ?? null
  } catch (error) {
    optionsError.value = errorMessage(error, 'Daftar tahun pelajaran dan kelas gagal dimuat.')
  } finally {
    loadingOptions.value = false
  }
}

const onYearChange = () => {
  selectedClassId.value = null
  students.value = []
}

const fetchCards = async () => {
  if (!selectedClassId.value) return
  loadingCards.value = true
  cardsError.value = ''
  students.value = []
  try {
    const res = await axios.get('/api/v1/payment-cards', {
      params: { class_id: selectedClassId.value, academic_year_id: selectedYearId.value },
    })
    const data = res.data.data
    students.value = data.students
    cardsClassName.value = data.class.name
    cardsYearName.value = data.academic_year?.name ?? ''
    blankMonthly.value = data.months.map((month: string, index: number) => ({ no: index + 1, month, paid: 0, date: null }))
    blankAnnual.value = data.annual_slots.map((slot: { key: string; label: string; rows: number }, index: number) => ({
      no: index + 1,
      key: slot.key,
      label: slot.label,
      rows: [],
      min_rows: slot.rows,
      items: [],
    }))
  } catch (error) {
    cardsError.value = errorMessage(error, 'Kartu kelas ini gagal disiapkan.')
  } finally {
    loadingCards.value = false
  }
}

const printCards = () => {
  const previousTitle = document.title
  document.title = `Kartu pembayaran ${cardsClassName.value} ${cardsYearName.value}`.trim()
  window.print()
  document.title = previousTitle
}

onMounted(loadOptions)
</script>

<style scoped>
.kartu-stack > * + * {
  margin-top: 1.5rem;
}

.kartu-stack > :last-child {
  break-after: auto;
}

@media print {
  .kartu-stack > * + * {
    margin-top: 0;
  }
}
</style>
