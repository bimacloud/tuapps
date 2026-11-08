<template>
  <admin-layout>
    <PageHeader
      title="Setup Awal"
      description="Urutan pengisian data sebelum aplikasi dipakai menagih. Kerjakan dari atas ke bawah: tiap langkah membutuhkan data dari langkah sebelumnya."
    />

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Memeriksa data yang sudah terisi." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchStatus">Coba lagi</button>
      </DataState>

      <ol v-else class="divide-y divide-garis">
        <li v-for="(step, index) in steps" :key="step.key" class="flex flex-col gap-4 px-5 py-5 lg:flex-row lg:items-start">
          <span
            class="flex size-9 shrink-0 items-center justify-center rounded-full font-bold tabular-nums"
            :class="step.done ? 'bg-lunas-muda text-lunas' : 'bg-redup text-tinta-2'"
            aria-hidden="true"
          >
            {{ index + 1 }}
          </span>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
              <h2 class="text-xl font-bold leading-tight">{{ step.title }}</h2>
              <StatusTag :status="step.done ? 'APPROVED' : 'CANCELLED'" :label="step.done ? 'Sudah terisi' : 'Belum'" />
              <span class="text-sm text-tinta-2">Dikerjakan oleh {{ step.role }}</span>
            </div>
            <p class="mt-1 max-w-3xl text-tinta-2">{{ step.todo }}</p>
            <p v-if="step.progressText" class="mt-1 text-sm font-semibold">{{ step.progressText }}</p>
          </div>

          <div class="flex flex-wrap gap-2 lg:justify-end">
            <router-link :to="step.path" class="btn btn-secondary btn-sm">Buka menu</router-link>
            <template v-if="step.dataset && canWork(step)">
              <button
                type="button"
                class="btn btn-secondary btn-sm"
                :disabled="exportingKey === step.key"
                @click="exportData(step)"
              >
                {{ exportingKey === step.key ? 'Menyiapkan file...' : 'Unduh data' }}
              </button>
              <button type="button" class="btn btn-primary btn-sm" @click="importStep = step">Impor dari Excel</button>
            </template>
          </div>
        </li>
      </ol>
    </section>

    <section class="panel mt-4 px-5 py-4" aria-labelledby="after-setup-heading">
      <h2 id="after-setup-heading" class="text-xl font-bold">Setelah semua langkah selesai</h2>
      <ul class="mt-2 list-disc space-y-1 pl-5 text-tinta-2">
        <li>Bendahara mencetak kartu pembayaran kosong per kelas di menu Kartu Pembayaran.</li>
        <li>Setiap hari kerja, Bendahara membuka kas di menu Kasir sebelum menerima pembayaran dan menutupnya di akhir hari.</li>
        <li>
          Format file impor: baris pertama judul kolom sesuai template, data mulai baris kedua. Impor ulang file yang sama
          memperbarui data yang sudah ada (kecuali tarif), jadi aman untuk memperbaiki kesalahan.
        </li>
      </ul>
    </section>

    <ImportExcelModal
      v-if="importStep?.dataset"
      :open="Boolean(importStep)"
      :dataset="importStep.dataset"
      :title="importStep.title"
      :notes="importStep.notes"
      @close="importStep = null"
      @imported="fetchStatus"
    />
  </admin-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotice } from '@/composables/useNotice'
import { errorMessage } from '@/utils/format'
import { downloadFile } from '@/utils/download'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import ImportExcelModal from '@/components/app/ImportExcelModal.vue'

interface StepStatus {
  key: string
  done: boolean
  count: number | null
  detail: string | null
}

interface StepGuide {
  key: string
  title: string
  role: 'Administrasi' | 'Bendahara'
  path: string
  todo: string
  /** Dataset key for Excel template, export and import. Steps without it are filled in their menu only. */
  dataset?: string
  notes?: string[]
  progress: (status: StepStatus) => string
}

type Step = StepGuide & { done: boolean; progressText: string }

