<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue'
import axios from 'axios'
import {
  Search,
  X,
  Check,
  Printer,
  Banknote,
  CreditCard,
  QrCode,
  ArrowLeft,
  ArrowRight,
  LoaderCircle,
  CircleAlert,
  CircleCheckBig,
  Receipt,
  Wallet,
  Lock,
  Clock,
} from 'lucide-vue-next'

const inputClass =
  'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800'

// Search state
const searchInput = ref<HTMLInputElement | null>(null)
const searchQuery = ref('')
const searchResults = ref<any[]>([])
const isSearching = ref(false)
const highlightedIndex = ref(0)

// Selection state
const selectedStudent = ref<any>(null)
const studentInvoices = ref<any[]>([])
const isLoadingInvoices = ref(false)

// Payment state
const paymentMethods = ref<any[]>([])
const selectedMethod = ref<number | null>(null)
const notes = ref('')
const cashReceived = ref<number | null>(null)
const payAmounts = ref<Record<number, number>>({}) // invoice_item_id -> amount
const selectedItems = ref<Record<number, boolean>>({}) // invoice_item_id -> isSelected

// Confirmation state
const showConfirmModal = ref(false)
const confirmChecked = ref(false)
const isProcessing = ref(false)
const paymentError = ref('')

// Success state
const successPayment = ref<any>(null)
const successChange = ref(0)
const showSuccessModal = ref(false)

// Session state
const activeSession = ref<any>(null)
const showOpenSessionModal = ref(false)
const showCloseSessionModal = ref(false)
const startingBalance = ref<number | null>(null)
const actualBalance = ref<number | null>(null)
const closeNotes = ref('')
const sessionSummary = ref<any>(null)
const isSubmittingSession = ref(false)
const sessionError = ref('')

const formatRupiah = (value: number | string | null | undefined) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value) || 0)
}

const formatTime = (value: string) => {
  return new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const initials = (name: string) => {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('')
}

// ---------- Session ----------

const checkSession = async () => {
  try {
    const res = await axios.get('/api/v1/cashier-sessions/current')
    if (res.data.data) {
      activeSession.value = res.data.data
      focusSearch()
    } else {
      showOpenSessionModal.value = true
    }
  } catch (err) {
    console.error(err)
  }
}

const isStartingBalanceValid = computed(() => typeof startingBalance.value === 'number' && startingBalance.value >= 0)

const openSession = async () => {
  if (!isStartingBalanceValid.value) return
  isSubmittingSession.value = true
  sessionError.value = ''
  try {
    const res = await axios.post('/api/v1/cashier-sessions/open', {
      starting_balance: startingBalance.value,
    })
    activeSession.value = res.data.data
    showOpenSessionModal.value = false
    startingBalance.value = null
    focusSearch()
  } catch (err: any) {
    sessionError.value = err.response?.data?.message || 'Gagal membuka sesi kasir'
  } finally {
    isSubmittingSession.value = false
  }
}

const fetchSessionSummary = async () => {
  sessionError.value = ''
  actualBalance.value = null
  closeNotes.value = ''
  try {
    const res = await axios.get('/api/v1/cashier-sessions/summary')
    sessionSummary.value = res.data.data
    showCloseSessionModal.value = true
  } catch (err) {
    console.error(err)
  }
}

const closeDifference = computed(() => {
  if (typeof actualBalance.value !== 'number' || !sessionSummary.value) return 0
  return actualBalance.value - Number(sessionSummary.value.expected_balance)
})

const closeSession = async () => {
  if (typeof actualBalance.value !== 'number') return
  isSubmittingSession.value = true
  sessionError.value = ''
  try {
    await axios.post('/api/v1/cashier-sessions/close', {
      actual_balance: actualBalance.value,
      notes: closeNotes.value,
    })
    showCloseSessionModal.value = false
    activeSession.value = null
    resetKasir()
    showOpenSessionModal.value = true
  } catch (err: any) {
    sessionError.value = err.response?.data?.message || 'Gagal menutup sesi kasir'
  } finally {
    isSubmittingSession.value = false
  }
}

// ---------- Student search ----------

const getPaymentMethods = async () => {
  try {
    const res = await axios.get('/api/v1/payment-methods')
    paymentMethods.value = res.data.data
    const cash = paymentMethods.value.find((method) => method.code === 'CASH')
    selectedMethod.value = cash?.id ?? paymentMethods.value[0]?.id ?? null
  } catch (err) {
    console.error(err)
  }
}

const focusSearch = () => {
  nextTick(() => searchInput.value?.focus())
}

let searchTimeout: ReturnType<typeof setTimeout> | undefined
watch(searchQuery, (newVal) => {
  clearTimeout(searchTimeout)
  highlightedIndex.value = 0
  if (!newVal) {
    searchResults.value = []
    isSearching.value = false
    return
  }

  isSearching.value = true
  searchTimeout = setTimeout(async () => {
    try {
      const res = await axios.get('/api/v1/payments/students/search', { params: { q: newVal } })
      searchResults.value = res.data.data
    } catch (err) {
      console.error(err)
    } finally {
      isSearching.value = false
    }
  }, 300)
})

const onSearchKeydown = (event: KeyboardEvent) => {
  if (!searchResults.value.length) return

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    highlightedIndex.value = (highlightedIndex.value + 1) % searchResults.value.length
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    highlightedIndex.value = (highlightedIndex.value - 1 + searchResults.value.length) % searchResults.value.length
  } else if (event.key === 'Enter') {
    event.preventDefault()
    selectStudent(searchResults.value[highlightedIndex.value])
  } else if (event.key === 'Escape') {
    searchQuery.value = ''
  }
}

