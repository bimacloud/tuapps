<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Modal from '@/components/ui/Modal.vue'

const loading = ref(false)
const fetching = ref(true)
const showSuccessModal = ref(false)
const showConfirmModal = ref(false)

const settings = ref({
  school_name: '',
  school_address: '',
  school_phone: '',
  school_email: '',
  receipt_footer: '',
  school_logo: null as File | null | string
})

const previewLogo = ref<string | null>(null)

const fetchSettings = async () => {
  fetching.value = true
  try {
    const res = await axios.get('/api/v1/settings')
    if (res.data.success) {
      const data = res.data.data
      settings.value.school_name = data.school_name || ''
      settings.value.school_address = data.school_address || ''
      settings.value.school_phone = data.school_phone || ''
      settings.value.school_email = data.school_email || ''
      settings.value.receipt_footer = data.receipt_footer || ''
      if (data.school_logo) {
        previewLogo.value = data.school_logo
      }
    }
  } catch (error) {
    console.error('Error fetching settings', error)
  } finally {
    fetching.value = false
  }
}

const onFileChange = (e: any) => {
  const file = e.target.files[0]
  if (file) {
    settings.value.school_logo = file
    previewLogo.value = URL.createObjectURL(file)
  }
}

const confirmSave = () => {
  showConfirmModal.value = true
}

const saveSettings = async () => {
  showConfirmModal.value = false
  loading.value = true
  try {
    const formData = new FormData()
    formData.append('school_name', settings.value.school_name)
    formData.append('school_address', settings.value.school_address)
    formData.append('school_phone', settings.value.school_phone)
    formData.append('school_email', settings.value.school_email)
    formData.append('receipt_footer', settings.value.receipt_footer)
    
    if (settings.value.school_logo instanceof File) {
      formData.append('school_logo', settings.value.school_logo)
    }

    const res = await axios.post('/api/v1/settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (res.data.success) {
      showSuccessModal.value = true
      setTimeout(() => {
        window.location.reload()
      }, 1500)
    }
  } catch (error: any) {
    alert(error.response?.data?.message || 'Gagal menyimpan pengaturan')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <AdminLayout>
    <div>
      <PageBreadcrumb pageTitle="Pengaturan Aplikasi" />

      <div v-if="fetching" class="text-center py-8">Memuat pengaturan...</div>
      
      <div v-else class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark max-w-3xl mx-auto">
        <div class="border-b border-stroke py-4 px-6.5 dark:border-strokedark">
          <h3 class="font-medium text-black dark:text-white">Pengaturan Kuitansi & Profil Sekolah</h3>
        </div>
        <form @submit.prevent="confirmSave">
          <div class="p-6.5 space-y-4">
            <!-- Logo -->
            <div class="mb-4">
              <label class="mb-2.5 block text-black dark:text-white font-medium">Logo Sekolah</label>
              <div class="flex items-center gap-4">
                <div v-if="previewLogo" class="w-20 h-20 border border-gray-300 rounded overflow-hidden">
                  <img :src="previewLogo" alt="Logo Sekolah" class="w-full h-full object-cover" />
                </div>
                <div v-else class="w-20 h-20 border border-gray-300 rounded bg-gray-100 flex items-center justify-center text-xs text-gray-500">
                  Belum ada logo
                </div>
                <div>
                  <input 
                    type="file" 
                    accept="image/*"
                    @change="onFileChange"
                    class="w-full rounded-md border border-stroke p-2 outline-none transition file:mr-4 file:rounded file:border-0 file:bg-brand-500 file:py-1 file:px-3 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-500/90 dark:border-form-strokedark dark:bg-form-input"
                  />
                </div>
              </div>
            </div>

            <!-- School Name -->
            <div>
              <label class="mb-2.5 block text-black dark:text-white font-medium">Nama Sekolah</label>
              <input 
                v-model="settings.school_name" 
                type="text" 
                placeholder="Contoh: SMK TIK Wates"
                class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-form-strokedark dark:bg-form-input dark:focus:border-brand-500"
              />
            </div>

            <!-- School Address -->
            <div>
              <label class="mb-2.5 block text-black dark:text-white font-medium">Alamat Sekolah</label>
              <input 
                v-model="settings.school_address" 
                type="text" 
                placeholder="Contoh: Jl. Raya Wates No. 123..."
                class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-form-strokedark dark:bg-form-input dark:focus:border-brand-500"
              />
            </div>

            <!-- Phone & Email -->
            <div class="flex flex-col sm:flex-row gap-4">
              <div class="flex-1">
                <label class="mb-2.5 block text-black dark:text-white font-medium">Nomor Telepon</label>
                <input 
                  v-model="settings.school_phone" 
                  type="text" 
                  placeholder="Contoh: (0274) 123456"
                  class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-form-strokedark dark:bg-form-input dark:focus:border-brand-500"
                />
              </div>
              <div class="flex-1">
                <label class="mb-2.5 block text-black dark:text-white font-medium">Email</label>
                <input 
                  v-model="settings.school_email" 
                  type="email" 
                  placeholder="Contoh: info@smk.sch.id"
                  class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-form-strokedark dark:bg-form-input dark:focus:border-brand-500"
                />
              </div>
            </div>

            <!-- Receipt Footer -->
            <div>
              <label class="mb-2.5 block text-black dark:text-white font-medium">Teks Catatan Bawah Kuitansi (Footer)</label>
              <textarea 
                v-model="settings.receipt_footer" 
                rows="3"
                placeholder="Catatan yang akan muncul di bagian paling bawah kuitansi cetak"
                class="w-full rounded border-[1.5px] border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-form-strokedark dark:bg-form-input dark:focus:border-brand-500"
              ></textarea>
            </div>

            <button 
              type="submit" 
              :disabled="loading"
              class="flex w-full justify-center rounded-lg bg-brand-500 p-3 font-medium text-white transition hover:bg-brand-600 disabled:opacity-50"
            >
              {{ loading ? 'Menyimpan...' : 'Simpan Pengaturan' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Success Modal -->
    <Modal v-if="showSuccessModal" :fullScreenBackdrop="true" @close="showSuccessModal = false">
      <template #body>
        <div class="relative z-50 p-6 mx-auto w-full max-w-sm bg-white rounded-2xl shadow-xl dark:bg-gray-800 text-center border border-gray-100 dark:border-gray-700">
          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 mb-4">
            <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Berhasil!</h3>
          <p class="text-gray-500 dark:text-gray-400 mb-6">Pengaturan berhasil disimpan.</p>
          <div class="flex justify-center">
            <div class="h-6 w-6 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"></div>
          </div>
        </div>
      </template>
    </Modal>

    <!-- Confirm Modal -->
    <Modal v-if="showConfirmModal" :fullScreenBackdrop="true" @close="showConfirmModal = false">
      <template #body>
        <div class="relative z-50 p-6 mx-auto w-full max-w-sm bg-white rounded-2xl shadow-xl dark:bg-gray-800 text-center border border-gray-100 dark:border-gray-700">
          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-100 mb-4">
            <svg class="h-8 w-8 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Konfirmasi Penyimpanan</h3>
          <p class="text-gray-500 dark:text-gray-400 mb-6">Apakah data yang Anda update sudah benar?</p>
          <div class="flex justify-center gap-3">
            <button @click="showConfirmModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
              Batal
            </button>
            <button @click="saveSettings" class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600">
              Ya, Simpan
            </button>
          </div>
        </div>
      </template>
    </Modal>
  </AdminLayout>
</template>
