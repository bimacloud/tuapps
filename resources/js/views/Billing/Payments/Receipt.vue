<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const paymentId = route.params.id
const payment = ref<any>(null)
const settings = ref<any>({})
const loading = ref(true)

const fetchReceipt = async () => {
  try {
    const res = await axios.get(`/api/v1/payments/${paymentId}/receipt`)
    payment.value = res.data.data
    settings.value = res.data.settings || {}
    // Otomatis print jika sudah terload
    setTimeout(() => {
      window.print()
    }, 500)
  } catch (error) {
    console.error('Error fetching receipt', error)
  } finally {
    loading.value = false
  }
}

const formatRupiah = (number: number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number)
}

const formatDate = (dateString: string) => {
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(dateString))
}

onMounted(() => {
  fetchReceipt()
})
</script>

<template>
  <div v-if="loading" class="min-h-screen flex items-center justify-center bg-gray-100">
    <p>Menyiapkan kuitansi...</p>
  </div>
  
  <div v-else-if="payment" class="receipt-container bg-white text-black min-h-screen">
    <!-- Non-print action bar -->
    <div class="print:hidden bg-gray-800 text-white p-4 flex justify-between items-center no-print-bar">
      <div>
        <h1 class="font-bold text-lg">Pratinjau Kuitansi</h1>
        <p class="text-sm text-gray-300">Gunakan (Ctrl+P) untuk mencetak</p>
      </div>
      <button @click="() => window.print()" class="bg-primary hover:bg-opacity-90 px-4 py-2 rounded text-white font-medium">
        Cetak Sekarang
      </button>
    </div>

    <!-- Receipt Paper -->
    <div class="max-w-3xl mx-auto bg-white p-8 sm:p-12 shadow-none sm:shadow-lg mt-0 sm:mt-8 print:shadow-none print:mt-0 print:p-0">
      
      <!-- Header Sekolah -->
      <div class="flex items-center justify-between border-b-2 border-black pb-4 mb-6">
        <div class="flex items-center gap-4">
          <!-- Logo -->
          <div v-if="settings.school_logo" class="w-16 h-16 rounded-full flex items-center justify-center overflow-hidden">
            <img :src="settings.school_logo" alt="Logo" class="w-full h-full object-cover" />
          </div>
          <div v-else class="w-16 h-16 bg-gray-200 border border-gray-400 rounded-full flex items-center justify-center">
            <span class="text-xs font-bold text-gray-500">LOGO</span>
          </div>
          <div>
            <h1 class="text-2xl font-bold uppercase tracking-wide">{{ settings.school_name || 'SMK TIK Wates' }}</h1>
            <p class="text-sm">{{ settings.school_address || 'Jl. Raya Wates No. 123, Kabupaten Kulon Progo, DIY' }}</p>
            <p class="text-sm">Telp: {{ settings.school_phone || '(0274) 123456' }} | Email: {{ settings.school_email || 'info@smktikwates.sch.id' }}</p>
          </div>
        </div>
        <div class="text-right">
          <h2 class="text-xl font-bold uppercase text-gray-700">KUITANSI</h2>
          <p class="text-sm font-medium mt-1">{{ payment.transaction_number }}</p>
        </div>
      </div>

      <!-- Info Siswa & Transaksi -->
      <div class="grid grid-cols-2 gap-8 mb-8 text-sm">
        <div>
          <h3 class="font-bold border-b border-gray-300 mb-2 pb-1">Telah terima dari:</h3>
          <table class="w-full">
            <tbody>
              <tr>
                <td class="py-1 w-24">Nama Siswa</td>
                <td class="py-1 px-2">:</td>
                <td class="py-1 font-semibold uppercase">{{ payment.student?.name }}</td>
              </tr>
              <tr>
                <td class="py-1">NIS/NISN</td>
                <td class="py-1 px-2">:</td>
                <td class="py-1">{{ payment.student?.nis }} / {{ payment.student?.nisn || '-' }}</td>
              </tr>
              <tr>
                <td class="py-1">Kelas/Jurusan</td>
                <td class="py-1 px-2">:</td>
                <td class="py-1">{{ payment.student?.class_room?.name }} - {{ payment.student?.class_room?.major?.name }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div>
          <h3 class="font-bold border-b border-gray-300 mb-2 pb-1">Detail Transaksi:</h3>
          <table class="w-full">
            <tbody>
              <tr>
                <td class="py-1 w-24">Tanggal Bayar</td>
                <td class="py-1 px-2">:</td>
                <td class="py-1">{{ formatDate(payment.payment_date) }}</td>
              </tr>
              <tr>
                <td class="py-1">Metode</td>
                <td class="py-1 px-2">:</td>
                <td class="py-1">{{ payment.payment_method?.name }}</td>
              </tr>
              <tr>
                <td class="py-1">Status</td>
                <td class="py-1 px-2">:</td>
                <td class="py-1 font-bold">{{ payment.status }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tabel Rincian -->
      <div class="mb-8">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-gray-100 border-y-2 border-black">
              <th class="py-2 px-2 text-left w-10">No</th>
              <th class="py-2 px-2 text-left">Guna Membayar</th>
              <th class="py-2 px-2 text-left">Keterangan</th>
              <th class="py-2 px-2 text-right">Nominal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in payment.items" :key="item.id" class="border-b border-gray-200 border-dashed">
              <td class="py-3 px-2">{{ index + 1 }}</td>
              <td class="py-3 px-2 font-medium">
                {{ item.invoice_item?.payment_type?.name }} 
                <span v-if="item.invoice_item?.period">({{ item.invoice_item?.period }})</span>
              </td>
              <td class="py-3 px-2">{{ item.invoice_item?.description || '-' }}</td>
              <td class="py-3 px-2 text-right">{{ formatRupiah(item.amount) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-y-2 border-black font-bold">
              <td colspan="3" class="py-3 px-2 text-right uppercase">Total Pembayaran</td>
              <td class="py-3 px-2 text-right text-lg">{{ formatRupiah(payment.total_amount) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Tanda Tangan -->
      <div class="flex justify-between items-end text-sm mt-12">
        <div class="text-center w-48">
          <p class="mb-16">Penyetor,</p>
          <p class="font-bold border-b border-black pb-1">{{ payment.student?.name }}</p>
          <p class="mt-1">Siswa/Wali</p>
        </div>

        <!-- Placeholder for QR Code (optional) -->
        <div class="w-24 h-24 border-2 border-gray-300 border-dashed flex items-center justify-center p-1">
          <div class="text-center leading-tight">
            <span class="text-[10px] text-gray-400 block">Scan to<br>Verify</span>
          </div>
        </div>

        <div class="text-center w-48">
          <p class="mb-16">Wates, {{ new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(payment.payment_date)) }}<br>Penerima / Kasir,</p>
          <p class="font-bold border-b border-black pb-1">{{ payment.cashier?.name }}</p>
          <p class="mt-1">Bendahara Sekolah</p>
        </div>
      </div>
      
      <!-- Footer Note -->
      <div class="mt-8 text-xs text-gray-500 italic text-center whitespace-pre-line">
        {{ settings.receipt_footer || "* Kuitansi ini adalah bukti pembayaran yang sah. Harap disimpan dengan baik.\nDicetak dari Sistem Keuangan SMK TIK Wates." }}
      </div>
    </div>
  </div>
  
  <div v-else class="min-h-screen flex items-center justify-center bg-gray-100">
    <p class="text-danger">Kuitansi tidak ditemukan.</p>
  </div>
</template>

<style scoped>
@media print {
  @page {
    size: A4 portrait;
    margin: 1cm;
  }
  
  body * {
    visibility: hidden;
  }
  
  .receipt-container, .receipt-container * {
    visibility: visible;
  }
  
  .no-print-bar {
    display: none !important;
  }
  
  .receipt-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    background-color: white;
  }
}
</style>