const selectStudent = async (student: any) => {
  selectedStudent.value = student
  searchQuery.value = ''
  searchResults.value = []
  cashReceived.value = null
  notes.value = ''

  await loadStudentInvoices(student.id)
}

const loadStudentInvoices = async (studentId: number) => {
  isLoadingInvoices.value = true
  try {
    const res = await axios.get(`/api/v1/payments/student/${studentId}/invoices`)
    studentInvoices.value = res.data.data

    payAmounts.value = {}
    selectedItems.value = {}

    studentInvoices.value.forEach((invoice) => {
      invoice.items.forEach((item: any) => {
        payAmounts.value[item.id] = parseFloat(item.remaining_amount)
        selectedItems.value[item.id] = false
      })
    })
  } catch (err) {
    console.error(err)
  } finally {
    isLoadingInvoices.value = false
  }
}

// ---------- Bill selection ----------

const allItems = computed(() => studentInvoices.value.flatMap((invoice) => invoice.items))

const totalOutstanding = computed(() => allItems.value.reduce((sum: number, item: any) => sum + parseFloat(item.remaining_amount), 0))

const selectedItemList = computed(() => allItems.value.filter((item: any) => selectedItems.value[item.id]))

const itemErrors = computed(() => {
  const errors: Record<number, string> = {}
  selectedItemList.value.forEach((item: any) => {
    const amount = payAmounts.value[item.id]
    if (!amount || amount <= 0) {
      errors[item.id] = 'Nominal harus lebih dari 0'
    } else if (amount > parseFloat(item.remaining_amount)) {
      errors[item.id] = `Maksimal ${formatRupiah(item.remaining_amount)}`
    }
  })
  return errors
})

const isInvoiceFullySelected = (invoice: any) => invoice.items.length > 0 && invoice.items.every((item: any) => selectedItems.value[item.id])

const toggleInvoice = (invoice: any) => {
  const shouldSelect = !isInvoiceFullySelected(invoice)
  invoice.items.forEach((item: any) => {
    selectedItems.value[item.id] = shouldSelect
  })
}

const toggleItem = (itemId: number) => {
  selectedItems.value[itemId] = !selectedItems.value[itemId]
}

const payInFull = (item: any) => {
  payAmounts.value[item.id] = parseFloat(item.remaining_amount)
}

const isPartial = (item: any) => {
  const amount = payAmounts.value[item.id] || 0
  return amount > 0 && amount < parseFloat(item.remaining_amount)
}

// ---------- Payment ----------

const totalToPay = computed(() => selectedItemList.value.reduce((sum: number, item: any) => sum + (payAmounts.value[item.id] || 0), 0))

const selectedMethodObj = computed(() => paymentMethods.value.find((method) => method.id === selectedMethod.value))

const isCash = computed(() => selectedMethodObj.value?.code === 'CASH')

const change = computed(() => (cashReceived.value ?? 0) - totalToPay.value)

const cashSuggestions = computed(() => {
  const total = totalToPay.value
  if (total <= 0) return []
  const candidates = [total, Math.ceil(total / 10000) * 10000, Math.ceil(total / 50000) * 50000, Math.ceil(total / 100000) * 100000]
  return [...new Set(candidates)].slice(0, 4)
})

const methodIcon = (code: string) => {
  if (code === 'CASH') return Banknote
  if (code === 'ONLINE') return QrCode
  return CreditCard
}

