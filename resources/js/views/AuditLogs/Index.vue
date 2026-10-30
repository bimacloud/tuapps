<template>
  <AdminLayout>
    <PageHeader
      title="Audit Log"
      description="Jejak tindakan penting di aplikasi: pembayaran, buka dan tutup kas, serta keputusan persetujuan."
    />

    <form class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-[repeat(2,minmax(0,16rem))_auto] sm:items-end" @submit.prevent>
      <div>
        <label for="filter-module" class="field-label">Modul</label>
        <select id="filter-module" v-model="filterModule" class="field-input">
          <option value="">Semua modul</option>
          <option v-for="(label, value) in moduleLabels" :key="value" :value="value">{{ label }}</option>
        </select>
      </div>
      <div>
        <label for="filter-date" class="field-label">Tanggal</label>
        <input id="filter-date" v-model="filterDate" type="date" class="field-input" />
      </div>
      <div v-if="hasFilters">
        <button type="button" class="btn btn-ghost" @click="clearFilters">Hapus filter</button>
      </div>
    </form>

    <section class="panel overflow-hidden" aria-label="Daftar catatan aktivitas">
      <DataState v-if="loading" state="loading" message="Mengambil catatan aktivitas." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData(currentPage)">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="logs.length === 0"
        state="empty"
        :title="hasFilters ? 'Tidak ada catatan untuk filter ini.' : 'Belum ada catatan aktivitas.'"
        :message="hasFilters ? 'Ganti modul atau tanggal, atau hapus filter untuk melihat semua catatan.' : 'Catatan muncul setelah ada pembayaran, buka atau tutup kas, atau keputusan persetujuan.'"
      >
        <button v-if="hasFilters" type="button" class="btn btn-secondary" @click="clearFilters">Hapus filter</button>
      </DataState>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="table-data">
            <thead>
              <tr>
                <th scope="col">Waktu</th>
                <th scope="col">Pengguna</th>
                <th scope="col">Tindakan</th>
                <th scope="col">Sasaran</th>
                <th scope="col" class="min-w-[18rem]">Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id">
                <td class="whitespace-nowrap tabular-nums">{{ formatDateTime(log.created_at) }}</td>
                <td class="whitespace-nowrap font-semibold">{{ log.user?.name || 'Sistem' }}</td>
                <td>
                  <span class="block whitespace-nowrap font-semibold">{{ actionLabel(log.action) }}</span>
                  <span class="block text-sm text-tinta-2">{{ moduleLabels[log.module] || log.module }}</span>
                </td>
                <td class="whitespace-nowrap">{{ targetLabel(log) }}</td>
                <td>
                  <p>{{ log.description || '-' }}</p>
                  <details v-if="hasDetails(log)" class="mt-1">
                    <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-semibold text-bata-tua">
                      Lihat rincian
                    </summary>
                    <dl class="mt-1 space-y-2 text-sm">
                      <div v-if="log.old_data">
                        <dt class="font-semibold">Data sebelum</dt>
                        <dd><pre class="max-w-[32rem] overflow-x-auto whitespace-pre-wrap break-all rounded-[var(--radius-kontrol)] bg-krem px-3 py-2 text-[0.8rem]">{{ formatPayload(log.old_data) }}</pre></dd>
                      </div>
                      <div v-if="log.new_data">
                        <dt class="font-semibold">Data sesudah</dt>
                        <dd><pre class="max-w-[32rem] overflow-x-auto whitespace-pre-wrap break-all rounded-[var(--radius-kontrol)] bg-krem px-3 py-2 text-[0.8rem]">{{ formatPayload(log.new_data) }}</pre></dd>
                      </div>
                      <div v-if="log.ip_address">
                        <dt class="font-semibold">Alamat IP</dt>
                        <dd class="text-tinta-2">{{ log.ip_address }}</dd>
                      </div>
                      <div v-if="log.user_agent">
                        <dt class="font-semibold">Perangkat</dt>
                        <dd class="max-w-[32rem] break-words text-tinta-2">{{ log.user_agent }}</dd>
                      </div>
                    </dl>
                  </details>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav
          v-if="lastPage > 1"
          class="flex flex-wrap items-center justify-between gap-3 border-t border-garis px-4 py-3"
          aria-label="Halaman audit log"
        >
          <p class="text-sm text-tinta-2">
            Halaman {{ currentPage }} dari {{ lastPage }}<span v-if="total">, {{ total }} catatan</span>
          </p>
          <div class="flex gap-2">
            <button type="button" class="btn btn-secondary" :disabled="currentPage <= 1" @click="fetchData(currentPage - 1)">
              Sebelumnya
            </button>
            <button type="button" class="btn btn-secondary" :disabled="currentPage >= lastPage" @click="fetchData(currentPage + 1)">
              Berikutnya
            </button>
          </div>
        </nav>
      </template>
    </section>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { errorMessage, formatDateTime } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'

type AuditLog = Record<string, any>

const moduleLabels: Record<string, string> = {
  PAYMENT: 'Pembayaran',
  CASHIER_SESSION: 'Sesi kasir',
  APPROVAL: 'Persetujuan',
  AUTH: 'Login',
}

const actionLabels: Record<string, string> = {
  PAYMENT_CREATED: 'Pembayaran dicatat',
  ONLINE_PAYMENT_SUCCESS: 'Pembayaran online berhasil',
  ONLINE_PAYMENT_REFUND_REQUIRED: 'Pembayaran online perlu dikembalikan',
  CASHIER_OPEN: 'Kas dibuka',
  CASHIER_CLOSE: 'Kas ditutup',
  APPROVAL_REQUESTED: 'Persetujuan diajukan',
  APPROVAL_APPROVED: 'Permintaan disetujui',
  APPROVAL_REJECTED: 'Permintaan ditolak',
}

const modelLabels: Record<string, string> = {
  Payment: 'Pembayaran',
  ApprovalRequest: 'Permintaan',
  CashierSession: 'Sesi kasir',
  Invoice: 'Tagihan',
  User: 'Pengguna',
}

const logs = ref<AuditLog[]>([])
const loading = ref(true)
const loadError = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

const filterModule = ref('')
const filterDate = ref('')
const hasFilters = computed(() => Boolean(filterModule.value || filterDate.value))

const actionLabel = (action: string) => actionLabels[action] ?? String(action || '-').replace(/_/g, ' ').toLowerCase()

const targetLabel = (log: AuditLog) => {
  if (!log.model_type) return '-'
  const basename = String(log.model_type).split('\\').pop() || ''
  const label = modelLabels[basename] ?? basename
  return log.model_id ? `${label} #${log.model_id}` : label
}

const hasDetails = (log: AuditLog) => Boolean(log.old_data || log.new_data || log.ip_address || log.user_agent)

const formatPayload = (payload: unknown) =>
  typeof payload === 'string' ? payload : JSON.stringify(payload, null, 2)

const fetchData = async (page = 1) => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await axios.get('/api/v1/audit-logs', {
      params: {
        page,
        module: filterModule.value,
        date: filterDate.value,
      },
    })
    logs.value = response.data.data || []
    currentPage.value = response.data.current_page || page
    lastPage.value = response.data.last_page || 1
    total.value = response.data.total || 0
  } catch (error: any) {
    loadError.value =
      error?.response?.status === 403
        ? 'Audit log hanya bisa dibuka akun Kepala Sekolah.'
        : errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const clearFilters = () => {
  filterModule.value = ''
  filterDate.value = ''
}

watch([filterModule, filterDate], () => fetchData(1))

onMounted(() => fetchData())
</script>
