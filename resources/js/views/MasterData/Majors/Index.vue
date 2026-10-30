<template>
  <admin-layout>
    <PageHeader
      title="Jurusan"
      description="Kompetensi keahlian yang dipakai saat membuat kelas dan mencatat siswa."
    >
      <template v-if="isBendahara" #actions>
        <button type="button" class="btn btn-primary" @click="openModal()">Tambah jurusan</button>
      </template>
    </PageHeader>

    <section class="panel overflow-hidden">
      <DataState v-if="loading" state="loading" message="Mengambil daftar jurusan." />
      <DataState v-else-if="loadError" state="error" :message="loadError">
        <button type="button" class="btn btn-secondary" @click="fetchItems">Coba lagi</button>
      </DataState>
      <DataState
        v-else-if="items.length === 0"
        state="empty"
        title="Belum ada jurusan."
        :message="isBendahara ? 'Tambahkan jurusan dulu supaya kelas dan siswa bisa dikelompokkan.' : 'Bendahara belum menambahkan jurusan.'"
      >
        <button v-if="isBendahara" type="button" class="btn btn-primary" @click="openModal()">Tambah jurusan</button>
      </DataState>

      <div v-else class="overflow-x-auto">
        <table class="table-data">
          <thead>
            <tr>
              <th scope="col" class="w-28">Kode</th>
              <th scope="col">Nama jurusan</th>
              <th scope="col" class="w-32">Status</th>
              <th v-if="isBendahara" scope="col" class="w-40"><span class="sr-only">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="font-display text-lg font-bold">{{ item.code }}</td>
              <td>{{ item.name }}</td>
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

    <BaseModal :open="showModal" :title="form.id ? 'Ubah jurusan' : 'Tambah jurusan'" @close="closeModal">
      <form id="major-form" class="space-y-4" @submit.prevent="saveItem">
        <p v-if="formError" class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat" role="alert">
          {{ formError }}
        </p>
        <div>
          <label for="major-code" class="field-label">Kode jurusan</label>
          <input id="major-code" v-model="form.code" type="text" required maxlength="20" class="field-input uppercase" placeholder="TKJ" />
          <p class="field-hint">Singkatan yang tampil di nama kelas, misalnya TKJ atau AKL.</p>
        </div>
        <div>
          <label for="major-name" class="field-label">Nama jurusan</label>
          <input id="major-name" v-model="form.name" type="text" required class="field-input" placeholder="Nama lengkap kompetensi keahlian" />
        </div>
        <label class="flex min-h-11 items-center gap-3">
          <input v-model="form.status" type="checkbox" class="field-check" />
          <span class="font-medium">Jurusan aktif</span>
        </label>
      </form>
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="closeModal">Batal</button>
        <button type="submit" form="major-form" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan jurusan' }}
        </button>
      </template>
    </BaseModal>
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
import DataState from '@/components/app/DataState.vue'
import StatusTag from '@/components/app/StatusTag.vue'
import BaseModal from '@/components/app/BaseModal.vue'

const authStore = useAuthStore()
const notice = useNotice()
const isBendahara = computed(() => authStore.hasRole('Bendahara'))

const items = ref([])
const loading = ref(true)
const loadError = ref('')
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')

const emptyForm = () => ({ id: null, code: '', name: '', status: true })
const form = ref(emptyForm())

const fetchItems = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await axios.get('/api/v1/majors')
    items.value = response.data.data
  } catch (error) {
    loadError.value = errorMessage(error, 'Periksa koneksi ke server, lalu coba lagi.')
  } finally {
    loading.value = false
  }
}

const openModal = (item = null) => {
  formError.value = ''
  form.value = item ? { ...item, status: Boolean(item.status) } : emptyForm()
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const saveItem = async () => {
  saving.value = true
  formError.value = ''

  try {
    if (form.value.id) {
      await axios.put(`/api/v1/majors/${form.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/majors', form.value)
    }
    notice.success(`Jurusan ${form.value.code.toUpperCase()} tersimpan.`)
    closeModal()
    fetchItems()
  } catch (error) {
    formError.value = errorMessage(error, 'Jurusan gagal disimpan.')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (item) => {
  const confirmed = await notice.confirm({
    title: 'Hapus jurusan?',
    message: `${item.code} (${item.name}) akan dihapus. Kelas yang masih memakai jurusan ini bisa ikut terdampak.`,
    confirmLabel: 'Hapus jurusan',
    danger: true,
  })
  if (!confirmed) return

  try {
    await axios.delete(`/api/v1/majors/${item.id}`)
    notice.success(`Jurusan ${item.code} dihapus.`)
    fetchItems()
  } catch (error) {
    notice.error(errorMessage(error, 'Jurusan gagal dihapus.'))
  }
}

onMounted(fetchItems)
</script>
