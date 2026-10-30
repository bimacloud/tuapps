<template>
  <AdminLayout>
    <PageHeader title="Riwayat Pembayaran" description="Semua pembayaran yang dicatat di kasir, terbaru di atas.">
      <template v-if="isBendahara" #actions>
        <router-link to="/kasir" class="btn btn-primary">Buka kasir</router-link>
      </template>
    </PageHeader>

    <section class="panel mb-4 p-4" aria-label="Filter riwayat pembayaran">
      <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)] md:items-end">
        <div>
          <label for="filter-cari" class="field-label">Cari</label>
          <input
            id="filter-cari"
            v-model="search"
            type="search"
            autocomplete="off"
            class="field-input"
            placeholder="No. transaksi, nama, atau NIS siswa"
          />
        </div>
        <div>
          <label for="filter-tanggal" class="field-label">Tanggal bayar</label>
          <input id="filter-tanggal" v-model="selectedDate" type="date" class="field-input" />
        </div>
        <div v-if="paymentMethods.length">
          <label for="filter-metode" class="field-label">Metode</label>
          <select id="filter-metode" v-model="selectedMethod" class="field-input">
            <option value="">Semua metode</option>
            <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
          </select>
        </div>
      </div>
      <div v-if="hasActiveFilters" class="mt-3">
        <button type="button" class="btn btn-ghost" @click="resetFilters">Hapus filter</button>
      </div>
    </section>

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Mengambil riwayat pembayaran." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData(currentPage)">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="payments.length === 0 && hasActiveFilters"
        state="empty"
        title="Tidak ada pembayaran yang cocok."
        message="Tidak ada transaksi dengan kata kunci, tanggal, atau metode itu. Ubah filter atau hapus semuanya."
      >
        <button type="button" class="btn btn-secondary" @click="resetFilters">Hapus filter</button>
      </DataState>
      <DataState
        v-else-if="payments.length === 0"
        state="empty"
        title="Belum ada pembayaran."
        :message="isBendahara ? 'Pembayaran yang disimpan di kasir akan tercatat di sini.' : 'Bendahara belum mencatat pembayaran di kasir.'"
      >
        <router-link v-if="isBendahara" to="/kasir" class="btn btn-primary">Buka kasir</router-link>
      </DataState>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="table-data">
            <thead>
              <tr>
                <th scope="col">Tanggal</th>
                <th scope="col">Siswa</th>
                <th scope="col" class="num">Total</th>
                <th scope="col">Status</th>
                <th scope="col">No. transaksi</th>
                <th scope="col">Metode</th>
                <th scope="col"><span class="sr-only">Aksi</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in payments" :key="payment.id">
                <td class="whitespace-nowrap">{{ formatDateTime(payment.payment_date) }}</td>
                <td class="min-w-48">
                  <router-link
                    v-if="studentId(payment)"
                    :to="`/siswa/${studentId(payment)}/rekap`"
                    class="link"
                  >
                    {{ payment.student?.name }}
                  </router-link>
                  <span v-else class="font-semibold">{{ payment.student?.name || '-' }}</span>
                  <p class="text-sm text-tinta-2">
                    {{ [payment.student?.class_room?.name, payment.student?.nis ? `NIS ${payment.student.nis}` : ''].filter(Boolean).join(', ') }}
                  </p>
                </td>
                <td class="num font-semibold">{{ formatRupiah(payment.total_amount) }}</td>
                <td><StatusTag :status="payment.status" /></td>
                <td class="whitespace-nowrap text-tinta-2">{{ payment.transaction_number }}</td>
                <td class="whitespace-nowrap">{{ payment.payment_method?.name || '-' }}</td>
                <td class="text-right">
                  <div class="flex justify-end gap-1">
                    <button type="button" class="btn btn-ghost btn-sm" @click="printReceipt(payment.id)">
                      Cetak kuitansi<span class="sr-only"> {{ payment.transaction_number }}</span>
                    </button>
                    <button
                      v-if="isBendahara && payment.status === 'SUCCESS'"
                      type="button"
                      class="btn btn-ghost btn-sm !text-galat"
                      @click="openCancelModal(payment.id, payment.transaction_number)"
                    >
                      Ajukan pembatalan<span class="sr-only"> {{ payment.transaction_number }}</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav
          v-if="lastPage > 1"
          class="flex flex-wrap items-center justify-between gap-3 border-t border-garis px-4 py-3"
          aria-label="Halaman riwayat pembayaran"
        >
          <p class="text-sm text-tinta-2">Halaman {{ currentPage }} dari {{ lastPage }}</p>
          <div class="flex gap-2">
            <button type="button" class="btn btn-secondary" :disabled="currentPage === 1" @click="fetchData(currentPage - 1)">
              Sebelumnya
            </button>
            <button type="button" class="btn btn-secondary" :disabled="currentPage === lastPage" @click="fetchData(currentPage + 1)">
              Berikutnya
            </button>
          </div>
        </nav>
      </template>
    </section>

    <BaseModal
      :open="showCancelModal"
      title="Ajukan pembatalan"
      :description="`Permintaan membatalkan ${selectedPaymentNumber || 'transaksi ini'} diteruskan ke Kepala Sekolah. Pembayaran tetap tercatat sampai disetujui.`"
      @close="closeCancelModal"
    >
      <form id="form-pembatalan" class="space-y-4" @submit.prevent="submitCancel">
        <p
          v-if="cancelError"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ cancelError }}
        </p>
        <div>
          <label for="alasan-pembatalan" class="field-label">Alasan pembatalan</label>
          <textarea
            id="alasan-pembatalan"
            v-model="cancelReason"
            rows="3"
            required
            class="field-input"
            aria-describedby="alasan-pembatalan-info"
            placeholder="Misalnya salah pilih siswa atau salah nominal"
          ></textarea>
          <p id="alasan-pembatalan-info" class="field-hint">Wajib diisi. Kepala Sekolah membaca alasan ini sebelum menyetujui.</p>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="closeCancelModal">Batal</button>
        <button type="submit" form="form-pembatalan" class="btn btn-danger" :disabled="isSubmittingCancel || !cancelReason">
          {{ isSubmittingCancel ? 'Mengirim permintaan...' : 'Kirim permintaan pembatalan' }}
        </button>
      </template>
    </BaseModal>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotice } from '@/composables/useNotice'
