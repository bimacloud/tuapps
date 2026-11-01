<template>
  <admin-layout>
    <PageHeader
      title="Data Siswa"
      :description="
        canViewFinance
          ? 'Cari siswa dengan nama, NIS, atau NISN. Rekap dan kartu pembayaran dibuka dari baris siswa.'
          : 'Cari siswa dengan nama, NIS, atau NISN. Tambah satu per satu atau impor dari Excel.'
      "
    >
      <template v-if="canEditData" #actions>
        <button type="button" class="btn btn-secondary" :disabled="exporting" @click="exportData">
          {{ exporting ? 'Menyiapkan file...' : 'Unduh data siswa' }}
        </button>
        <button type="button" class="btn btn-secondary" @click="openImportModal">Impor dari Excel</button>
        <button type="button" class="btn btn-primary" @click="openModal()">Tambah siswa</button>
      </template>
    </PageHeader>

    <form
      role="search"
      aria-label="Cari dan saring siswa"
      class="panel mb-4 grid grid-cols-1 items-end gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_repeat(3,minmax(0,1fr))]"
      @submit.prevent="applyFilters"
    >
      <div class="sm:col-span-2 lg:col-span-1">
        <label for="student-search" class="field-label">Cari siswa</label>
        <div class="flex gap-2">
          <input
            id="student-search"
            v-model="filters.search"
            type="search"
            class="field-input min-w-0 flex-1"
            placeholder="Nama, NIS, atau NISN"
            autocomplete="off"
          />
          <button type="submit" class="btn btn-secondary shrink-0">Cari</button>
        </div>
      </div>
      <div>
        <label for="filter-class" class="field-label">Kelas</label>
        <select id="filter-class" v-model="filters.class_id" class="field-input" @change="applyFilters">
          <option value="">Semua kelas</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div>
        <label for="filter-major" class="field-label">Jurusan</label>
        <select id="filter-major" v-model="filters.major_id" class="field-input" @change="applyFilters">
          <option value="">Semua jurusan</option>
          <option v-for="m in majors" :key="m.id" :value="m.id">{{ m.name }}</option>
        </select>
      </div>
      <div>
        <label for="filter-status" class="field-label">Status</label>
        <select id="filter-status" v-model="filters.status" class="field-input" @change="applyFilters">
          <option value="">Semua status</option>
          <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>
      <div v-if="hasFilterInput" class="sm:col-span-2 lg:col-span-4">
        <button type="button" class="btn btn-ghost -ml-3" @click="resetFilters">Hapus filter</button>
      </div>
    </form>

    <section class="panel overflow-hidden" aria-label="Daftar siswa">
      <DataState v-if="loading" state="loading" message="Mengambil data siswa." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0 && lastRequestFiltered"
        state="empty"
        title="Tidak ada siswa yang cocok."
        message="Periksa ejaan nama atau nomor induk, atau hapus filter untuk melihat semua siswa."
      >
        <button type="button" class="btn btn-secondary" @click="resetFilters">Hapus filter</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        title="Belum ada data siswa."
        :message="canEditData ? 'Tambahkan siswa satu per satu, atau impor sekaligus dari file Excel.' : 'Administrasi belum memasukkan data siswa.'"
      >
        <template v-if="canEditData">
          <button type="button" class="btn btn-primary" @click="openModal()">Tambah siswa</button>
          <button type="button" class="btn btn-secondary" @click="openImportModal">Impor dari Excel</button>
        </template>
      </DataState>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="table-data">
            <thead>
              <tr>
                <th scope="col" class="num w-12">No</th>
                <th scope="col">Siswa</th>
                <th scope="col">Kelas</th>
                <th scope="col">Tahun masuk</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="sr-only">Aksi</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in items" :key="item.id">
                <td class="num text-tinta-2">{{ rowNumber(index) }}</td>
                <td>
                  <div class="flex items-start gap-3">
                    <div
                      class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-redup text-sm font-semibold text-tinta-2"
                      aria-hidden="true"
                    >
                      <img v-if="item.photo" :src="`/storage/${item.photo}`" alt="" class="size-full object-cover" />
                      <span v-else>{{ item.name?.charAt(0) }}</span>
                    </div>
                    <div class="min-w-0">
                      <span class="block font-semibold">{{ item.name }}</span>
                      <span class="block whitespace-nowrap text-sm text-tinta-2 tabular-nums">
                        NIS {{ item.nis || '-' }} / NISN {{ item.nisn || '-' }}
                      </span>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="block whitespace-nowrap font-medium">{{ item.class_room?.name || '-' }}</span>
                  <span class="block text-sm text-tinta-2">{{ item.major?.name || '-' }}</span>
                </td>
                <td class="tabular-nums">{{ item.entry_year || '-' }}</td>
                <td><StatusTag :status="item.status" /></td>
                <td class="text-right">
                  <div class="flex justify-end gap-1 whitespace-nowrap">
                    <template v-if="canViewFinance">
                      <router-link :to="`/siswa/${item.id}/rekap`" class="btn btn-ghost btn-sm">
                        Rekap<span class="sr-only"> pembayaran {{ item.name }}</span>
                      </router-link>
                      <router-link :to="`/siswa/${item.id}/kartu`" class="btn btn-ghost btn-sm">
                        Kartu<span class="sr-only"> pembayaran {{ item.name }}</span>
                      </router-link>
                    </template>
                    <template v-if="canEditData">
                      <button type="button" class="btn btn-ghost btn-sm" @click="openModal(item)">
                        Ubah<span class="sr-only"> {{ item.name }}</span>
                      </button>
                      <button type="button" class="btn btn-ghost btn-sm !text-galat" @click="deleteItem(item)">
                        Hapus<span class="sr-only"> {{ item.name }}</span>
                      </button>
                    </template>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav
          aria-label="Halaman data siswa"
          class="flex flex-col gap-3 border-t border-garis px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
          <p class="text-sm text-tinta-2 tabular-nums" aria-live="polite">
            Menampilkan {{ pagination.from }} sampai {{ pagination.to }} dari {{ pagination.total }} siswa
          </p>
          <div v-if="pagination.total > pagination.per_page" class="flex flex-wrap gap-2">
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

    <!-- Student form -->
    <BaseModal :open="showModal" size="lg" :title="form.id ? 'Ubah data siswa' : 'Tambah siswa'" @close="closeModal">
      <form id="student-form" class="space-y-6" @submit.prevent="saveItem">
        <p
          v-if="formError"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ formError }}
        </p>

        <fieldset>
          <legend class="mb-3 font-display text-lg font-bold">Identitas dan kelas</legend>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label for="student-name" class="field-label">Nama lengkap</label>
              <input
                id="student-name"
                v-model="form.name"
                type="text"
                required
                maxlength="255"
                class="field-input"
                placeholder="Nama lengkap siswa"
                v-bind="invalidAttrs('name')"
              />
              <p v-if="fieldError('name')" id="student-name-error" class="field-error">{{ fieldError('name') }}</p>
            </div>
            <div>
              <label for="student-nis" class="field-label">NIS</label>
              <input
                id="student-nis"
                v-model="form.nis"
                type="text"
                inputmode="numeric"
                maxlength="50"
                class="field-input"
                placeholder="Nomor induk sekolah"
                v-bind="invalidAttrs('nis')"
              />
              <p v-if="fieldError('nis')" id="student-nis-error" class="field-error">{{ fieldError('nis') }}</p>
            </div>
            <div>
              <label for="student-nisn" class="field-label">NISN</label>
              <input
                id="student-nisn"
                v-model="form.nisn"
                type="text"
                inputmode="numeric"
                maxlength="50"
                class="field-input"
                placeholder="Nomor induk siswa nasional"
                v-bind="invalidAttrs('nisn')"
              />
              <p v-if="fieldError('nisn')" id="student-nisn-error" class="field-error">{{ fieldError('nisn') }}</p>
            </div>
            <div>
              <label for="student-class" class="field-label">Kelas</label>
              <select
                id="student-class"
                v-model="form.class_id"
                required
                class="field-input"
                v-bind="invalidAttrs('class_id')"
                @change="fillMajorFromClass"
              >
                <option value="" disabled>Pilih kelas</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <p v-if="fieldError('class_id')" id="student-class_id-error" class="field-error">{{ fieldError('class_id') }}</p>
              <p v-else-if="!classes.length" class="field-hint">
                Belum ada kelas. <router-link to="/master/classes" class="link">Tambah kelas</router-link>
              </p>
            </div>
            <div>
              <label for="student-major" class="field-label">Jurusan</label>
              <select id="student-major" v-model="form.major_id" class="field-input" v-bind="invalidAttrs('major_id')">
                <option value="">Belum dipilih</option>
                <option v-for="m in majors" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
              <p v-if="fieldError('major_id')" id="student-major_id-error" class="field-error">{{ fieldError('major_id') }}</p>
              <p v-else class="field-hint">Terisi otomatis dari kelas jika masih kosong.</p>
            </div>
            <div>
              <label for="student-status" class="field-label">Status siswa</label>
              <select id="student-status" v-model="form.status" required class="field-input" v-bind="invalidAttrs('status')">
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
              <p v-if="fieldError('status')" id="student-status-error" class="field-error">{{ fieldError('status') }}</p>
            </div>
            <div>
              <label for="student-entry-year" class="field-label">Tahun masuk</label>
              <input
                id="student-entry-year"
                v-model="form.entry_year"
                type="number"
                inputmode="numeric"
                class="field-input"
                placeholder="Empat digit tahun"
                v-bind="invalidAttrs('entry_year')"
              />
              <p v-if="fieldError('entry_year')" id="student-entry_year-error" class="field-error">
                {{ fieldError('entry_year') }}
              </p>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend class="mb-3 font-display text-lg font-bold">Data pribadi</legend>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="student-gender" class="field-label">Jenis kelamin</label>
              <select id="student-gender" v-model="form.gender" class="field-input" v-bind="invalidAttrs('gender')">
                <option value="">Belum dipilih</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
              </select>
              <p v-if="fieldError('gender')" id="student-gender-error" class="field-error">{{ fieldError('gender') }}</p>
            </div>
            <div>
              <label for="student-phone" class="field-label">Telepon siswa</label>
              <input
                id="student-phone"
                v-model="form.phone"
                type="tel"
                maxlength="50"
                class="field-input"
                placeholder="Nomor HP atau telepon"
                v-bind="invalidAttrs('phone')"
              />
              <p v-if="fieldError('phone')" id="student-phone-error" class="field-error">{{ fieldError('phone') }}</p>
            </div>
            <div>
              <label for="student-birth-place" class="field-label">Tempat lahir</label>
              <input
                id="student-birth-place"
                v-model="form.birth_place"
                type="text"
                maxlength="255"
                class="field-input"
                placeholder="Kota atau kabupaten"
                v-bind="invalidAttrs('birth_place')"
              />
              <p v-if="fieldError('birth_place')" id="student-birth_place-error" class="field-error">
                {{ fieldError('birth_place') }}
              </p>
            </div>
            <div>
              <label for="student-birth-date" class="field-label">Tanggal lahir</label>
              <input
                id="student-birth-date"
                v-model="form.birth_date"
                type="date"
                class="field-input"
                v-bind="invalidAttrs('birth_date')"
              />
              <p v-if="fieldError('birth_date')" id="student-birth_date-error" class="field-error">
                {{ fieldError('birth_date') }}
              </p>
            </div>
            <div class="sm:col-span-2">
              <label for="student-address" class="field-label">Alamat</label>
              <textarea
                id="student-address"
                v-model="form.address"
                rows="2"
                class="field-input"
                placeholder="Alamat tempat tinggal siswa"
                v-bind="invalidAttrs('address')"
              ></textarea>
              <p v-if="fieldError('address')" id="student-address-error" class="field-error">{{ fieldError('address') }}</p>
            </div>
            <div class="sm:col-span-2">
              <label for="student-photo" class="field-label">Foto siswa</label>
              <input
                id="student-photo"
                type="file"
                accept="image/*"
                class="field-input py-1.5 file:mr-3 file:rounded-[var(--radius-kontrol)] file:border-0 file:bg-bata-muda file:px-3 file:py-2 file:font-semibold file:text-bata-tua"
                :aria-invalid="fieldError('photo') ? 'true' : 'false'"
                :aria-describedby="fieldError('photo') ? 'student-photo-error' : 'student-photo-hint'"
                @change="onFileChange"
              />
              <p v-if="fieldError('photo')" id="student-photo-error" class="field-error">{{ fieldError('photo') }}</p>
              <p v-else id="student-photo-hint" class="field-hint">
                Berkas gambar, paling besar 2 MB.{{ form.id ? ' Biarkan kosong jika foto tidak diganti.' : '' }}
              </p>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend class="mb-3 font-display text-lg font-bold">Orang tua atau wali</legend>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label for="guardian-name" class="field-label">Nama wali</label>
              <input
                id="guardian-name"
                v-model="form.guardian_name"
                type="text"
                maxlength="255"
                class="field-input"
                placeholder="Nama orang tua atau wali"
                v-bind="invalidAttrs('guardian_name', 'guardian-name')"
              />
              <p v-if="fieldError('guardian_name')" id="guardian-name-error" class="field-error">
                {{ fieldError('guardian_name') }}
              </p>
            </div>
            <div>
              <label for="guardian-phone" class="field-label">Telepon wali</label>
              <input
                id="guardian-phone"
                v-model="form.guardian_phone"
                type="tel"
                maxlength="50"
                class="field-input"
                placeholder="Nomor HP orang tua atau wali"
                v-bind="invalidAttrs('guardian_phone', 'guardian-phone')"
              />
              <p v-if="fieldError('guardian_phone')" id="guardian-phone-error" class="field-error">
                {{ fieldError('guardian_phone') }}
              </p>
            </div>
            <div>
              <label for="guardian-relation" class="field-label">Hubungan dengan siswa</label>
              <input
                id="guardian-relation"
                v-model="form.guardian_relation"
                type="text"
                maxlength="50"
                class="field-input"
                placeholder="Ayah, ibu, atau wali"
                v-bind="invalidAttrs('guardian_relation', 'guardian-relation')"
              />
              <p v-if="fieldError('guardian_relation')" id="guardian-relation-error" class="field-error">
                {{ fieldError('guardian_relation') }}
              </p>
            </div>
          </div>
        </fieldset>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="closeModal">Batal</button>
        <button type="submit" form="student-form" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan siswa' }}
        </button>
      </template>
    </BaseModal>

    <!-- Import from Excel: upload, preview, then confirm -->
    <BaseModal
      :open="showImportModal"
      size="xl"
      title="Impor data siswa dari Excel"
      :description="importPreviewData ? 'Langkah 2 dari 2: periksa hasil pembacaan file.' : 'Langkah 1 dari 2: pilih file, sistem akan memeriksanya dulu.'"
      @close="closeImportModal"
    >
      <form v-if="!importPreviewData" id="import-upload-form" class="space-y-4" @submit.prevent="uploadImport">
        <div class="rounded-[var(--radius-kontrol)] bg-krem px-4 py-3 text-sm">
          <p class="font-semibold">Ketentuan file</p>
          <ul class="mt-1 list-disc space-y-1 pl-5 text-tinta-2">
            <li>Format .xlsx, .xls, atau .csv, paling besar 5 MB. Baris pertama berisi nama kolom.</li>
            <li>Kolom wajib: <code class="font-semibold text-tinta">nama_lengkap</code> dan <code class="font-semibold text-tinta">kelas</code>.</li>
            <li>
              Kolom lain yang dibaca: nis, nisn, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, telepon, tahun_masuk,
              jurusan, status, nama_wali, telepon_wali, hubungan_wali.
            </li>
            <li>Isi kolom kelas dan jurusan harus sama persis dengan nama di menu Kelas dan Jurusan.</li>
          </ul>
        </div>
        <div>
          <label for="import-file" class="field-label">File Excel siswa</label>
          <input
            id="import-file"
            type="file"
            accept=".xlsx,.xls,.csv"
            required
            class="field-input py-1.5 file:mr-3 file:rounded-[var(--radius-kontrol)] file:border-0 file:bg-bata-muda file:px-3 file:py-2 file:font-semibold file:text-bata-tua"
            :aria-invalid="importError ? 'true' : 'false'"
            :aria-describedby="importError ? 'import-file-error' : undefined"
            @change="onImportFileChange"
          />
          <p v-if="importError" id="import-file-error" class="field-error" role="alert">{{ importError }}</p>
        </div>
      </form>

      <div v-else class="space-y-4">
        <div>
          <p class="font-semibold">
            {{ importPreviewData.length }} baris terbaca: {{ importValidCount }} siap diimpor,
            {{ importInvalidCount }} dilewati.
          </p>
          <p v-if="importHasErrors" class="mt-1 text-sm text-tinta-2">
            Baris yang dilewati tidak ikut disimpan. Perbaiki di file Excel lalu impor ulang baris tersebut, atau
            tambahkan manual.
          </p>
          <p v-if="importValidCount === 0" class="field-error" role="alert">
            Tidak ada baris yang bisa diimpor. Perbaiki file sesuai keterangan di tabel, lalu pilih file lagi.
          </p>
        </div>

        <div class="overflow-x-auto rounded-[var(--radius-kontrol)] border border-garis">
          <table class="table-data">
            <thead>
              <tr>
                <th scope="col" class="num w-12">No</th>
                <th scope="col">Hasil</th>
                <th scope="col">Nama</th>
                <th scope="col">NIS / NISN</th>
                <th scope="col">Kelas</th>
                <th scope="col">Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in importPreviewData" :key="idx">
                <td class="num text-tinta-2">{{ idx + 1 }}</td>
                <td>
                  <StatusTag v-if="row.is_valid" status="APPROVED" label="Siap diimpor" />
                  <StatusTag v-else status="REJECTED" label="Dilewati" />
                </td>
                <td>{{ row.name || '-' }}</td>
                <td class="whitespace-nowrap tabular-nums">{{ row.nis || '-' }} / {{ row.nisn || '-' }}</td>
                <td class="whitespace-nowrap">{{ row.class_name || '-' }}</td>
                <td>
                  <ul v-if="row.errors?.length" class="space-y-0.5 text-sm font-medium text-galat">
                    <li v-for="err in row.errors" :key="err">{{ err }}</li>
                  </ul>
                  <span v-else class="text-sm text-tinta-2">Tidak ada masalah</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <template #footer>
        <template v-if="!importPreviewData">
          <button type="button" class="btn btn-secondary" @click="closeImportModal">Batal</button>
          <button type="submit" form="import-upload-form" class="btn btn-primary" :disabled="!importFile || importing">
            {{ importing ? 'Memeriksa file...' : 'Periksa file' }}
          </button>
        </template>
        <template v-else>
          <button type="button" class="btn btn-secondary" :disabled="importing" @click="chooseAnotherImportFile">
            Pilih file lain
          </button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="importing || importValidCount === 0"
            @click="confirmImport"
          >
            {{ importing ? 'Mengimpor...' : `Impor ${importValidCount} siswa` }}
          </button>
        </template>
      </template>
    </BaseModal>
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotice } from '@/composables/useNotice'
import { errorMessage } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const authStore = useAuthStore()
const notice = useNotice()
const canEditData = computed(() => authStore.hasRole('Administrasi'))
const canViewFinance = computed(() => authStore.hasRole('Bendahara') || authStore.hasRole('Kepala Sekolah'))

