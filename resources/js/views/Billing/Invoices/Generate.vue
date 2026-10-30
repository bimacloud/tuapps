<template>
  <admin-layout>
    <div class="space-y-5 sm:space-y-6 max-w-4xl mx-auto">
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">Generate Tagihan Siswa</h2>
        <router-link to="/invoices" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
          Lihat Daftar Tagihan
        </router-link>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 dark:bg-gray-800 dark:border-gray-700 overflow-hidden shadow-sm">
        <div class="p-6">
          <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300 rounded-lg text-sm">
            <p class="font-semibold mb-1">Panduan Generate Tagihan:</p>
            <ul class="list-disc ml-5 space-y-1">
              <li>Pilih <strong>Tahun Pelajaran</strong> dan <strong>Kelas</strong> target tagihan.</li>
              <li>Pilih <strong>Jenis Pembayaran</strong> (Misal: SPP, UTS).</li>
              <li>Tentukan <strong>Periode</strong> (Misal: Juli, Semester 1, 2024/2025).</li>
              <li>Sistem akan secara otomatis mencari tarif (Rate) yang aktif dan membuatkan Tagihan untuk semua siswa aktif di kelas tersebut.</li>
              <li>Sistem <strong>mencegah duplikasi tagihan</strong> (siswa yang sama tidak akan menerima tagihan ganda untuk periode yang sama).</li>
            </ul>
          </div>

          <form @submit.prevent="generateInvoices" class="space-y-5">
            <div v-if="successMessage" class="p-4 text-sm text-success-800 bg-success-50 rounded-lg dark:bg-success-900/20 dark:text-success-400">
              <span class="font-bold">Berhasil!</span> {{ successMessage }}
            </div>
            
            <div v-if="errorMessage" class="p-4 text-sm text-error-800 bg-error-50 rounded-lg dark:bg-error-900/20 dark:text-error-400">
              <span class="font-bold">Gagal:</span> {{ errorMessage }}
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tahun Pelajaran *</label>
                <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                  <option value="" disabled>Pilih Tahun Pelajaran</option>
                  <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">{{ ay.name }}</option>
                </select>
              </div>

              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Target Kelas *</label>
                <select v-model="form.class_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                  <option value="" disabled>Pilih Kelas</option>
                  <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>

              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Pembayaran *</label>
                <select v-model="form.payment_type_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                  <option value="" disabled>Pilih Jenis Pembayaran</option>
                  <option v-for="pt in activePaymentTypes" :key="pt.id" :value="pt.id">{{ pt.code }} - {{ pt.name }} ({{ pt.type }})</option>
                </select>
              </div>

              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Periode (Misal: Juli, Gasal) *</label>
                <input v-if="selectedPaymentType && selectedPaymentType.type !== 'BULANAN'" v-model="form.period" type="text" required placeholder="Contoh: Semester 1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
                <select v-else v-model="form.period" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                  <option value="" disabled>Pilih Bulan</option>
                  <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
                </select>
              </div>

              <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tenggat Waktu / Jatuh Tempo (Opsional)</label>
                <input v-model="form.due_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:text-white" />
              </div>
            </div>

            <div class="pt-4 mt-6 border-t border-gray-200 dark:border-gray-700 flex justify-end">
              <button type="submit" :disabled="generating" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-medium text-white transition bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-50">
                <svg v-if="generating" class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                {{ generating ? 'Memproses...' : 'Generate Tagihan Sekarang' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import AdminLayout from '@/components/layout/AdminLayout.vue'

const academicYears = ref([])
const classes = ref([])
const paymentTypes = ref([])

const generating = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const months = ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni']

const form = ref({
  academic_year_id: '',
  class_id: '',
  payment_type_id: '',
  period: '',
  due_date: ''
})

const activePaymentTypes = computed(() => paymentTypes.value.filter(pt => pt.status))

const selectedPaymentType = computed(() => {
  if (!form.value.payment_type_id) return null;
  return activePaymentTypes.value.find(pt => pt.id === form.value.payment_type_id)
})

const fetchDependencies = async () => {
  try {
    const [ayRes, clRes, ptRes] = await Promise.all([
      axios.get('/api/v1/academic-years'),
      axios.get('/api/v1/classes'),
      axios.get('/api/v1/payment-types')
    ])
    academicYears.value = ayRes.data.data
    classes.value = clRes.data.data
    paymentTypes.value = ptRes.data.data
    
    const activeYear = academicYears.value.find(y => y.is_active)
    if (activeYear) {
      form.value.academic_year_id = activeYear.id
    }
  } catch (error) {
    console.error(error)
  }
}

const generateInvoices = async () => {
  if (!confirm('Apakah Anda yakin ingin men-generate tagihan untuk kelas ini? Proses ini akan membuat tagihan massal.')) return
  
  generating.value = true
  successMessage.value = ''
  errorMessage.value = ''
  
  try {
    const response = await axios.post('/api/v1/invoices/generate', form.value)
    successMessage.value = response.data.message
    
    // reset form period untuk mempermudah generate bulan berikutnya
    form.value.period = ''
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Terjadi kesalahan saat generate tagihan'
  } finally {
    generating.value = false
  }
}

onMounted(() => {
  fetchDependencies()
})
</script>
