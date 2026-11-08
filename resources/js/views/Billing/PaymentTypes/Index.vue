<template>
  <admin-layout>
    <PageHeader
      title="Jenis Pembayaran"
      description="Daftar pos pembayaran sekolah. Tarif dan tagihan selalu mengacu ke jenis pembayaran di sini."
    >
      <template v-if="isBendahara" #actions>
        <button type="button" class="btn btn-secondary" :disabled="exporting" @click="exportData">
          {{ exporting ? 'Menyiapkan file...' : 'Unduh data' }}
        </button>
        <button type="button" class="btn btn-secondary" @click="showImportModal = true">Impor dari Excel</button>
        <button type="button" class="btn btn-primary" @click="openModal()">Tambah jenis pembayaran</button>
      </template>
    </PageHeader>

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Mengambil daftar jenis pembayaran." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchData">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        title="Belum ada jenis pembayaran."
        :message="isBendahara ? 'Tambahkan jenis pembayaran dulu, lalu atur tarifnya di menu Tarif Pembayaran.' : 'Bendahara belum menambahkan jenis pembayaran.'"
      >
        <button v-if="isBendahara" type="button" class="btn btn-primary" @click="openModal()">Tambah jenis pembayaran</button>
      </DataState>

      <div v-else class="overflow-x-auto">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col" class="w-28">Kode</th>
              <th scope="col">Nama pembayaran</th>
              <th scope="col" class="w-36">Tipe</th>
              <th scope="col">Kategori</th>
              <th scope="col" class="w-28">Status</th>
              <th v-if="isBendahara" scope="col" class="w-40"><span class="sr-only">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="font-display text-lg font-bold">{{ item.code }}</td>
              <td>
                <span class="font-medium">{{ item.name }}</span>
                <span v-if="item.description" class="mt-0.5 block text-sm text-tinta-2">{{ item.description }}</span>
              </td>
              <td><StatusTag :status="item.type" :label="typeLabel(item.type)" /></td>
              <td :class="item.category ? '' : 'text-tinta-2'">{{ item.category || '-' }}</td>
              <td><StatusTag :status="Boolean(item.status)" /></td>
              <td v-if="isBendahara" class="text-right">
                <div class="flex justify-end gap-1">
                  <button type="button" class="btn btn-ghost btn-sm" @click="openModal(item)">
                    Ubah<span class="sr-only"> {{ item.name }}</span>
                  </button>
                  <button type="button" class="btn btn-ghost btn-sm !text-galat" @click="deleteItem(item)">
                    Hapus<span class="sr-only"> {{ item.name }}</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <BaseModal
      :open="showModal"
      :title="form.id ? 'Ubah jenis pembayaran' : 'Tambah jenis pembayaran'"
      @close="closeModal"
    >
      <form id="payment-type-form" class="space-y-4" @submit.prevent="saveItem">
        <p
          v-if="formError && !hasFieldErrors"
          class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          role="alert"
        >
          {{ formError }}
        </p>

        <div>
          <label for="payment-type-code" class="field-label">Kode</label>
          <input
            id="payment-type-code"
            v-model="form.code"
            type="text"
            required
            maxlength="50"
            class="field-input uppercase"
            placeholder="Singkatan pendek, misalnya SPP"
            :aria-invalid="Boolean(fieldErrors.code)"
            :aria-describedby="fieldErrors.code ? 'payment-type-code-error' : undefined"
          />
          <p v-if="fieldErrors.code" id="payment-type-code-error" class="field-error">{{ fieldErrors.code[0] }}</p>
        </div>

        <div>
          <label for="payment-type-type" class="field-label">Tipe</label>
          <select
            id="payment-type-type"
            v-model="form.type"
            required
            class="field-input"
            :aria-invalid="Boolean(fieldErrors.type)"
            :aria-describedby="fieldErrors.type ? 'payment-type-type-error' : undefined"
          >
            <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <p v-if="fieldErrors.type" id="payment-type-type-error" class="field-error">{{ fieldErrors.type[0] }}</p>
        </div>

        <div>
          <label for="payment-type-name" class="field-label">Nama pembayaran</label>
          <input
            id="payment-type-name"
            v-model="form.name"
            type="text"
            required
            maxlength="255"
            class="field-input"
            placeholder="Nama yang tampil di tagihan dan kartu pembayaran"
            :aria-invalid="Boolean(fieldErrors.name)"
            :aria-describedby="nameDescribedBy"
          />
          <p v-if="fieldErrors.name" id="payment-type-name-error" class="field-error">{{ fieldErrors.name[0] }}</p>
          <p v-if="form.type !== 'BULANAN'" id="payment-type-name-hint" class="field-hint">
            Kartu pembayaran mencocokkan nama ini dengan barisnya: Daftar Ulang, LKS atau Buku (sertakan Semester 1/2
            atau Ganjil/Genap di nama atau periode), ASTS, ASAS, ASAT, PKL, Asesmen Akhir. Nama lain dicetak di baris
            kosong terakhir kartu.
          </p>
        </div>

        <div>
          <label for="payment-type-category" class="field-label">Kategori <span class="font-normal text-tinta-2">(opsional)</span></label>
          <input
            id="payment-type-category"
            v-model="form.category"
            type="text"
            maxlength="255"
            class="field-input"
            placeholder="Pengelompokan bebas, misalnya Wajib"
            :aria-invalid="Boolean(fieldErrors.category)"
            :aria-describedby="fieldErrors.category ? 'payment-type-category-error' : undefined"
          />
          <p v-if="fieldErrors.category" id="payment-type-category-error" class="field-error">{{ fieldErrors.category[0] }}</p>
        </div>

        <div>
          <label for="payment-type-description" class="field-label">Keterangan <span class="font-normal text-tinta-2">(opsional)</span></label>
          <textarea
            id="payment-type-description"
            v-model="form.description"
            rows="2"
            class="field-input"
            placeholder="Catatan untuk staf TU"
            :aria-invalid="Boolean(fieldErrors.description)"
            :aria-describedby="fieldErrors.description ? 'payment-type-description-error' : undefined"
          ></textarea>
          <p v-if="fieldErrors.description" id="payment-type-description-error" class="field-error">{{ fieldErrors.description[0] }}</p>
        </div>

        <label class="flex min-h-11 items-center gap-3">
          <input v-model="form.status" type="checkbox" class="field-check" />
          <span class="font-medium">Jenis pembayaran aktif</span>
        </label>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="closeModal">Batal</button>
        <button type="submit" form="payment-type-form" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan jenis pembayaran' }}
        </button>
      </template>
    </BaseModal>

    <ImportExcelModal
      :open="showImportModal"
      dataset="payment-types"
      title="Jenis Pembayaran"
      :notes="['Jenis dengan kode yang sudah ada akan diperbarui.']"
      @close="showImportModal = false"
      @imported="fetchData"
    />
  </admin-layout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotice } from '@/composables/useNotice'