const statusOptions = [
  { value: 'AKTIF', label: 'Aktif' },
  { value: 'LULUS', label: 'Lulus' },
  { value: 'PINDAH', label: 'Pindah' },
  { value: 'KELUAR', label: 'Keluar' },
]

const items = ref([])
const classes = ref([])
const majors = ref([])
const loading = ref(true)
const loadError = ref('')
const lastRequestFiltered = ref(false)
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref({})

const emptyFilters = () => ({ search: '', class_id: '', major_id: '', status: '' })
const filters = ref(emptyFilters())

const hasFilterInput = computed(() => Object.values(filters.value).some((value) => value !== '' && value !== null))

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

const emptyForm = () => ({
  id: null,
  nis: '',
  nisn: '',
  name: '',
  gender: '',
  birth_place: '',
  birth_date: '',
  address: '',
  phone: '',
  entry_year: '',
  class_id: '',
  major_id: '',
  status: 'AKTIF',
  photo: null,
  guardian_name: '',
  guardian_phone: '',
  guardian_relation: '',
})
const form = ref(emptyForm())

const photoFile = ref(null)

const fieldError = (key) => fieldErrors.value[key]?.[0] || ''

/** aria-invalid + aria-describedby for a field whose error <p> id is `${idBase}-error`. */
const invalidAttrs = (key, idBase = `student-${key}`) => ({
  'aria-invalid': fieldError(key) ? 'true' : 'false',
  'aria-describedby': fieldError(key) ? `${idBase}-error` : undefined,
})

