<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const payments = ref<any[]>([])
const loading = ref(true)

// Filters
const search = ref('')
const selectedDate = ref('')
const selectedMethod = ref('')
const paymentMethods = ref<any[]>([])

// Pagination
const currentPage = ref(1)
const lastPage = ref(1)

const getPaymentMethods = async () => {
  try {
    const res = await axios.get('/api/v1/payment-methods')
    paymentMethods.value = res.data.data
  } catch (error) {
    console.error('Error fetching payment methods', error)
  }
}

// Cancel Modal State
const showCancelModal = ref(false)
const selectedPaymentId = ref<number | null>(null)
const cancelReason = ref('')
const isSubmittingCancel = ref(false)

const openCancelModal = (paymentId: number) => {
  selectedPaymentId.value = paymentId
  cancelReason.value = ''
  showCancelModal.value = true
}

const submitCancel = async () => {
  if (!selectedPaymentId.value || !cancelReason.value) return
  
  isSubmittingCancel.value = true
  try {
    const res = await axios.post(`/api/v1/payments/${selectedPaymentId.value}/cancel-request`, {
      reason: cancelReason.value
    })
    alert('Permintaan pembatalan berhasil diajukan dan sedang menunggu persetujuan.')
    showCancelModal.value = false
    fetchData(currentPage.value)
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal mengajukan pembatalan')
  } finally {
    isSubmittingCancel.value = false
  }
}

const fetchData = async (page = 1) => {
  loading.value = true
  try {
    const res = await axios.get(`/api/v1/payments`, {
      params: {
        page,
        search: search.value,
        date: selectedDate.value,
        method: selectedMethod.value
      }
    })
    payments.value = res.data.data
    currentPage.value = res.data.current_page
    lastPage.value = res.data.last_page
  } catch (error) {
    console.error('Error fetching payments', error)
  } finally {
    loading.value = false
  }
}

let timeoutId: any = null
const debouncedFetch = () => {
  if (timeoutId) clearTimeout(timeoutId)
  timeoutId = setTimeout(() => {
    fetchData(1)
  }, 500)
}

watch([search, selectedDate, selectedMethod], () => {
  debouncedFetch()
})

const formatRupiah = (number: number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number)
}

const formatDate = (dateString: string) => {
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(dateString))
}

const printReceipt = (id: number) => {
  window.open(`/pembayaran/${id}/kuitansi`, '_blank')
}

onMounted(() => {
  getPaymentMethods()
  fetchData()
})
</script>