import { errorMessage } from '@/utils/format'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'
import ImportExcelModal from '@/components/app/ImportExcelModal.vue'
import { downloadFile } from '@/utils/download'
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const authStore = useAuthStore()
const notice = useNotice()
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

/** Values accepted by PaymentTypeController validation. */
const typeOptions = [
  { value: 'BULANAN', label: 'Bulanan (Juli sampai Juni)' },
  { value: 'SEMESTER', label: 'Semester' },
  { value: 'TAHUNAN', label: 'Tahunan' },
  { value: 'KHUSUS', label: 'Khusus' },
]

const typeLabels = { BULANAN: 'Bulanan', SEMESTER: 'Semester', TAHUNAN: 'Tahunan', KHUSUS: 'Khusus' }
const typeLabel = (type) => typeLabels[type] ?? type

const items = ref([])
const loading = ref(true)
const loadError = ref('')
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const fieldErrors = ref({})

const hasFieldErrors = computed(() => Object.keys(fieldErrors.value).length > 0)

const nameDescribedBy = computed(() => {
  const ids = []
  if (fieldErrors.value.name) ids.push('payment-type-name-error')
  if (form.value.type !== 'BULANAN') ids.push('payment-type-name-hint')
  return ids.length ? ids.join(' ') : undefined
})

const emptyForm = () => ({ id: null, code: '', name: '', category: '', type: 'BULANAN', description: '', status: true })
const form = ref(emptyForm())

const fetchData = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await axios.get('/api/v1/payment-types')
    items.value = response.data.data
  } catch (error) {
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  formError.value = ''
  fieldErrors.value = {}
  form.value = item ? { ...item } : emptyForm()
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}

  try {
    if (form.value.id) {
      await axios.put(`/api/v1/payment-types/${form.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/payment-types', form.value)
    }
    notice.success(`Jenis pembayaran ${form.value.name} tersimpan.`)
    closeModal()
    fetchData()
  } catch (error) {
    fieldErrors.value = error.response?.data?.errors ?? {}
    formError.value = errorMessage(error, 'Jenis pembayaran gagal disimpan.')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (item) => {
  const confirmed = await notice.confirm({
    title: 'Hapus jenis pembayaran?',
    message: `${item.code} (${item.name}) akan dihapus. Jenis yang sudah punya tarif tidak bisa dihapus.`,
    confirmLabel: 'Hapus jenis pembayaran',
    danger: true,
  })
  if (!confirmed) return

  try {
    await axios.delete(`/api/v1/payment-types/${item.id}`)
    notice.success(`Jenis pembayaran ${item.code} dihapus.`)
    fetchData()
  } catch (error) {
    notice.error(errorMessage(error, 'Jenis pembayaran gagal dihapus.'))
  }
}

// Excel export and import in the same format as the template on the Setup Awal page.
const showImportModal = ref(false)
const exporting = ref(false)
const exportData = async () => {
  exporting.value = true
  try {
    await downloadFile('/api/v1/setup/payment-types/export', 'data-payment-types.xlsx')
  } catch {
    notice.error('Data jenis pembayaran gagal diunduh. Coba lagi.')
  } finally {
    exporting.value = false
  }
}

onMounted(fetchData)
</script>