const blockingReason = computed(() => {
  if (selectedItemList.value.length === 0) return 'Pilih minimal satu tagihan.'
  if (Object.keys(itemErrors.value).length > 0) return 'Periksa nominal tagihan yang ditandai merah.'
  if (!selectedMethod.value) return 'Pilih metode pembayaran.'
  if (isCash.value && (cashReceived.value === null || cashReceived.value <= 0)) return 'Isi jumlah uang yang diterima.'
  if (isCash.value && change.value < 0) return `Uang diterima kurang ${formatRupiah(-change.value)}.`
  return null
})

const currentStep = computed(() => {
  if (showConfirmModal.value) return 4
  if (!selectedStudent.value) return 1
  if (selectedItemList.value.length === 0) return 2
  return 3
})

const steps = ['Pilih Siswa', 'Pilih Tagihan', 'Pembayaran', 'Konfirmasi']

const openConfirm = () => {
  if (blockingReason.value) return
  paymentError.value = ''
  confirmChecked.value = false
  showConfirmModal.value = true
}

const closeConfirm = () => {
  if (isProcessing.value) return
  showConfirmModal.value = false
}

const submitPayment = async () => {
  if (!confirmChecked.value || blockingReason.value) return

  isProcessing.value = true
  paymentError.value = ''
  try {
    const res = await axios.post('/api/v1/payments', {
      student_id: selectedStudent.value.id,
      payment_method_id: selectedMethod.value,
      notes: notes.value,
      items: selectedItemList.value.map((item: any) => ({
        invoice_item_id: item.id,
        amount: payAmounts.value[item.id],
      })),
    })

    successPayment.value = res.data.data
    successChange.value = isCash.value ? change.value : 0
    showConfirmModal.value = false
    showSuccessModal.value = true
  } catch (err: any) {
    paymentError.value = err.response?.data?.message || 'Gagal memproses pembayaran'
  } finally {
    isProcessing.value = false
  }
}

const resetKasir = () => {
  showSuccessModal.value = false
  successPayment.value = null
  selectedStudent.value = null
  studentInvoices.value = []
  payAmounts.value = {}
  selectedItems.value = {}
  notes.value = ''
  cashReceived.value = null
  searchQuery.value = ''
  focusSearch()
}

const printReceipt = () => {
  if (successPayment.value) {
    window.open(`/pembayaran/${successPayment.value.id}/kuitansi`, '_blank')
  }
}

const onGlobalKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && showConfirmModal.value) {
    closeConfirm()
  }
}

