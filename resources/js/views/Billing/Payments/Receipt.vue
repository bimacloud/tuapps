<template>
  <div class="halaman min-h-screen">
    <div class="no-print border-b border-garis bg-kertas">
      <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="min-w-0">
          <h1 class="text-2xl font-bold leading-none">Kuitansi</h1>
          <p class="mt-1 text-sm text-tinta-2">
            {{
              payment && !schoolName
                ? 'Nama sekolah belum diisi di Pengaturan, jadi kop kuitansi masih kosong.'
                : 'Periksa isi kuitansi, lalu cetak di kertas A4.'
            }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-secondary" @click="goBack">Kembali</button>
          <button v-if="payment" type="button" class="btn btn-primary" @click="printPage">Cetak kuitansi</button>
        </div>
      </div>
    </div>

    <main class="px-4 py-6">
      <div v-if="loading || loadError || !payment" class="panel mx-auto max-w-3xl">
        <DataState v-if="loading" state="loading" message="Menyiapkan kuitansi." />
        <DataState v-else-if="loadError" state="error" :message="loadError">
          <button type="button" class="btn btn-secondary" @click="fetchReceipt">Coba lagi</button>
          <button type="button" class="btn btn-ghost" @click="goBack">Kembali</button>
        </DataState>
        <DataState
          v-else
          state="empty"
          title="Kuitansi tidak ditemukan."
          message="Data transaksi ini tidak tersedia. Buka kuitansi dari Riwayat Pembayaran."
        >
          <router-link to="/pembayaran" class="btn btn-secondary">Ke riwayat pembayaran</router-link>
        </DataState>
      </div>

      <article v-else class="kuitansi mx-auto max-w-3xl" aria-label="Kuitansi pembayaran">
        <header class="kop">
          <img v-if="schoolLogo && !logoFailed" :src="schoolLogo" alt="" class="kop-logo" @error="logoFailed = true" />
          <div class="kop-sekolah">
            <p v-if="schoolName" class="kop-nama">{{ schoolName }}</p>
            <p v-if="schoolAddress">{{ schoolAddress }}</p>
            <p v-if="contactLine">{{ contactLine }}</p>
          </div>
          <div class="kop-judul">
            <p class="judul">Kuitansi</p>
            <p>No. {{ payment.transaction_number }}</p>
          </div>
        </header>

        <p v-if="isCancelled" class="cap-batal">Transaksi ini dibatalkan dan tidak berlaku sebagai bukti pembayaran.</p>

        <div class="info">
          <section>
            <h2 class="info-judul">Telah terima dari</h2>
            <dl>
              <div>
                <dt>Nama siswa</dt>
                <dd class="uppercase">{{ payment.student?.name || '-' }}</dd>
              </div>
              <div>
                <dt>NIS / NISN</dt>
                <dd>{{ payment.student?.nis || '-' }} / {{ payment.student?.nisn || '-' }}</dd>
              </div>
              <div>
                <dt>Kelas</dt>
                <dd>{{ classLine || '-' }}</dd>
              </div>
            </dl>
          </section>
          <section>
            <h2 class="info-judul">Rincian transaksi</h2>
            <dl>
              <div>
                <dt>Tanggal bayar</dt>
                <dd>{{ formatLongDateTime(payment.payment_date) }}</dd>
              </div>
              <div>
                <dt>Metode</dt>
                <dd>{{ payment.payment_method?.name || '-' }}</dd>
              </div>
              <div>
                <dt>Status</dt>
                <dd>{{ statusLabel }}</dd>
              </div>
            </dl>
          </section>
        </div>

        <div class="tabel-bungkus">
          <table class="rincian">
            <thead>
              <tr>
                <th scope="col" class="w-10">No</th>
                <th scope="col">Guna membayar</th>
                <th scope="col">Keterangan</th>
                <th scope="col" class="angka">Nominal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in payment.items" :key="item.id">
                <td>{{ index + 1 }}</td>
                <td class="font-semibold">
                  {{ item.invoice_item?.payment_type?.name }}
                  <span v-if="item.invoice_item?.period" class="font-normal">({{ item.invoice_item.period }})</span>
                </td>
                <td>
                  <template v-if="lineFor(item)">
                    <strong v-if="lineFor(item).remaining_after <= 0">LUNAS</strong>
                    <span v-else>Cicilan, sisa {{ formatRupiah(lineFor(item).remaining_after) }}</span>
                  </template>
                  <span v-else>{{ item.invoice_item?.description || '-' }}</span>
                </td>
                <td class="angka">{{ formatRupiah(item.amount) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <th scope="row" colspan="3" class="text-right">Total pembayaran</th>
                <td class="angka total">{{ formatRupiah(payment.total_amount) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="ttd">
          <div>
            <p><span class="invisible" aria-hidden="true">{{ formatLongDate(payment.payment_date) }}</span><br />Penyetor,</p>
            <div class="ttd-ruang"></div>
            <p class="ttd-nama">{{ payment.student?.name || '-' }}</p>
            <p>Siswa / wali</p>
          </div>
          <div>
            <p>{{ formatLongDate(payment.payment_date) }}<br />Penerima,</p>
            <div class="ttd-ruang"></div>
            <p class="ttd-nama">{{ payment.cashier?.name || '-' }}</p>
            <p>Bendahara</p>
          </div>
        </div>

        <p class="catatan-kaki">{{ footerNote }}</p>
      </article>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useSchoolStore } from '@/stores/school'
import { errorMessage, formatRupiah } from '@/utils/format'
import DataState from '@/components/app/DataState.vue'

const route = useRoute()
const router = useRouter()
const schoolStore = useSchoolStore()

const paymentId = route.params.id
const payment = ref<any>(null)
/** Remaining balance per bill right after this payment (from the API), so an old instalment receipt never shows LUNAS. */
const cardLines = ref<any[]>([])
const lineFor = (item: any) => cardLines.value.find((line) => line.payment_item_id === item.id)
const settings = ref<any>({})
const loading = ref(true)
const loadError = ref('')
const logoFailed = ref(false)

const tanggalPanjang = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
const tanggalJamPanjang = new Intl.DateTimeFormat('id-ID', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

const formatLongDate = (value: string | null | undefined) => (value ? tanggalPanjang.format(new Date(value)) : '-')
const formatLongDateTime = (value: string | null | undefined) => (value ? tanggalJamPanjang.format(new Date(value)) : '-')

/** School identity comes from Pengaturan (school store); the receipt endpoint's settings are the fallback. */
const schoolSettings = computed<Record<string, string>>(() => schoolStore.settings || {})
const schoolName = computed(() => schoolStore.name || settings.value.school_name || '')
const schoolAddress = computed(() => schoolStore.address || settings.value.school_address || '')
const schoolLogo = computed(() => schoolStore.logo || settings.value.school_logo || '')
const contactLine = computed(() => {
  const phone = schoolSettings.value.school_phone || settings.value.school_phone
  const email = schoolSettings.value.school_email || settings.value.school_email
  return [phone ? `Telp. ${phone}` : '', email ? `Email ${email}` : ''].filter(Boolean).join(', ')
})
const footerNote = computed(
  () => settings.value.receipt_footer || 'Kuitansi ini adalah bukti pembayaran yang sah. Simpan dengan baik.',
)

const classLine = computed(() =>
  [payment.value?.student?.class_room?.name, payment.value?.student?.class_room?.major?.name].filter(Boolean).join(', '),
)

const statusLabels: Record<string, string> = {
  SUCCESS: 'Berhasil',
  CANCELLED: 'Dibatalkan',
  VOID: 'Dibatalkan',
  PENDING: 'Menunggu',
}
const statusLabel = computed(() => statusLabels[payment.value?.status] || payment.value?.status || '-')
const isCancelled = computed(() => ['CANCELLED', 'VOID'].includes(payment.value?.status))

const fetchReceipt = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const [res] = await Promise.all([axios.get(`/api/v1/payments/${paymentId}/receipt`), schoolStore.loadProfile()])
    payment.value = res.data.data
    settings.value = res.data.settings || {}
    cardLines.value = res.data.card_lines || []
    // Print automatically once the receipt is on screen.
    setTimeout(() => {
      window.print()
    }, 500)
  } catch (error: any) {
    loadError.value =
      error?.response?.status === 404
        ? 'Kuitansi tidak ditemukan. Transaksinya mungkin sudah dihapus atau alamatnya salah.'
        : errorMessage(error, 'Kuitansi gagal dimuat. Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const printPage = () => {
  window.print()
}

/** The receipt usually opens in a new tab, so there may be no in-app page to go back to. */
const goBack = () => {
  if (window.history.state?.back) {
    router.back()
  } else {
    router.push('/pembayaran')
  }
}

onMounted(() => {
  fetchReceipt()
})
</script>

<style scoped>
.kuitansi {
  background: #fff;
  color: #000;
  border: 1px solid var(--color-garis);
  border-radius: var(--radius-panel);
  padding: 2rem;
  font-size: 0.95rem;
  line-height: 1.45;
}

.kop {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1.5px solid #000;
}
.kop-logo {
  width: 64px;
  height: 64px;
  object-fit: contain;
  flex-shrink: 0;
}
.kop-sekolah {
  flex: 1 1 16rem;
  min-width: 0;
  font-size: 0.875rem;
}
.kop-nama {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.1;
  text-transform: uppercase;
}
.kop-judul {
  margin-left: auto;
  text-align: right;
  font-size: 0.875rem;
}
.judul {
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
  text-transform: uppercase;
}

.cap-batal {
  margin-top: 1rem;
  padding: 0.5rem 0.75rem;
  border: 1.5px solid #000;
  font-weight: 700;
  text-align: center;
}

.info {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem 2rem;
  margin: 1.25rem 0;
}
.info-judul {
  font-family: var(--font-sans);
  font-size: 0.9rem;
  font-weight: 700;
  padding-bottom: 0.25rem;
  margin-bottom: 0.375rem;
  border-bottom: 1px solid #000;
}
.info dl > div {
  display: grid;
  grid-template-columns: 7rem minmax(0, 1fr);
  gap: 0.5rem;
  padding: 0.125rem 0;
}
.info dd {
  font-weight: 600;
  overflow-wrap: anywhere;
}

.tabel-bungkus {
  overflow-x: auto;
}
.rincian {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.rincian th,
.rincian td {
  padding: 0.45rem 0.5rem;
  text-align: left;
  vertical-align: top;
}
.rincian thead th {
  font-weight: 700;
  border-top: 1.5px solid #000;
  border-bottom: 1px solid #000;
  white-space: nowrap;
}
.rincian tbody td {
  border-bottom: 1px solid #999;
}
.rincian tfoot th,
.rincian tfoot td {
  font-weight: 700;
  border-top: 1px solid #000;
  border-bottom: 1.5px solid #000;
}
.rincian tfoot th {
  text-align: right;
}
.rincian .angka {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.rincian .total {
  font-size: 1.1rem;
}

.ttd {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-top: 2.5rem;
  text-align: center;
  font-size: 0.9rem;
}
.ttd-ruang {
  height: 4.5rem;
}
.ttd-nama {
  display: inline-block;
  min-width: min(12rem, 100%);
  padding-bottom: 0.125rem;
  border-bottom: 1px solid #000;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.catatan-kaki {
  margin-top: 2rem;
  font-size: 0.8rem;
  text-align: center;
  white-space: pre-line;
}

@media (min-width: 640px) {
  .info {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 639px) {
  .kuitansi {
    padding: 1.25rem;
  }
}

@media print {
  @page {
    size: A4 portrait;
    margin: 12mm;
  }

  .halaman {
    min-height: 0;
  }
  .halaman main {
    padding: 0;
  }
  .kuitansi {
    max-width: none;
    border: 0;
    border-radius: 0;
    padding: 0;
  }
  .info {
    grid-template-columns: 1fr 1fr;
  }
  .tabel-bungkus {
    overflow: visible;
  }
}
</style>