const rowNumber = (index) => (pagination.value.current_page - 1) * pagination.value.per_page + index + 1

const onFileChange = (e) => {
  photoFile.value = e.target.files[0]
}

const fillMajorFromClass = () => {
  if (form.value.major_id) return
  const selectedClass = classes.value.find((c) => c.id === form.value.class_id)
  if (selectedClass?.major_id) form.value.major_id = selectedClass.major_id
}

const fetchMasterData = async () => {
  try {
    const [cRes, mRes] = await Promise.all([axios.get('/api/v1/classes'), axios.get('/api/v1/majors')])
    classes.value = cRes.data.data
    majors.value = mRes.data.data
  } catch (error) {
    notice.error(errorMessage(error, 'Daftar kelas dan jurusan gagal dimuat. Filter dan formulir siswa belum lengkap; muat ulang halaman.'))
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
    const response = await axios.get('/api/v1/students', { params })
    items.value = response.data.data
    lastRequestFiltered.value = Object.values(filters.value).some((value) => value !== '' && value !== null)

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

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return
  pagination.value.current_page = page
  fetchData()
}

const applyFilters = () => {
  pagination.value.current_page = 1
  fetchData()
}

const resetFilters = () => {
  filters.value = emptyFilters()
  pagination.value.current_page = 1
  fetchData()
}

const openModal = (item = null) => {
  formError.value = ''
  fieldErrors.value = {}
  photoFile.value = null
  form.value = item
    ? {
        id: item.id,
        nis: item.nis || '',
        nisn: item.nisn || '',
        name: item.name || '',
        gender: item.gender || '',
        birth_place: item.birth_place || '',
        birth_date: item.birth_date ? item.birth_date.substring(0, 10) : '',
        address: item.address || '',
        phone: item.phone || '',
        entry_year: item.entry_year || '',
        class_id: item.class_id || '',
        major_id: item.major_id || '',
        status: item.status || 'AKTIF',
        photo: null,
        guardian_name: item.guardian?.name || '',
        guardian_phone: item.guardian?.phone || '',
        guardian_relation: item.guardian?.relation || '',
      }
    : emptyForm()
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}

  const formData = new FormData()
  for (const key in form.value) {
    if (form.value[key] !== null && form.value[key] !== undefined) {
      formData.append(key, form.value[key])
    }
  }

  if (photoFile.value) {
    formData.append('photo', photoFile.value)
  }

  try {
    if (form.value.id) {
      // Laravel reads multipart bodies only on POST, so the update is sent as POST with _method=PUT.
      formData.append('_method', 'PUT')
      await axios.post(`/api/v1/students/${form.value.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    } else {
      await axios.post('/api/v1/students', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }
    notice.success(`Data ${form.value.name} tersimpan.`)
    closeModal()
    fetchData()
  } catch (error) {
    fieldErrors.value = error.response?.data?.errors || {}
    formError.value = errorMessage(error, 'Data siswa gagal disimpan.')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (item) => {
  const confirmed = await notice.confirm({
    title: 'Hapus data siswa?',
    message: `${item.name} akan dihapus dari daftar siswa.`,
    confirmLabel: 'Hapus siswa',
    danger: true,
  })
  if (!confirmed) return

  try {
    await axios.delete(`/api/v1/students/${item.id}`)
    notice.success(`${item.name} dihapus.`)
    if (items.value.length === 1 && pagination.value.current_page > 1) {
      pagination.value.current_page -= 1
    }
    fetchData()
  } catch (error) {
    notice.error(errorMessage(error, 'Data siswa gagal dihapus.'))
  }
}

// Export: the API needs the bearer token, so a plain link cannot download the file.
const exporting = ref(false)
const exportData = async () => {
  exporting.value = true
  try {
    const res = await axios.get('/api/v1/students/export', { params: filters.value, responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const link = document.createElement('a')
    link.href = url
    link.download = 'data-siswa.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (error) {
    notice.error('Data siswa gagal diunduh. Coba lagi.')
  } finally {
    exporting.value = false
  }
}

// Import
const showImportModal = ref(false)
const importFile = ref(null)
const importPreviewData = ref(null)
const importHasErrors = ref(false)
const importing = ref(false)
const importError = ref('')

const importValidCount = computed(() => (importPreviewData.value || []).filter((row) => row.is_valid).length)
const importInvalidCount = computed(() => (importPreviewData.value || []).length - importValidCount.value)

const openImportModal = () => {
  importFile.value = null
  importPreviewData.value = null
  importHasErrors.value = false
  importError.value = ''
  showImportModal.value = true
}

const closeImportModal = () => {
  showImportModal.value = false
}

const chooseAnotherImportFile = () => {
  importFile.value = null
  importPreviewData.value = null
  importHasErrors.value = false
  importError.value = ''
}

const onImportFileChange = (e) => {
  importFile.value = e.target.files[0]
  importError.value = ''
}

const uploadImport = async () => {
  if (!importFile.value) return
  importing.value = true
  importError.value = ''

  const fd = new FormData()
  fd.append('file', importFile.value)

  try {
    const res = await axios.post('/api/v1/students/import-preview', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importPreviewData.value = res.data.data
    importHasErrors.value = res.data.has_errors
  } catch (error) {
    importError.value = errorMessage(error, 'File gagal dibaca. Pastikan formatnya .xlsx, .xls, atau .csv.')
  } finally {
    importing.value = false
  }
}

const confirmImport = async () => {
  importing.value = true
  const importedCount = importValidCount.value
  try {
    await axios.post('/api/v1/students/import', { data: importPreviewData.value })
    closeImportModal()
    notice.success(`${importedCount} siswa berhasil diimpor.`)
    fetchData()
  } catch (error) {
    notice.error(errorMessage(error, 'Data siswa gagal diimpor. Tidak ada baris yang disimpan.'))
  } finally {
    importing.value = false
  }
}

onMounted(() => {
  fetchMasterData()
  fetchData()
})
</script>