onMounted(() => {
  checkSession()
  getPaymentMethods()
  window.addEventListener('keydown', onGlobalKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onGlobalKeydown)
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Kasir Pembayaran</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Terima pembayaran tagihan siswa di loket.</p>
      </div>
      <div v-if="activeSession" class="flex flex-wrap items-center gap-3">
        <span class="inline-flex items-center gap-2 rounded-full bg-success-50 px-3 py-1.5 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">
          <span class="h-2 w-2 animate-pulse rounded-full bg-success-500"></span>
          Kasir buka sejak {{ formatTime(activeSession.start_time) }} · Modal {{ formatRupiah(activeSession.starting_balance) }}
        </span>
        <button
          @click="fetchSessionSummary"
          class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-medium text-error-600 ring-1 ring-inset ring-error-200 transition hover:bg-error-50 dark:bg-gray-800 dark:text-error-500 dark:ring-error-500/30 dark:hover:bg-error-500/10"
        >
          <Lock class="h-4 w-4" />
          Tutup Kasir
        </button>
      </div>
    </div>

    <!-- Stepper -->
    <ol class="mb-6 flex flex-wrap items-center gap-2 text-sm">
      <li v-for="(step, index) in steps" :key="step" class="flex items-center gap-2">
        <span
          class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition"
          :class="
            index + 1 < currentStep
              ? 'bg-success-500 text-white'
              : index + 1 === currentStep
                ? 'bg-brand-500 text-white ring-4 ring-brand-500/15'
                : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
          "
        >
          <Check v-if="index + 1 < currentStep" class="h-4 w-4" />
          <template v-else>{{ index + 1 }}</template>
        </span>
        <span :class="index + 1 === currentStep ? 'font-medium text-gray-800 dark:text-white/90' : 'text-gray-500 dark:text-gray-400'">{{ step }}</span>
        <span v-if="index < steps.length - 1" class="mx-1 hidden h-px w-8 bg-gray-200 sm:block dark:bg-gray-800"></span>
      </li>
    </ol>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <!-- Left: student & bills -->
      <div class="space-y-6 xl:col-span-2">
        <!-- Search -->
        <div v-if="!selectedStudent" class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cari Siswa</label>
          <div class="relative">
            <Search class="pointer-events-none absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-gray-400" />
            <input
              ref="searchInput"
              v-model="searchQuery"
              @keydown="onSearchKeydown"
              :disabled="!activeSession"
              type="text"
              placeholder="Ketik NIS, NISN, atau nama siswa..."
              :class="[inputClass, 'h-12 pr-12 pl-12 disabled:cursor-not-allowed disabled:opacity-60']"
            />
            <LoaderCircle v-if="isSearching" class="absolute top-1/2 right-4 h-5 w-5 -translate-y-1/2 animate-spin text-brand-500" />

            <!-- Results -->
            <div
              v-if="searchQuery && !isSearching"
              class="absolute left-0 z-50 mt-2 max-h-80 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
            >
              <ul v-if="searchResults.length > 0">
                <li
                  v-for="(student, index) in searchResults"
                  :key="student.id"
                  @click="selectStudent(student)"
                  @mouseenter="highlightedIndex = index"
                  class="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-4 py-3 last:border-0 dark:border-gray-800"
                  :class="index === highlightedIndex ? 'bg-brand-50 dark:bg-brand-500/10' : ''"
                >
                  <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
                    {{ initials(student.name) }}
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ student.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">NIS {{ student.nis || '-' }} · NISN {{ student.nisn || '-' }}</p>
                  </div>
                  <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ student.class_room?.name }}
                  </span>
                </li>
              </ul>
              <p v-else class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada siswa dengan tagihan tertunggak.</p>
            </div>
          </div>
          <p class="mt-2 text-xs text-gray-400">Gunakan ↑ ↓ untuk memilih dan Enter untuk konfirmasi.</p>
        </div>

        <!-- Selected student -->
        <div v-else class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 sm:flex-row sm:items-center dark:border-gray-800 dark:bg-white/[0.03]">
          <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-100 text-lg font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
            {{ initials(selectedStudent.name) }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ selectedStudent.name }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ selectedStudent.class_room?.name }} · {{ selectedStudent.class_room?.major?.name }} · NIS {{ selectedStudent.nis || '-' }}
            </p>
          </div>
          <div class="sm:text-right">
            <p class="text-xs text-gray-500 dark:text-gray-400">Total tunggakan</p>
            <p class="text-lg font-semibold text-error-600 dark:text-error-500">{{ formatRupiah(totalOutstanding) }}</p>
          </div>
          <button
            @click="resetKasir"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
          >
            <ArrowLeft class="h-4 w-4" />
            Ganti Siswa
          </button>
        </div>

        <!-- Bills -->
        <div v-if="selectedStudent" class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <div class="flex items-center justify-between px-5 py-4 sm:px-6">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Tagihan Belum Lunas</h3>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ selectedItemList.length }} dari {{ allItems.length }} dipilih</span>
          </div>

          <div class="border-t border-gray-100 p-4 sm:p-6 dark:border-gray-800">
            <div v-if="isLoadingInvoices" class="space-y-3">
              <div v-for="n in 3" :key="n" class="h-16 animate-pulse rounded-xl bg-gray-100 dark:bg-gray-800"></div>
            </div>

            <div v-else-if="studentInvoices.length === 0" class="flex flex-col items-center gap-2 py-8 text-center">
              <CircleCheckBig class="h-10 w-10 text-success-500" />
              <p class="font-medium text-gray-800 dark:text-white/90">Semua tagihan sudah lunas.</p>
            </div>

            <div v-else class="space-y-4">
              <div v-for="invoice in studentInvoices" :key="invoice.id" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <!-- Invoice header -->
                <div class="flex flex-wrap items-center justify-between gap-3 bg-gray-50 px-4 py-3 dark:bg-white/[0.02]">
                  <div class="flex items-center gap-3">
                    <Receipt class="h-4 w-4 text-gray-400" />
                    <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ invoice.invoice_number }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">T.A. {{ invoice.academic_year?.name }}</span>
                  </div>
                  <button
                    @click="toggleInvoice(invoice)"
                    class="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                  >
                    {{ isInvoiceFullySelected(invoice) ? 'Batalkan semua' : 'Pilih semua' }}
                  </button>
                </div>

                <!-- Items -->
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                  <div
                    v-for="item in invoice.items"
                    :key="item.id"
                    @click="toggleItem(item.id)"
                    class="flex cursor-pointer flex-wrap items-center gap-4 px-4 py-3.5 transition sm:flex-nowrap"
                    :class="selectedItems[item.id] ? 'bg-brand-50/60 dark:bg-brand-500/5' : 'hover:bg-gray-50 dark:hover:bg-white/[0.02]'"
                  >
                    <span
                      class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-[1.5px] transition"
                      :class="selectedItems[item.id] ? 'border-brand-500 bg-brand-500' : 'border-gray-300 dark:border-gray-700'"
                    >
                      <Check v-if="selectedItems[item.id]" class="h-3.5 w-3.5 text-white" />
                    </span>

                    <div class="min-w-0 flex-1">
                      <p class="font-medium text-gray-800 dark:text-white/90">
                        {{ item.payment_type?.name }}
                        <span v-if="item.period" class="text-gray-500 dark:text-gray-400">· {{ item.period }}</span>
                      </p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">
                        Tarif {{ formatRupiah(item.amount) }}
                        <template v-if="Number(item.paid_amount) > 0"> · Sudah dibayar {{ formatRupiah(item.paid_amount) }}</template>
                      </p>
                    </div>

                    <div class="text-right">
                      <p class="text-xs text-gray-500 dark:text-gray-400">Sisa</p>
                      <p class="text-sm font-semibold text-error-600 dark:text-error-500">{{ formatRupiah(item.remaining_amount) }}</p>
                    </div>

                    <!-- Amount editor (only when selected) -->
                    <div v-if="selectedItems[item.id]" @click.stop class="w-full sm:w-48">
                      <div class="relative">
                        <span class="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-gray-500">Rp</span>
                        <input
                          type="number"
                          min="1"
                          :max="item.remaining_amount"
                          step="1000"
                          v-model.number="payAmounts[item.id]"
                          :class="[inputClass, 'h-10 pr-3 pl-10 text-right', itemErrors[item.id] ? 'border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-700' : '']"
                        />
                      </div>
                      <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                        <span v-if="itemErrors[item.id]" class="text-error-600 dark:text-error-500">{{ itemErrors[item.id] }}</span>
                        <span v-else-if="isPartial(item)" class="text-warning-600 dark:text-warning-500">Cicilan</span>
                        <span v-else class="text-success-600 dark:text-success-500">Lunas</span>
                        <button v-if="isPartial(item) || itemErrors[item.id]" @click="payInFull(item)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                          Bayar lunas
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: payment summary -->
      <div class="xl:col-span-1">
        <div class="sticky top-24 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <div class="px-5 py-4 sm:px-6">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Ringkasan Pembayaran</h3>
          </div>

          <div class="border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
            <div v-if="selectedItemList.length === 0" class="flex flex-col items-center gap-2 py-8 text-center">
              <Wallet class="h-10 w-10 text-gray-300 dark:text-gray-700" />
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ selectedStudent ? 'Pilih tagihan yang akan dibayar.' : 'Cari dan pilih siswa terlebih dahulu.' }}
              </p>
            </div>

            <div v-else class="space-y-5">
              <!-- Selected items -->
              <ul class="space-y-2">
                <li v-for="item in selectedItemList" :key="item.id" class="flex items-start justify-between gap-3 text-sm">
                  <div class="min-w-0">
                    <p class="truncate text-gray-700 dark:text-gray-300">{{ item.payment_type?.name }} <span v-if="item.period">· {{ item.period }}</span></p>
                    <p v-if="isPartial(item)" class="text-xs text-warning-600 dark:text-warning-500">Cicilan</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="font-medium whitespace-nowrap text-gray-800 dark:text-white/90">{{ formatRupiah(payAmounts[item.id]) }}</span>
                    <button @click="toggleItem(item.id)" class="text-gray-400 hover:text-error-500" title="Hapus dari pembayaran">
                      <X class="h-4 w-4" />
                    </button>
                  </div>
                </li>
              </ul>

              <div class="flex items-end justify-between border-t border-dashed border-gray-200 pt-4 dark:border-gray-800">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Bayar</span>
                <span class="text-2xl font-bold text-brand-600 dark:text-brand-400">{{ formatRupiah(totalToPay) }}</span>
              </div>

              <!-- Method -->
              <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Metode Pembayaran</label>
                <div class="grid grid-cols-1 gap-2">
                  <button
                    v-for="method in paymentMethods"
                    :key="method.id"
                    @click="selectedMethod = method.id"
                    class="flex items-center gap-3 rounded-lg border px-4 py-3 text-left text-sm transition"
                    :class="
                      selectedMethod === method.id
                        ? 'border-brand-500 bg-brand-50 text-brand-700 ring-3 ring-brand-500/10 dark:bg-brand-500/10 dark:text-brand-300'
                        : 'border-gray-200 text-gray-700 hover:border-gray-300 dark:border-gray-800 dark:text-gray-300 dark:hover:border-gray-700'
                    "
                  >
                    <component :is="methodIcon(method.code)" class="h-5 w-5 shrink-0" />
                    <span class="flex-1 font-medium">{{ method.name }}</span>
                    <Check v-if="selectedMethod === method.id" class="h-4 w-4" />
                  </button>
                </div>
              </div>

              <!-- Cash received -->
              <div v-if="isCash" class="rounded-xl bg-gray-50 p-4 dark:bg-white/[0.02]">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Uang Diterima</label>
                <div class="relative">
                  <span class="absolute top-1/2 left-4 -translate-y-1/2 text-sm text-gray-500">Rp</span>
                  <input type="number" min="0" step="1000" v-model.number="cashReceived" placeholder="0" :class="[inputClass, 'pl-11']" />
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                  <button
                    v-for="(amount, index) in cashSuggestions"
                    :key="amount"
                    @click="cashReceived = amount"
                    class="rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset transition"
                    :class="cashReceived === amount ? 'bg-brand-500 text-white ring-brand-500' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-700'"
                  >
                    {{ index === 0 ? 'Uang pas' : formatRupiah(amount) }}
                  </button>
                </div>
                <div v-if="cashReceived" class="mt-3 flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">{{ change >= 0 ? 'Kembalian' : 'Kurang' }}</span>
                  <span class="text-lg font-semibold" :class="change >= 0 ? 'text-success-600 dark:text-success-500' : 'text-error-600 dark:text-error-500'">
                    {{ formatRupiah(Math.abs(change)) }}
                  </span>
                </div>
              </div>

              <p v-else-if="selectedMethodObj" class="flex gap-2 rounded-lg bg-blue-light-50 p-3 text-xs text-blue-light-700 dark:bg-blue-light-500/10 dark:text-blue-light-500">
                <CircleAlert class="h-4 w-4 shrink-0" />
                Pastikan dana sudah masuk ke rekening sekolah. Catat nomor referensi di kolom catatan.
              </p>

              <!-- Notes -->
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Catatan <span class="font-normal text-gray-400">(opsional)</span></label>
                <textarea
                  v-model="notes"
                  rows="2"
                  placeholder="Contoh: no. referensi transfer, nama penyetor..."
                  class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                ></textarea>
              </div>

              <div>
                <button
                  @click="openConfirm"
                  :disabled="!!blockingReason"
                  class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:bg-brand-300 dark:disabled:bg-brand-800"
                >
                  Lanjut ke Konfirmasi
                  <ArrowRight class="h-4 w-4" />
                </button>
                <p v-if="blockingReason" class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">{{ blockingReason }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="showConfirmModal" class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto px-4 py-6">
      <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="closeConfirm"></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl sm:p-8 dark:bg-gray-900">
        <button @click="closeConfirm" class="absolute top-4 right-4 rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
          <X class="h-5 w-5" />
        </button>

        <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Konfirmasi Pembayaran</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Periksa kembali sebelum disimpan. Transaksi yang tersimpan hanya bisa dibatalkan dengan persetujuan Kepala Sekolah.</p>

        <div class="mt-5 space-y-4">
          <div class="flex items-center gap-3 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300">
              {{ initials(selectedStudent.name) }}
            </span>
            <div>
              <p class="font-medium text-gray-800 dark:text-white/90">{{ selectedStudent.name }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedStudent.class_room?.name }} · NIS {{ selectedStudent.nis || '-' }}</p>
            </div>
          </div>

          <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <li v-for="item in selectedItemList" :key="item.id" class="flex justify-between gap-3 py-2">
              <span class="text-gray-700 dark:text-gray-300">
                {{ item.payment_type?.name }} <span v-if="item.period">· {{ item.period }}</span>
                <span v-if="isPartial(item)" class="ml-1 rounded bg-warning-50 px-1.5 py-0.5 text-xs text-warning-700 dark:bg-warning-500/15 dark:text-warning-500">Cicilan</span>
              </span>
              <span class="font-medium whitespace-nowrap text-gray-800 dark:text-white/90">{{ formatRupiah(payAmounts[item.id]) }}</span>
            </li>
          </ul>

          <dl class="space-y-2 rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800">
            <div class="flex justify-between">
              <dt class="text-gray-500 dark:text-gray-400">Metode</dt>
              <dd class="font-medium text-gray-800 dark:text-white/90">{{ selectedMethodObj?.name }}</dd>
            </div>
            <div class="flex justify-between text-base">
              <dt class="font-medium text-gray-700 dark:text-gray-300">Total Bayar</dt>
              <dd class="font-bold text-brand-600 dark:text-brand-400">{{ formatRupiah(totalToPay) }}</dd>
            </div>
            <template v-if="isCash">
              <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Uang Diterima</dt>
                <dd class="text-gray-800 dark:text-white/90">{{ formatRupiah(cashReceived) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Kembalian</dt>
                <dd class="font-semibold text-success-600 dark:text-success-500">{{ formatRupiah(change) }}</dd>
              </div>
            </template>
            <div v-if="notes" class="flex justify-between gap-4">
              <dt class="text-gray-500 dark:text-gray-400">Catatan</dt>
              <dd class="text-right text-gray-800 dark:text-white/90">{{ notes }}</dd>
            </div>
          </dl>

          <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-800" :class="confirmChecked ? 'border-success-300 bg-success-50 dark:border-success-700 dark:bg-success-500/10' : ''">
            <input type="checkbox" v-model="confirmChecked" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500" />
            <span class="text-gray-700 dark:text-gray-300">
              <template v-if="isCash">Saya sudah menerima uang tunai sebesar <strong>{{ formatRupiah(cashReceived) }}</strong> dan menyerahkan kembalian <strong>{{ formatRupiah(change) }}</strong>.</template>
              <template v-else>Saya sudah memastikan dana sebesar <strong>{{ formatRupiah(totalToPay) }}</strong> diterima melalui {{ selectedMethodObj?.name }}.</template>
            </span>
          </label>

          <p v-if="paymentError" class="flex gap-2 rounded-lg bg-error-50 p-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-500">
            <CircleAlert class="h-5 w-5 shrink-0" />
            {{ paymentError }}
          </p>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row">
          <button
            @click="closeConfirm"
            :disabled="isProcessing"
            class="flex flex-1 items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 disabled:opacity-50 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
          >
            <ArrowLeft class="h-4 w-4" />
            Periksa Lagi
          </button>
          <button
            @click="submitPayment"
            :disabled="!confirmChecked || isProcessing"
            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:bg-brand-300 dark:disabled:bg-brand-800"
          >
            <LoaderCircle v-if="isProcessing" class="h-4 w-4 animate-spin" />
            <Check v-else class="h-4 w-4" />
            {{ isProcessing ? 'Memproses...' : 'Ya, Proses Pembayaran' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="fixed inset-0 z-99999 flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-xl dark:bg-gray-900">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
          <CircleCheckBig class="h-9 w-9" />
        </div>

        <h3 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Pembayaran Berhasil</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ successPayment?.transaction_number }}</p>

        <p class="mt-4 text-3xl font-bold text-gray-800 dark:text-white/90">{{ formatRupiah(successPayment?.total_amount) }}</p>

        <div v-if="successChange > 0" class="mt-4 rounded-xl bg-success-50 p-4 dark:bg-success-500/10">
          <p class="text-sm text-success-700 dark:text-success-500">Serahkan kembalian</p>
          <p class="text-2xl font-bold text-success-700 dark:text-success-500">{{ formatRupiah(successChange) }}</p>
        </div>

        <div class="mt-6 flex flex-col gap-3">
          <button @click="printReceipt" class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-brand-600">
            <Printer class="h-5 w-5" />
            Cetak Kuitansi
          </button>
          <button
            @click="resetKasir"
            class="flex w-full items-center justify-center rounded-lg px-5 py-3 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
          >
            Transaksi Baru
          </button>
        </div>
      </div>
    </div>

    <!-- Open Session Modal -->
    <div v-if="showOpenSessionModal" class="fixed inset-0 z-99999 flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm"></div>
      <div class="relative w-full max-w-md rounded-2xl bg-white p-8 shadow-xl dark:bg-gray-900">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
          <Clock class="h-7 w-7" />
        </div>
        <h3 class="text-center text-xl font-semibold text-gray-800 dark:text-white/90">Buka Sesi Kasir</h3>
        <p class="mt-1 mb-6 text-center text-sm text-gray-500 dark:text-gray-400">Hitung uang modal di laci, lalu masukkan jumlahnya sebagai saldo awal.</p>

        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Saldo Awal (Tunai)</label>
        <div class="relative">
          <span class="absolute top-1/2 left-4 -translate-y-1/2 text-sm text-gray-500">Rp</span>
          <input type="number" min="0" v-model.number="startingBalance" @keydown.enter="openSession" placeholder="0" :class="[inputClass, 'pl-11']" />
        </div>

        <p v-if="sessionError" class="mt-3 text-sm text-error-600 dark:text-error-500">{{ sessionError }}</p>

        <button
          @click="openSession"
          :disabled="isSubmittingSession || !isStartingBalanceValid"
          class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:bg-brand-300 dark:disabled:bg-brand-800"
        >
          <LoaderCircle v-if="isSubmittingSession" class="h-4 w-4 animate-spin" />
          {{ isSubmittingSession ? 'Memproses...' : 'Buka Kasir' }}
        </button>
      </div>
    </div>

    <!-- Close Session Modal -->
    <div v-if="showCloseSessionModal && sessionSummary" class="fixed inset-0 z-99999 flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-sm" @click="showCloseSessionModal = false"></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl sm:p-8 dark:bg-gray-900">
        <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Tutup Sesi Kasir</h3>
        <p class="mt-1 mb-5 text-sm text-gray-500 dark:text-gray-400">Hitung uang fisik di laci dan cocokkan dengan catatan sistem.</p>

        <dl class="mb-5 space-y-2 rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800">
          <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">Saldo Awal</dt>
            <dd class="text-gray-800 dark:text-white/90">{{ formatRupiah(sessionSummary.session.starting_balance) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">Penerimaan Tunai</dt>
            <dd class="text-success-600 dark:text-success-500">+ {{ formatRupiah(sessionSummary.total_cash_payments) }}</dd>
          </div>
          <div class="flex justify-between border-t border-gray-100 pt-2 text-base dark:border-gray-800">
            <dt class="font-medium text-gray-700 dark:text-gray-300">Seharusnya di Laci</dt>
            <dd class="font-bold text-brand-600 dark:text-brand-400">{{ formatRupiah(sessionSummary.expected_balance) }}</dd>
          </div>
        </dl>

        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Uang Fisik di Laci</label>
        <div class="relative">
          <span class="absolute top-1/2 left-4 -translate-y-1/2 text-sm text-gray-500">Rp</span>
          <input type="number" min="0" v-model.number="actualBalance" placeholder="Masukkan hasil hitung uang..." :class="[inputClass, 'pl-11']" />
        </div>

        <template v-if="typeof actualBalance === 'number'">
          <p v-if="closeDifference === 0" class="mt-3 flex items-center gap-2 text-sm text-success-600 dark:text-success-500">
            <CircleCheckBig class="h-4 w-4" />
            Kas cocok, tidak ada selisih.
          </p>
          <div v-else class="mt-3 rounded-xl bg-warning-50 p-4 dark:bg-warning-500/10">
            <p class="mb-2 text-sm font-medium text-warning-700 dark:text-warning-500">
              Selisih kas {{ closeDifference > 0 ? 'lebih' : 'kurang' }} {{ formatRupiah(Math.abs(closeDifference)) }}
            </p>
            <label class="mb-1.5 block text-sm text-gray-700 dark:text-gray-300">Wajib isi alasan selisih</label>
            <textarea
              v-model="closeNotes"
              rows="2"
              placeholder="Jelaskan alasan selisih..."
              class="w-full rounded-lg border border-warning-300 bg-white px-4 py-2.5 text-sm text-gray-800 focus:outline-hidden focus:ring-3 focus:ring-warning-500/10 dark:border-warning-700 dark:bg-gray-900 dark:text-white/90"
            ></textarea>
          </div>
        </template>

        <p v-if="sessionError" class="mt-3 text-sm text-error-600 dark:text-error-500">{{ sessionError }}</p>

        <div class="mt-6 flex gap-3">
          <button
            @click="showCloseSessionModal = false"
            class="flex-1 rounded-lg px-5 py-3 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
          >
            Batal
          </button>
          <button
            @click="closeSession"
            :disabled="isSubmittingSession || typeof actualBalance !== 'number' || (closeDifference !== 0 && !closeNotes)"
            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-error-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <LoaderCircle v-if="isSubmittingSession" class="h-4 w-4 animate-spin" />
            {{ isSubmittingSession ? 'Menutup...' : 'Tutup Kasir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
