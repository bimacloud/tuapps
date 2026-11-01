<template>
  <AdminLayout>
    <PageHeader
      title="Persetujuan"
      description="Permintaan dari bendahara yang menunggu keputusan Kepala Sekolah. Setiap keputusan tercatat di Audit Log."
    />

    <section v-if="loading" class="panel">
      <DataState state="loading" message="Mengambil daftar permintaan." />
    </section>
    <section v-else-if="loadError" class="panel">
      <DataState state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData()">Coba lagi</button>
      </DataState>
    </section>

    <template v-else>
      <section aria-labelledby="pending-title">
        <h2 id="pending-title" class="mb-3 text-2xl font-bold">
          Menunggu keputusan
          <span class="font-sans text-base font-semibold text-tinta-2">({{ pendingApprovals.length }})</span>
        </h2>

        <div v-if="pendingApprovals.length === 0" class="panel">
          <DataState
            state="empty"
            compact
            title="Tidak ada permintaan yang menunggu."
            message="Permintaan baru muncul di sini setelah bendahara mengajukan pembatalan pembayaran dari Riwayat Pembayaran."
          >
            <button type="button" class="btn btn-secondary" @click="fetchData()">Muat ulang</button>
          </DataState>
        </div>

        <ul v-else class="space-y-4">
          <li v-for="approval in pendingApprovals" :key="approval.id" class="panel px-5 py-5 sm:px-6">
            <article :aria-labelledby="`approval-${approval.id}-title`">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                  <h3 :id="`approval-${approval.id}-title`" class="text-xl font-bold leading-tight">
                    {{ typeLabel(approval.type).title }}
                  </h3>
                  <p class="mt-1 text-sm text-tinta-2">
                    Diajukan {{ approval.requester?.name || 'pengguna tidak dikenal' }}, {{ formatDateTime(approval.created_at) }}
                  </p>
                </div>
                <StatusTag :status="approval.status" />
              </div>

              <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 rounded-[var(--radius-kontrol)] bg-krem px-4 py-3 sm:grid-cols-3">
                <template v-for="row in recordDetails(approval)" :key="row.label">
                  <div class="min-w-0">
                    <dt class="text-sm text-tinta-2">{{ row.label }}</dt>
                    <dd class="break-words font-semibold" :class="row.money ? 'font-display text-xl tabular-nums' : ''">
                      {{ row.value }}
                    </dd>
                  </div>
                </template>
              </dl>

              <div class="mt-4">
                <p class="text-sm font-semibold">Alasan pemohon</p>
                <p class="mt-1 whitespace-pre-line">{{ approval.reason || 'Pemohon tidak menulis alasan.' }}</p>
              </div>

              <p
                v-if="isOwnRequest(approval)"
                class="mt-4 rounded-[var(--radius-kontrol)] bg-amber-muda px-3 py-2 text-sm font-semibold"
              >
                Permintaan ini Anda ajukan sendiri, jadi keputusannya harus diambil akun Kepala Sekolah lain.
              </p>

              <div v-else class="mt-5 border-t border-garis pt-4">
                <label :for="`note-${approval.id}`" class="field-label">Catatan keputusan</label>
                <textarea
                  :id="`note-${approval.id}`"
                  v-model="notes[approval.id]"
                  rows="2"
                  class="field-input"
                  :aria-invalid="noteErrors[approval.id] ? 'true' : undefined"
                  :aria-describedby="noteErrors[approval.id] ? `note-${approval.id}-error` : `note-${approval.id}-hint`"
                  @input="noteErrors[approval.id] = ''"
                ></textarea>
                <p v-if="noteErrors[approval.id]" :id="`note-${approval.id}-error`" class="field-error">
                  {{ noteErrors[approval.id] }}
                </p>
                <p v-else :id="`note-${approval.id}-hint`" class="field-hint">
                  Boleh kosong saat menyetujui. Wajib diisi saat menolak.
                </p>

                <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                  <button
                    type="button"
                    class="btn btn-danger"
                    :disabled="busyId !== null"
                    @click="rejectRequest(approval)"
                  >
                    {{ busyId === approval.id && busyAction === 'reject' ? 'Menolak...' : typeLabel(approval.type).reject }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="busyId !== null"
                    @click="approveRequest(approval)"
                  >
                    {{ busyId === approval.id && busyAction === 'approve' ? 'Menyetujui...' : typeLabel(approval.type).approve }}
                  </button>
                </div>
              </div>
            </article>
          </li>
        </ul>
      </section>

      <section class="mt-10" aria-labelledby="history-title">
        <h2 id="history-title" class="mb-3 text-2xl font-bold">Riwayat keputusan</h2>
        <div class="panel overflow-hidden">
          <DataState
            v-if="historyApprovals.length === 0"
            state="empty"
            compact
            title="Belum ada keputusan."
            message="Permintaan yang sudah disetujui atau ditolak akan tercatat di sini."
          />
          <div v-else class="overflow-x-auto">
            <table class="table-data">
              <thead>
                <tr>
                  <th scope="col">Diputuskan</th>
                  <th scope="col">Permintaan</th>
                  <th scope="col">Pemohon</th>
                  <th scope="col">Keputusan</th>
                  <th scope="col">Oleh</th>
                  <th scope="col" class="min-w-[14rem]">Catatan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="approval in historyApprovals" :key="approval.id">
                  <td class="whitespace-nowrap">{{ formatDateTime(approval.reviewed_at) }}</td>
                  <td>
                    <span class="font-semibold">{{ typeLabel(approval.type).title }}</span>
                    <span v-if="recordSummary(approval)" class="block text-sm text-tinta-2">{{ recordSummary(approval) }}</span>
                  </td>
                  <td>{{ approval.requester?.name || '-' }}</td>
                  <td><StatusTag :status="approval.status" /></td>
                  <td>{{ approval.approver?.name || '-' }}</td>
                  <td class="whitespace-pre-line">{{ approval.review_note || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </template>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotice } from '@/composables/useNotice'
import { errorMessage, formatDateTime, formatRupiah } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'

type Approval = Record<string, any>

const authStore = useAuthStore()
const notice = useNotice()

const approvals = ref<Approval[]>([])
const loading = ref(true)
const loadError = ref('')
const notes = reactive<Record<number, string>>({})
const noteErrors = reactive<Record<number, string>>({})
const busyId = ref<number | null>(null)
const busyAction = ref<'approve' | 'reject' | null>(null)

const typeLabels: Record<string, { title: string; approve: string; reject: string }> = {
  BATAL_BAYAR: { title: 'Pembatalan pembayaran', approve: 'Setujui pembatalan', reject: 'Tolak pembatalan' },
  UBAH_TARIF: { title: 'Perubahan tarif', approve: 'Setujui perubahan tarif', reject: 'Tolak perubahan tarif' },
}

const typeLabel = (type: string) => {
  const fallback = String(type || 'Permintaan').replace(/_/g, ' ').toLowerCase()
  return typeLabels[type] ?? { title: fallback, approve: 'Setujui permintaan', reject: 'Tolak permintaan' }
}

/** What the request touches, taken only from the linked record the API returns. */
const recordDetails = (approval: Approval) => {
  const record = approval.record
  if (!record) {
    return [{ label: 'Data terkait', value: 'Data asli sudah tidak tersedia.', money: false }]
  }
  if (approval.type === 'BATAL_BAYAR') {
    return [
      { label: 'Nomor transaksi', value: record.transaction_number || '-', money: false },
      { label: 'Jumlah dibayar', value: formatRupiah(record.total_amount), money: true },
      { label: 'Tanggal bayar', value: formatDateTime(record.payment_date || record.created_at), money: false },
    ]
  }
  if (record.amount !== undefined) {
    return [{ label: 'Tarif saat ini', value: formatRupiah(record.amount), money: true }]
  }
  return [{ label: 'Nomor data', value: String(record.id ?? '-'), money: false }]
}

const recordSummary = (approval: Approval) => {
  const record = approval.record
  if (!record) return ''
  if (approval.type === 'BATAL_BAYAR') {
    return `${record.transaction_number || '-'}, ${formatRupiah(record.total_amount)}`
  }
  return record.amount !== undefined ? `Tarif ${formatRupiah(record.amount)}` : ''
}

const isOwnRequest = (approval: Approval) => {
  const userId = (authStore.user as { id?: number } | null)?.id
  return Boolean(userId) && approval.requester_id === userId
}

const pendingApprovals = computed(() =>
  approvals.value
    .filter((approval) => approval.status === 'PENDING')
    .sort((first, second) => new Date(first.created_at).getTime() - new Date(second.created_at).getTime()),
)

const historyApprovals = computed(() => approvals.value.filter((approval) => approval.status !== 'PENDING'))

/** After a decision the list refreshes silently so the page does not jump back to the loading state. */
const fetchData = async (silent = false) => {
  if (!silent) {
    loading.value = true
    loadError.value = ''
  }
  try {
    const response = await axios.get('/api/v1/approvals')
    approvals.value = response.data.data || []
  } catch (error: any) {
    if (silent) {
      notice.error(errorMessage(error, 'Daftar gagal dimuat ulang. Muat ulang halaman ini.'))
      return
    }
    loadError.value =
      error?.response?.status === 403
        ? 'Daftar persetujuan hanya bisa dibuka akun Kepala Sekolah.'
        : errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const decide = async (approval: Approval, action: 'approve' | 'reject', successMessage: string) => {
  busyId.value = approval.id
  busyAction.value = action
  try {
    await axios.post(`/api/v1/approvals/${approval.id}/${action}`, {
      review_note: notes[approval.id] || '',
    })
    delete notes[approval.id]
    notice.success(successMessage)
    await fetchData(true)
  } catch (error) {
    notice.error(errorMessage(error, 'Keputusan gagal disimpan. Coba lagi.'))
  } finally {
    busyId.value = null
    busyAction.value = null
  }
}

const approveRequest = async (approval: Approval) => {
  const label = typeLabel(approval.type)
  const message =
    approval.type === 'BATAL_BAYAR' && approval.record
      ? `Pembayaran ${approval.record.transaction_number} sebesar ${formatRupiah(approval.record.total_amount)} dibatalkan dan tagihan siswa dibuka kembali. Keputusan ini tidak bisa diubah.`
      : 'Permintaan ini disetujui dan tidak bisa diubah lagi.'

  const confirmed = await notice.confirm({
    title: `${label.approve}?`,
    message,
    confirmLabel: label.approve,
  })
  if (!confirmed) return

  await decide(approval, 'approve', `${label.title} disetujui.`)
}

const rejectRequest = async (approval: Approval) => {
  const label = typeLabel(approval.type)
  if (!notes[approval.id]?.trim()) {
    noteErrors[approval.id] = 'Tulis alasan penolakan dulu.'
    document.getElementById(`note-${approval.id}`)?.focus()
    return
  }

  const confirmed = await notice.confirm({
    title: `${label.reject}?`,
    message: 'Permintaan ditolak dan catatan Anda tersimpan di riwayat keputusan. Keputusan ini tidak bisa diubah.',
    confirmLabel: label.reject,
    danger: true,
  })
  if (!confirmed) return

  await decide(approval, 'reject', `${label.title} ditolak.`)
}

onMounted(() => fetchData())
</script>
