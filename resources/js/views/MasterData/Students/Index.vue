<template>
  <admin-layout>
    <div class="space-y-5 sm:space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Data Siswa</h2>
        <div class="flex gap-2" v-if="isBendahara">
          <button @click="openImportModal" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
            Import Excel
          </button>
          <button @click="exportData" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
            Export Excel
          </button>
          <button @click="openModal()" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white transition bg-brand-500 rounded-lg hover:bg-brand-600">
            Tambah Siswa
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="p-4 bg-white rounded-xl border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Pencarian (Nama, NIS, NISN)</label>
          <input v-model="filters.search" @keyup.enter="fetchData" type="text" placeholder="Ketik lalu Enter..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
        </div>
        <div class="w-48">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Kelas</label>
          <select v-model="filters.class_id" @change="fetchData" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            <option value="">Semua Kelas</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="w-48">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Jurusan</label>
          <select v-model="filters.major_id" @change="fetchData" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            <option value="">Semua Jurusan</option>
            <option v-for="m in majors" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
        </div>
        <div class="w-40">
          <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
          <select v-model="filters.status" @change="fetchData" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
            <option value="">Semua Status</option>
            <option value="AKTIF">AKTIF</option>
            <option value="LULUS">LULUS</option>
            <option value="PINDAH">PINDAH</option>
            <option value="KELUAR">KELUAR</option>
          </select>
        </div>
        <div>
          <button @click="resetFilters" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">Reset</button>
        </div>
      </div>

      <!-- Data Table -->
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="max-w-full overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <th class="px-5 py-3 text-left w-16">No</th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Siswa</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Kelas / Jurusan</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Tahun Masuk</p></th>
                <th class="px-5 py-3 text-left"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p></th>
                <th v-if="isBendahara" class="px-5 py-3 text-right"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Aksi</p></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="loading">
                <td colspan="6" class="px-5 py-4 text-center">Loading...</td>
              </tr>
              <tr v-else-if="items.length === 0">
                <td colspan="6" class="px-5 py-4 text-center">Tidak ada data.</td>
              </tr>
              <tr v-for="(item, index) in items" :key="item.id" class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="px-5 py-4 text-sm text-gray-500">{{ (pagination.current_page - 1) * pagination.per_page + index + 1 }}</td>
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700 flex-shrink-0">
                      <img v-if="item.photo" :src="`/storage/${item.photo}`" class="w-full h-full object-cover" />
                      <div v-else class="w-full h-full flex items-center justify-center text-gray-500">
                        {{ item.name.charAt(0) }}
                      </div>
                    </div>
                    <div>
                      <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ item.name }}</span>
                      <span class="block text-gray-500 text-xs dark:text-gray-400">NIS: {{ item.nis || '-' }} | NISN: {{ item.nisn || '-' }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span class="block text-gray-800 text-theme-sm dark:text-white/90 font-medium">{{ item.class_room?.name || '-' }}</span>
                  <span class="block text-gray-500 text-xs dark:text-gray-400">{{ item.major?.name || '-' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="block text-gray-500 text-theme-sm dark:text-gray-400">{{ item.entry_year || '-' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span :class="[ 'rounded-full px-2 py-0.5 text-theme-xs font-medium', getStatusClass(item.status) ]">
                    {{ item.status }}
                  </span>
                </td>
                <td v-if="isBendahara" class="px-5 py-4 text-right">
                  <button @click="openModal(item)" class="text-brand-500 hover:text-brand-600 mr-3 text-sm">Edit</button>
                  <button @click="deleteItem(item.id)" class="text-error-500 hover:text-error-600 text-sm">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <div v-if="pagination.total > pagination.per_page" class="flex items-center justify-between px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
          <span class="text-sm text-gray-500 dark:text-gray-400">
            Menampilkan {{ pagination.from }} - {{ pagination.to }} dari {{ pagination.total }} data
          </span>
          <div class="flex gap-2">
            <button 
              @click="changePage(pagination.current_page - 1)" 
              :disabled="!pagination.prev_page_url"
              class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >Prev</button>
            <button 
              @click="changePage(pagination.current_page + 1)" 
              :disabled="!pagination.next_page_url"
              class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >Next</button>
          </div>
        </div>
      </div>

      <!-- Form Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex justify-center p-4 bg-gray-900/50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-4xl dark:bg-gray-800 my-auto h-max">
          <form @submit.prevent="saveItem">
            <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
              <h3 class="font-medium text-gray-800 dark:text-white/90">
                {{ form.id ? 'Edit Siswa' : 'Tambah Siswa' }}
              </h3>
              <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600">×</button>
            </div>
            
            <div class="p-5 overflow-y-auto max-h-[70vh]">
              <div v-if="errorMessage" class="p-3 mb-4 text-sm text-error-700 bg-error-50 rounded-lg">
                {{ errorMessage }}
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Kolom 1 -->
                <div class="space-y-4">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 border-b pb-2">Data Pribadi</h4>
                  
                  <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Nama Lengkap *</label>
                    <input v-model="form.name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                  </div>
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">NIS</label>
                      <input v-model="form.nis" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                    </div>
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">NISN</label>
                      <input v-model="form.nisn" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tempat Lahir</label>
                      <input v-model="form.birth_place" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                    </div>
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Lahir</label>
                      <input v-model="form.birth_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                    </div>
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Kelamin</label>
                      <select v-model="form.gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="">Pilih...</option>
                        <option value="L">Laki-Laki</option>
                        <option value="P">Perempuan</option>
                      </select>
                    </div>
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Telepon</label>
                      <input v-model="form.phone" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                    </div>
                  </div>

                  <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Alamat</label>
                    <textarea v-model="form.address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white"></textarea>
                  </div>
                  
                  <div>
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Foto</label>
                    <input type="file" @change="onFileChange" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-gray-700 dark:file:text-gray-300" />
                  </div>
                </div>
                
                <!-- Kolom 2 -->
                <div class="space-y-4">
                  <h4 class="font-semibold text-gray-700 dark:text-gray-300 border-b pb-2">Akademik & Wali</h4>
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Kelas *</label>
                      <select v-model="form.class_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="" disabled>Pilih Kelas</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Jurusan</label>
                      <select v-model="form.major_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="">Pilih Jurusan</option>
                        <option v-for="m in majors" :key="m.id" :value="m.id">{{ m.name }}</option>
                      </select>
                    </div>
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tahun Masuk</label>
                      <input v-model="form.entry_year" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                    </div>
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Status *</label>
                      <select v-model="form.status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                        <option value="AKTIF">AKTIF</option>
                        <option value="LULUS">LULUS</option>
                        <option value="PINDAH">PINDAH</option>
                        <option value="KELUAR">KELUAR</option>
                      </select>
                    </div>
                  </div>

                  <div class="pt-2">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Nama Wali</label>
                    <input v-model="form.guardian_name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Telepon Wali</label>
                      <input v-model="form.guardian_phone" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                    </div>
                    <div>
                      <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Hubungan Wali</label>
                      <input v-model="form.guardian_relation" type="text" placeholder="Ayah/Ibu/Wali" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                    </div>
                  </div>

                </div>
              </div>
            </div>
            
            <div class="flex justify-end p-5 border-t border-gray-200 dark:border-gray-700 gap-3">
              <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">Batal</button>
              <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-50">
                {{ saving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Import Modal -->
      <div v-if="showImportModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/50">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-5xl dark:bg-gray-800 h-[80vh] flex flex-col">
          <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="font-medium text-gray-800 dark:text-white/90">Import Data Siswa</h3>
            <button type="button" @click="closeImportModal" class="text-gray-400 hover:text-gray-600">×</button>
          </div>
          
          <div class="p-5 flex-1 overflow-y-auto">
            <div v-if="!importPreviewData" class="space-y-4">
              <div class="p-4 bg-blue-50 text-blue-800 rounded-lg text-sm">
                <strong>Panduan Import:</strong><br/>
                1. Gunakan file Excel (.xlsx)<br/>
                2. Kolom wajib: <code>nama_lengkap</code>, <code>kelas</code><br/>
                3. Pastikan nama <code>kelas</code> dan <code>jurusan</code> sama persis dengan yang ada di Master Data.
              </div>
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">File Excel</label>
                <input type="file" @change="onImportFileChange" accept=".xlsx,.xls,.csv" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
              </div>
              <button @click="uploadImport" :disabled="!importFile || importing" class="px-4 py-2 mt-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-50">
                {{ importing ? 'Memproses Preview...' : 'Upload & Preview' }}
              </button>
              <div v-if="importError" class="mt-2 text-sm text-error-600">{{ importError }}</div>
            </div>

            <div v-else class="space-y-4">
              <div class="flex items-center justify-between">
                <h4 class="font-medium text-gray-800 dark:text-white/90">Preview Data ({{ importPreviewData.length }} baris)</h4>
                <span v-if="importHasErrors" class="text-error-600 text-sm font-medium">Beberapa data tidak valid dan akan dilewati!</span>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                  <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">
                    <tr>
                      <th class="px-3 py-2">Status</th>
                      <th class="px-3 py-2">Nama</th>
                      <th class="px-3 py-2">NIS/NISN</th>
                      <th class="px-3 py-2">Kelas</th>
                      <th class="px-3 py-2">Keterangan</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="(row, idx) in importPreviewData" :key="idx" :class="row.is_valid ? 'bg-white dark:bg-gray-800' : 'bg-error-50 dark:bg-error-900/20'">
                      <td class="px-3 py-2 text-center">
                        <span v-if="row.is_valid" class="text-success-600 font-bold">✓</span>
                        <span v-else class="text-error-600 font-bold">✗</span>
                      </td>
                      <td class="px-3 py-2">{{ row.name }}</td>
                      <td class="px-3 py-2">{{ row.nis || '-' }} / {{ row.nisn || '-' }}</td>
                      <td class="px-3 py-2">{{ row.class_name }}</td>
                      <td class="px-3 py-2 text-error-600 text-xs">
                        <div v-for="err in row.errors" :key="err">• {{ err }}</div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <div class="flex justify-end p-5 border-t border-gray-200 dark:border-gray-700 gap-3">
            <button type="button" @click="closeImportModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
            <button v-if="importPreviewData" type="button" @click="confirmImport" :disabled="importing" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-50">
              {{ importing ? 'Mengimpor...' : 'Konfirmasi Import' }}
            </button>
          </div>
        </div>
      </div>
      <!-- End Import Modal -->

    </div>
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const authStore = useAuthStore()
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

const items = ref([])
const classes = ref([])
const majors = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const filters = ref({
  search: '',
  class_id: '',
  major_id: '',
  status: ''
})

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
  prev_page_url: null,
  next_page_url: null
})

const form = ref({
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
  guardian_relation: ''
})

const photoFile = ref(null)

const onFileChange = (e) => {
  photoFile.value = e.target.files[0]
}

const fetchMasterData = async () => {
  try {
    const [cRes, mRes] = await Promise.all([
      axios.get('/api/v1/classes'),
      axios.get('/api/v1/majors')
    ])
    classes.value = cRes.data.data
    majors.value = mRes.data.data
  } catch (e) {
    console.error(e)
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const params = {
      ...filters.value,
      page: pagination.value.current_page
    }
    const response = await axios.get('/api/v1/students', { params })
    items.value = response.data.data
    
    // update pagination state
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      per_page: response.data.per_page,
      total: response.data.total,
      from: response.data.from,
      to: response.data.to,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url
    }
  } catch (error) {
    console.error('Failed to fetch data', error)
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return
  pagination.value.current_page = page
  fetchData()
}

const resetFilters = () => {
  filters.value = { search: '', class_id: '', major_id: '', status: '' }
  pagination.value.current_page = 1
  fetchData()
}

const openModal = (item = null) => {
  errorMessage.value = ''
  photoFile.value = null
  if (item) {
    form.value = { 
      id: item.id,
      nis: item.nis || '',
      nisn: item.nisn || '',
      name: item.name || '',
      gender: item.gender || '',
      birth_place: item.birth_place || '',
      birth_date: item.birth_date ? item.birth_date.substring(0,10) : '',
      address: item.address || '',
      phone: item.phone || '',
      entry_year: item.entry_year || '',
      class_id: item.class_id || '',
      major_id: item.major_id || '',
      status: item.status || 'AKTIF',
      photo: null,
      guardian_name: item.guardian?.name || '',
      guardian_phone: item.guardian?.phone || '',
      guardian_relation: item.guardian?.relation || ''
    }
  } else {
    form.value = { 
      id: null, nis: '', nisn: '', name: '', gender: '', birth_place: '', birth_date: '',
      address: '', phone: '', entry_year: '', class_id: '', major_id: '', status: 'AKTIF',
      photo: null, guardian_name: '', guardian_phone: '', guardian_relation: ''
    }
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  saving.value = true
  errorMessage.value = ''
  
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
      formData.append('_method', 'PUT') // Axios workaround for multipart/form-data PUT
      await axios.post(`/api/v1/students/${form.value.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    } else {
      await axios.post('/api/v1/students', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    }
    closeModal()
    fetchData()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan saat menyimpan'
  } finally {
    saving.value = false
  }
}

const deleteItem = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus data siswa ini?')) return
  
  try {
    await axios.delete(`/api/v1/students/${id}`)
    fetchData()
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal menghapus data')
  }
}

const getStatusClass = (status) => {
  switch(status) {
    case 'AKTIF': return 'bg-success-50 text-success-700'
    case 'LULUS': return 'bg-brand-50 text-brand-700'
    case 'PINDAH': return 'bg-warning-50 text-warning-700'
    case 'KELUAR': return 'bg-error-50 text-error-700'
    default: return 'bg-gray-100 text-gray-600'
  }
}

// Export
const exportData = () => {
  const query = new URLSearchParams(filters.value).toString()
  window.location.href = `/api/v1/students/export?${query}`
}

// Import
const showImportModal = ref(false)
const importFile = ref(null)
const importPreviewData = ref(null)
const importHasErrors = ref(false)
const importing = ref(false)
const importError = ref('')

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

const onImportFileChange = (e) => {
  importFile.value = e.target.files[0]
}

const uploadImport = async () => {
  if (!importFile.value) return
  importing.value = true
  importError.value = ''
  
  const fd = new FormData()
  fd.append('file', importFile.value)
  
  try {
    const res = await axios.post('/api/v1/students/import-preview', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    importPreviewData.value = res.data.data
    importHasErrors.value = res.data.has_errors
  } catch (error) {
    importError.value = error.response?.data?.message || 'Gagal memproses file'
  } finally {
    importing.value = false
  }
}

const confirmImport = async () => {
  importing.value = true
  try {
    await axios.post('/api/v1/students/import', { data: importPreviewData.value })
    closeImportModal()
    fetchData()
    alert('Data berhasil diimpor!')
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal mengimpor data')
  } finally {
    importing.value = false
  }
}

onMounted(() => {
  fetchMasterData()
  fetchData()
})
</script>