import { errorMessage, formatDateTime, formatRupiah } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const authStore = useAuthStore()
const notice = useNotice()
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

const payments = ref<any[]>([])
const loading = ref(true)
const loadError = ref('')

// Filters
const search = ref('')
const selectedDate = ref('')
const selectedMethod = ref('')
const paymentMethods = ref<any[]>([])

const hasActiveFilters = computed(() => Boolean(search.value || selectedDate.value || selectedMethod.value))

const resetFilters = () => {
  search.value = ''
  selectedDate.value = ''
  selectedMethod.value = ''
}

// Pagination
const currentPage = ref(1)
const lastPage = ref(1)

/** The method list is only served to Bendahara; for other roles the filter stays hidden. */
const getPaymentMethods = async () => {
  try {
    const res = await axios.get('/api/v1/payment-methods')
    paymentMethods.value = res.data.data
  } catch (error) {
    paymentMethods.value = []
  }
}

// Cancel Modal State
const showCancelModal = ref(false)
const selectedPaymentId = ref<number | null>(null)
const selectedPaymentNumber = ref('')
const cancelReason = ref('')
const cancelError = ref('')
const isSubmittingCancel = ref(false)

const openCancelModal = (paymentId: number, transactionNumber = '') => {
  selectedPaymentId.value = paymentId
  selectedPaymentNumber.value = transactionNumber
  cancelReason.value = ''
  cancelError.value = ''
  showCancelModal.value = true
}

const closeCancelModal = () => {
  if (isSubmittingCancel.value) return
  showCancelModal.value = false
}

const submitCancel = async () => {
  if (!selectedPaymentId.value || !cancelReason.value) return

  isSubmittingCancel.value = true
  cancelError.value = ''
  try {
    await axios.post(`/api/v1/payments/${selectedPaymentId.value}/cancel-request`, {
      reason: cancelReason.value,
    })
    notice.success('Permintaan pembatalan terkirim dan menunggu persetujuan Kepala Sekolah.')
    showCancelModal.value = false
    fetchData(currentPage.value)
  } catch (err: any) {
    cancelError.value = errorMessage(err, 'Permintaan pembatalan gagal dikirim. Coba lagi.')
  } finally {
    isSubmittingCancel.value = false
  }
}

const fetchData = async (page = 1) => {
  loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get(`/api/v1/payments`, {
      params: {
        page,
        search: search.value,
        date: selectedDate.value,
        method: selectedMethod.value,
      },
    })
    payments.value = res.data.data
    currentPage.value = res.data.current_page
    lastPage.value = res.data.last_page
  } catch (error) {
    loadError.value = errorMessage(error, 'Riwayat pembayaran gagal dimuat. Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

let timeoutId: ReturnType<typeof setTimeout> | null = null
const debouncedFetch = () => {
  if (timeoutId) clearTimeout(timeoutId)
  timeoutId = setTimeout(() => {
    fetchData(1)
  }, 500)
}

watch([search, selectedDate, selectedMethod], () => {
  debouncedFetch()
})

const studentId = (payment: any) => payment.student_id ?? payment.student?.id ?? null

const printReceipt = (id: number) => {
  window.open(`/pembayaran/${id}/kuitansi`, '_blank')
}

onMounted(() => {
  getPaymentMethods()
  fetchData()
})
</script>