<template>
  <div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="text-title-md2 font-semibold text-black dark:text-white">Riwayat Pembayaran</h2>
    </div>

    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
      <div class="p-4 md:p-6 xl:p-7.5">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
          <div class="relative w-full sm:w-1/3">
            <span class="absolute left-4 top-1/2 -translate-y-1/2">
              <svg class="fill-body dark:fill-bodydark" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M9.16666 3.33332C5.945 3.33332 3.33332 5.945 3.33332 9.16666C3.33332 12.3883 5.945 15 9.16666 15C12.3883 15 15 12.3883 15 9.16666C15 5.945 12.3883 3.33332 9.16666 3.33332ZM1.66666 9.16666C1.66666 5.02452 5.02452 1.66666 9.16666 1.66666C13.3088 1.66666 16.6667 5.02452 16.6667 9.16666C16.6667 11.1444 15.899 12.9431 14.6468 14.2884L18.0892 17.7308C18.4147 18.0562 18.4147 18.5838 18.0892 18.9093C17.7638 19.2348 17.2362 19.2348 16.9107 18.9093L13.4683 15.4669C12.1231 16.7191 10.3244 17.4868 8.33333 17.4868C4.1912 17.4868 0.833336 14.129 0.833336 9.98685H1.66666Z" fill=""/>
              </svg>
            </span>
            <input v-model="search" type="text" placeholder="Cari No Transaksi / Nama..." class="w-full rounded border-[1.5px] border-stroke bg-transparent py-2 px-10 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" />
          </div>

          <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
            <input v-model="selectedDate" type="date" class="w-full sm:w-auto rounded border-[1.5px] border-stroke bg-transparent py-2 px-4 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" />
            
            <select v-model="selectedMethod" class="w-full sm:w-auto rounded border-[1.5px] border-stroke bg-transparent py-2 px-4 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input">
              <option value="">Semua Metode</option>
              <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full table-auto text-sm">
            <thead>
              <tr class="bg-gray-2 text-left dark:bg-meta-4">
                <th class="py-4 px-4 font-medium text-black dark:text-white xl:px-6">No. Transaksi</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Tanggal</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Siswa</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Metode</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white text-right">Total</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white text-center">Status</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7" class="text-center py-8">Memuat data...</td>
              </tr>
              <tr v-else-if="payments.length === 0">
                <td colspan="7" class="text-center py-8 text-gray-500">Tidak ada riwayat pembayaran.</td>
              </tr>
              <tr v-else v-for="payment in payments" :key="payment.id" class="border-b border-stroke dark:border-strokedark hover:bg-gray-50 dark:hover:bg-meta-4/20">
                <td class="py-4 px-4 xl:px-6 font-medium">{{ payment.transaction_number }}</td>
                <td class="py-4 px-4">{{ formatDate(payment.payment_date) }}</td>
                <td class="py-4 px-4">
                  <p class="font-medium text-black dark:text-white">{{ payment.student?.name }}</p>
                  <p class="text-xs text-gray-500">{{ payment.student?.class_room?.name }} | NIS: {{ payment.student?.nis }}</p>
                </td>
                <td class="py-4 px-4">{{ payment.payment_method?.name }}</td>
                <td class="py-4 px-4 text-right font-medium text-black dark:text-white">{{ formatRupiah(payment.total_amount) }}</td>
                <td class="py-4 px-4 text-center">
                  <span class="inline-flex rounded-full bg-opacity-10 py-1 px-3 text-xs font-medium" :class="payment.status === 'SUCCESS' ? 'bg-success text-success' : 'bg-danger text-danger'">
                    {{ payment.status }}
                  </span>
                </td>
                <td class="py-4 px-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="printReceipt(payment.id)" class="text-primary hover:text-opacity-80 transition" title="Cetak Kuitansi">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    </button>
                    <button v-if="payment.status === 'SUCCESS'" @click="openCancelModal(payment.id)" class="text-danger hover:text-opacity-80 transition" title="Ajukan Pembatalan">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="mt-6 flex justify-end gap-2">
          <button @click="fetchData(currentPage - 1)" :disabled="currentPage === 1" class="rounded px-3 py-1 text-sm bg-gray-2 dark:bg-meta-4 hover:bg-gray-3 disabled:opacity-50">
            &larr; Prev
          </button>
          <span class="px-3 py-1 text-sm font-medium">Page {{ currentPage }} of {{ lastPage }}</span>
          <button @click="fetchData(currentPage + 1)" :disabled="currentPage === lastPage" class="rounded px-3 py-1 text-sm bg-gray-2 dark:bg-meta-4 hover:bg-gray-3 disabled:opacity-50">
            Next &rarr;
          </button>
        </div>

      </div>
    </div>

    <!-- Cancel Modal -->
    <div v-if="showCancelModal" class="fixed inset-0 z-999999 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
      <div class="w-full max-w-md rounded-lg bg-white p-8 dark:bg-boxdark shadow-lg border border-stroke dark:border-strokedark">
        <h3 class="mb-4 text-xl font-bold text-black dark:text-white text-center">Ajukan Pembatalan</h3>
        <p class="mb-6 text-gray-500 dark:text-gray-400 text-sm text-center">Permintaan pembatalan transaksi akan diteruskan ke Kepala Sekolah untuk disetujui.</p>
        
        <div class="mb-6">
          <label class="mb-2.5 block text-black dark:text-white font-medium">Alasan Pembatalan</label>
          <textarea v-model="cancelReason" rows="3" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" placeholder="Wajib mengisi alasan pembatalan..."></textarea>
        </div>
        
        <div class="flex gap-4">
          <button @click="showCancelModal = false" class="flex w-1/2 items-center justify-center rounded border border-stroke p-3 font-medium text-black hover:bg-gray dark:border-strokedark dark:text-white transition">
            Batal
          </button>
          <button @click="submitCancel" :disabled="isSubmittingCancel || !cancelReason" class="flex w-1/2 items-center justify-center rounded bg-danger p-3 font-medium text-white hover:bg-opacity-90 transition disabled:opacity-50">
            {{ isSubmittingCancel ? 'Memproses...' : 'Ajukan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
