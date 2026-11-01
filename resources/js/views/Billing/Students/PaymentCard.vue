<template>
  <div class="min-h-screen bg-krem print:bg-white">
    <header class="no-print border-b border-garis bg-kertas">
      <div class="mx-auto flex max-w-[1180px] flex-wrap items-end gap-4 px-4 py-4 sm:px-6">
        <div class="min-w-0 flex-1 basis-64">
          <router-link :to="`/siswa/${studentId}/rekap`" class="link text-sm">Kembali ke rekap siswa</router-link>
          <h1 class="mt-2 text-[1.9rem] font-bold leading-none">Kartu bukti pembayaran</h1>
          <p class="mt-1.5 text-sm text-tinta-2">
            Cetak di A4 posisi mendatar, lalu lipat di garis putus-putus. Kertas tebal (230 gsm) lebih awet dibawa setahun.
          </p>
        </div>

        <fieldset class="w-full sm:w-auto">
          <legend class="field-label">Isi kartu</legend>
          <div class="flex flex-wrap gap-2">
            <label
              v-for="option in modeOptions"
              :key="option.value"
              class="flex min-h-11 cursor-pointer items-center gap-2 rounded-[var(--radius-kontrol)] border px-3"
              :class="mode === option.value ? 'border-bata bg-bata-muda font-semibold' : 'border-garis-kuat bg-kertas'"
            >
              <input v-model="mode" type="radio" name="card-mode" :value="option.value" class="field-check" />
              {{ option.label }}
            </label>
          </div>
        </fieldset>

        <div v-if="card && card.academic_years.length > 1" class="w-full sm:w-48">
          <label for="card-year" class="field-label">Tahun pelajaran</label>
          <select id="card-year" v-model="selectedYearId" class="field-input" @change="fetchCard">
            <option v-for="year in card.academic_years" :key="year.id" :value="year.id">{{ year.name }}</option>
          </select>
        </div>
        <button type="button" class="btn btn-primary" :disabled="loading || !card" @click="printCard">
          {{ mode === 'kosong' ? 'Cetak kartu kosong' : 'Cetak kartu terisi' }}
        </button>
      </div>
    </header>

    <main class="mx-auto max-w-[1180px] px-4 py-6 sm:px-6 print:m-0 print:max-w-none print:p-0">
      <section v-if="loading || loadError" class="panel">
        <DataState v-if="loading" state="loading" message="Menyiapkan kartu siswa." />
        <DataState v-else state="error" :message="loadError">
          <button type="button" class="btn btn-secondary" @click="fetchCard">Coba lagi</button>
        </DataState>
      </section>

      <template v-else-if="card">
        <p class="no-print mb-4 text-sm text-tinta-2">
          <template v-if="mode === 'kosong'">
            Kartu kosong untuk dibawa siswa. Setiap kali siswa membayar, bendahara menulis tanggal dan jumlah dari layar kasir, lalu memaraf.
          </template>
          <template v-else>
            Kartu pengganti untuk kartu yang hilang atau rusak. Tanggal dan jumlah diisi dari pembayaran yang tercatat; paraf tetap ditulis tangan.
          </template>
        </p>
        <p
          v-if="mode === 'terisi' && unmatchedCount > 1"
          class="no-print mb-4 rounded-[var(--radius-kontrol)] bg-amber-muda px-4 py-3 text-sm"
        >
          Ada {{ unmatchedCount }} jenis pembayaran yang tidak cocok dengan uraian kartu, semuanya masuk ke baris 10:
          {{ lainnya?.items.join(', ') }}.
        </p>

        <div class="overflow-x-auto pb-2 print:overflow-visible print:pb-0">
          <PaymentCardSheet
            :student-name="card.student.name"
            :class-name="card.student.class_name"
            :year-name="card.academic_year?.name"
            :monthly="card.monthly"
            :annual="card.annual"
            :blank="mode === 'kosong'"
          />
        </div>
      </template>
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import DataState from '@/components/app/DataState.vue'
import PaymentCardSheet, { type CardMonth, type CardSlot } from '@/components/billing/PaymentCardSheet.vue'
import { errorMessage } from '@/utils/format'

interface CardData {
  student: { id: number; name: string; nis: string | null; class_name: string | null }
  academic_year: { id: number; name: string } | null
  academic_years: { id: number; name: string }[]
  monthly: CardMonth[]
  annual: CardSlot[]
}

const route = useRoute()
const studentId = route.params.id as string

const modeOptions = [
  { value: 'kosong', label: 'Kartu kosong' },
  { value: 'terisi', label: 'Terisi dari riwayat' },
] as const

const mode = ref<'kosong' | 'terisi'>(route.query.mode === 'terisi' ? 'terisi' : 'kosong')
const card = ref<CardData | null>(null)
const loading = ref(true)
const loadError = ref('')
const selectedYearId = ref<number | null>(null)

const lainnya = computed(() => card.value?.annual.find((slot) => slot.key === 'lainnya'))
const unmatchedCount = computed(() => lainnya.value?.items.length ?? 0)

const fetchCard = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await axios.get(`/api/v1/payments/student/${studentId}/card`, {
      params: selectedYearId.value ? { academic_year_id: selectedYearId.value } : {},
    })
    card.value = response.data.data
    selectedYearId.value = card.value?.academic_year?.id ?? null
  } catch (error) {
    loadError.value = errorMessage(error, 'Data kartu gagal dimuat. Periksa koneksi, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const printCard = () => {
  const previousTitle = document.title
  document.title = `Kartu pembayaran ${card.value?.student.name ?? ''} ${card.value?.academic_year?.name ?? ''}`.trim()
  window.print()
  document.title = previousTitle
}

onMounted(fetchCard)
</script>
