<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const approvals = ref<any[]>([])
const loading = ref(true)

const selectedApproval = ref<any>(null)
const showReviewModal = ref(false)
const reviewNote = ref('')
const isSubmitting = ref(false)

const fetchData = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/approvals')
    approvals.value = res.data.data
  } catch (error) {
    console.error('Error fetching approvals', error)
  } finally {
    loading.value = false
  }
}

const formatDate = (dateString: string) => {
  if (!dateString) return '-'
  return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(dateString))
}

const openReviewModal = (approval: any) => {
  selectedApproval.value = approval
  reviewNote.value = ''
  showReviewModal.value = true
}

const approveRequest = async () => {
  isSubmitting.value = true
  try {
    await axios.post(`/api/v1/approvals/${selectedApproval.value.id}/approve`, {
      review_note: reviewNote.value
    })
    alert('Permintaan disetujui.')
    showReviewModal.value = false
    fetchData()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menyetujui permintaan')
  } finally {
    isSubmitting.value = false
  }
}

const rejectRequest = async () => {
  if (!reviewNote.value) {
    alert('Catatan review wajib diisi untuk penolakan.')
    return
  }
  isSubmitting.value = true
  try {
    await axios.post(`/api/v1/approvals/${selectedApproval.value.id}/reject`, {
      review_note: reviewNote.value
    })
    alert('Permintaan ditolak.')
    showReviewModal.value = false
    fetchData()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menolak permintaan')
  } finally {
    isSubmitting.value = false
  }
}

const pendingApprovals = computed(() => {
  return approvals.value.filter(a => a.status === 'PENDING')
})

const historyApprovals = computed(() => {
  return approvals.value.filter(a => a.status !== 'PENDING')
})

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="text-title-md2 font-semibold text-black dark:text-white">Persetujuan (Approval)</h2>
    </div>

    <!-- Menunggu Persetujuan -->
    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark mb-8">
      <div class="border-b border-stroke py-4 px-6.5 dark:border-strokedark">
        <h3 class="font-medium text-black dark:text-white">
          Menunggu Persetujuan
        </h3>
      </div>
      <div class="p-4 md:p-6 xl:p-7.5">
        <div class="overflow-x-auto">
          <table class="w-full table-auto text-sm">
            <thead>
              <tr class="bg-gray-2 text-left dark:bg-meta-4">
                <th class="py-4 px-4 font-medium text-black dark:text-white">Tanggal</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Pemohon</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Jenis</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white w-1/3">Alasan</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="5" class="text-center py-8">Memuat data...</td>
              </tr>
              <tr v-else-if="pendingApprovals.length === 0">
                <td colspan="5" class="text-center py-8 text-gray-500">Tidak ada permintaan yang menunggu.</td>
              </tr>
              <tr v-else v-for="approval in pendingApprovals" :key="approval.id" class="border-b border-stroke dark:border-strokedark">
                <td class="py-4 px-4">{{ formatDate(approval.created_at) }}</td>
                <td class="py-4 px-4">{{ approval.requester?.name }}</td>
                <td class="py-4 px-4 font-medium">{{ approval.type.replace('_', ' ') }}</td>
                <td class="py-4 px-4">{{ approval.reason }}</td>
                <td class="py-4 px-4 text-center">
                  <button @click="openReviewModal(approval)" class="inline-flex items-center justify-center rounded-md bg-primary py-2 px-4 text-center font-medium text-white hover:bg-opacity-90">
                    Review
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Riwayat Persetujuan -->
    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
      <div class="border-b border-stroke py-4 px-6.5 dark:border-strokedark">
        <h3 class="font-medium text-black dark:text-white">
          Riwayat Keputusan
        </h3>
      </div>
      <div class="p-4 md:p-6 xl:p-7.5">
        <div class="overflow-x-auto">
          <table class="w-full table-auto text-sm">
            <thead>
              <tr class="bg-gray-2 text-left dark:bg-meta-4">
                <th class="py-4 px-4 font-medium text-black dark:text-white">Tanggal</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Jenis</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white">Status</th>
                <th class="py-4 px-4 font-medium text-black dark:text-white w-1/3">Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="historyApprovals.length === 0">
                <td colspan="4" class="text-center py-8 text-gray-500">Belum ada riwayat.</td>
              </tr>
              <tr v-else v-for="approval in historyApprovals" :key="approval.id" class="border-b border-stroke dark:border-strokedark">
                <td class="py-4 px-4">{{ formatDate(approval.reviewed_at) }}</td>
                <td class="py-4 px-4 font-medium">{{ approval.type.replace('_', ' ') }}</td>
                <td class="py-4 px-4">
                  <span class="inline-flex rounded-full bg-opacity-10 py-1 px-3 text-xs font-medium" :class="approval.status === 'APPROVED' ? 'bg-success text-success' : 'bg-danger text-danger'">
                    {{ approval.status }}
                  </span>
                </td>
                <td class="py-4 px-4">{{ approval.review_note || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Review Modal -->
    <div v-if="showReviewModal && selectedApproval" class="fixed inset-0 z-999999 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
      <div class="w-full max-w-lg rounded-lg bg-white p-8 dark:bg-boxdark shadow-lg border border-stroke dark:border-strokedark">
        <h3 class="mb-4 text-xl font-bold text-black dark:text-white">Review {{ selectedApproval.type.replace('_', ' ') }}</h3>
        
        <div class="mb-4 space-y-2 text-sm bg-gray-2 dark:bg-meta-4 p-4 rounded">
          <div class="flex justify-between border-b border-stroke dark:border-strokedark pb-2">
            <span class="text-gray-500">Pemohon:</span>
            <span class="font-medium text-black dark:text-white">{{ selectedApproval.requester?.name }}</span>
          </div>
          <div class="flex justify-between border-b border-stroke dark:border-strokedark pb-2">
            <span class="text-gray-500">Waktu:</span>
            <span class="font-medium text-black dark:text-white">{{ formatDate(selectedApproval.created_at) }}</span>
          </div>
          <div>
            <span class="block text-gray-500 mb-1">Alasan Pemohon:</span>
            <p class="font-medium text-black dark:text-white italic">"{{ selectedApproval.reason }}"</p>
          </div>
        </div>

        <div class="mb-6">
          <label class="mb-2.5 block text-black dark:text-white font-medium">Catatan Keputusan (Opsional untuk setuju, Wajib untuk tolak)</label>
          <textarea v-model="reviewNote" rows="3" class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-primary active:border-primary dark:border-form-strokedark dark:bg-form-input" placeholder="Tambahkan pesan atau alasan..."></textarea>
        </div>
        
        <div class="flex justify-end gap-3">
          <button @click="showReviewModal = false" class="inline-flex items-center justify-center rounded border border-stroke py-2 px-6 font-medium text-black hover:bg-gray dark:border-strokedark dark:text-white transition">
            Tutup
          </button>
          <button @click="rejectRequest" :disabled="isSubmitting || !reviewNote" class="inline-flex items-center justify-center rounded bg-danger py-2 px-6 font-medium text-white hover:bg-opacity-90 transition disabled:opacity-50">
            Tolak
          </button>
          <button @click="approveRequest" :disabled="isSubmitting" class="inline-flex items-center justify-center rounded bg-success py-2 px-6 font-medium text-white hover:bg-opacity-90 transition disabled:opacity-50">
            Setujui
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