const guides: StepGuide[] = [
  {
    key: 'settings',
    title: 'Identitas sekolah',
    role: 'Administrasi',
    path: '/settings',
    todo: 'Isi nama, alamat, telepon, email, dan logo sekolah. Data ini tercetak di kuitansi, kartu pembayaran, dan laporan.',
    progress: (s) => (s.detail ? `Nama sekolah: ${s.detail}` : ''),
  },
  {
    key: 'academic-years',
    title: 'Tahun pelajaran',
    role: 'Administrasi',
    path: '/master/academic-years',
    dataset: 'academic-years',
    todo: 'Tambahkan tahun pelajaran yang sedang berjalan beserta tanggal mulai dan selesai, lalu tandai sebagai aktif.',
    notes: ['Hanya satu baris yang boleh berisi Aktif = Ya.'],
    progress: (s) => `${s.count} tahun pelajaran${s.detail ? `, aktif: ${s.detail}` : ', belum ada yang aktif'}`,
  },
  {
    key: 'majors',
    title: 'Jurusan',
    role: 'Administrasi',
    path: '/master/majors',
    dataset: 'majors',
    todo: 'Daftarkan semua kompetensi keahlian dengan kode singkat (contoh TKJ). Kode dipakai saat mengimpor kelas.',
    notes: ['Jurusan dengan kode yang sudah ada akan diperbarui namanya.'],
    progress: (s) => `${s.count} jurusan`,
  },
  {
    key: 'classes',
    title: 'Kelas',
    role: 'Administrasi',
    path: '/master/classes',
    dataset: 'classes',
    todo: 'Buat rombongan belajar untuk tahun pelajaran aktif. Setiap kelas menyebut kode jurusan dan nama tahun pelajaran.',
    notes: ['Kode jurusan dan nama tahun pelajaran harus sudah ada di aplikasi.'],
    progress: (s) => `${s.count} kelas di tahun pelajaran aktif`,
  },
  {
    key: 'students',
    title: 'Siswa dan wali',
    role: 'Administrasi',
    path: '/master/students',
    dataset: 'students',
    todo: 'Masukkan siswa per kelas beserta nama dan nomor WhatsApp wali. Nomor wali dipakai untuk pemberitahuan pembayaran.',
    notes: [
      'Nama kelas harus sama persis dengan menu Kelas.',
      'Isi NIS atau NISN supaya impor ulang memperbarui siswa, bukan menambah siswa ganda.',
    ],
    progress: (s) => `${s.count} siswa aktif`,
  },
  {
    key: 'payment-types',
    title: 'Jenis pembayaran',
    role: 'Bendahara',
    path: '/billing/payment-types',
    dataset: 'payment-types',
    todo: 'Daftarkan pos pembayaran (SPP, Daftar Ulang, LKS, ujian, PKL) dengan kode dan tipe: bulanan, semester, tahunan, atau khusus.',
    notes: ['Jenis dengan kode yang sudah ada akan diperbarui.'],
    progress: (s) => `${s.count} jenis pembayaran`,
  },
  {
    key: 'payment-rates',
    title: 'Tarif pembayaran',
    role: 'Bendahara',
    path: '/billing/payment-rates',
    dataset: 'payment-rates',
    todo: 'Isi nominal tiap jenis pembayaran untuk tahun pelajaran aktif. Kosongkan kelas jika nominalnya sama untuk semua kelas.',
    notes: ['Tarif yang sudah ada tidak ditimpa. Ubah nominalnya lewat menu Tarif Pembayaran.'],
    progress: (s) => `${s.count} tarif di tahun pelajaran aktif`,
  },
  {
    key: 'invoices',
    title: 'Buat tagihan',
    role: 'Bendahara',
    path: '/billing/invoices/generate',
    todo: 'Setelah siswa dan tarif lengkap, buat tagihan untuk tahun pelajaran aktif. Tagihan inilah yang dibayar di kasir.',
    progress: (s) => `${s.count} tagihan di tahun pelajaran aktif`,
  },
]

const authStore = useAuthStore()
const notice = useNotice()

const statuses = ref<StepStatus[]>([])
const loading = ref(true)
const loadError = ref('')
const exportingKey = ref('')
const importStep = ref<Step | null>(null)

const steps = computed<Step[]>(() =>
  guides.map((guide) => {
    const status = statuses.value.find((item) => item.key === guide.key) ?? { key: guide.key, done: false, count: 0, detail: null }
    return { ...guide, done: status.done, progressText: guide.progress(status) }
  }),
)

const canWork = (step: StepGuide) => authStore.hasRole(step.role)

const fetchStatus = async () => {
  loading.value = true
  loadError.value = ''
  try {
    statuses.value = (await axios.get('/api/v1/setup/status')).data.data
  } catch (error) {
    loadError.value = errorMessage(error, 'Status setup gagal diambil.')
  } finally {
    loading.value = false
  }
}

const exportData = async (step: StepGuide) => {
  exportingKey.value = step.key
  try {
    await downloadFile(`/api/v1/setup/${step.dataset}/export`, `data-${step.dataset}.xlsx`)
  } catch {
    notice.error(`Data ${step.title.toLowerCase()} gagal diunduh. Coba lagi.`)
  } finally {
    exportingKey.value = ''
  }
}

onMounted(fetchStatus)
</script>
