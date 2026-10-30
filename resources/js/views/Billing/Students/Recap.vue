<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const studentId = route.params.id // or pass it as prop if it's a modal, but let's assume route for now
const student = ref<any>(null)
const bulanan = ref<any[]>([])
const tahunan = ref<any[]>([])
const loading = ref(true)

const fetchStudent = async () => {
  try {
    const res = await axios.get(`/api/v1/students/${studentId}`)
    student.value = res.data.data
  } catch (error) {
    console.error('Error fetching student', error)
  }
}

const fetchRecap = async () => {
  loading.value = true
  try {
    const res = await axios.get(`/api/v1/payments/student/${studentId}/recap`)
    bulanan.value = res.data.data.bulanan
    tahunan.value = res.data.data.tahunan
  } catch (error) {
    console.error('Error fetching recap', error)
  } finally {
    loading.value = false
  }
}

const formatRupiah = (number: number) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number)
}

onMounted(async () => {
  if (studentId) {
    await fetchStudent()
    await fetchRecap()
  }
})
</script>

<template>
  <div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="text-title-md2 font-semibold text-black dark:text-white">Rekapitulasi Pembayaran</h2>
    </div>

    <div v-if="loading" class="text-center py-8">Memuat data rekap...</div>

    <div v-else-if="student" class="space-y-6">
      
      <!-- Profil Siswa -->
      <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark p-6">
        <h2 class="text-xl font-bold text-black dark:text-white mb-2">{{ student.name }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm text-gray-500">
          <div><p class="font-medium text-black dark:text-white">NIS/NISN</p><p>{{ student.nis }} / {{ student.nisn || '-' }}</p></div>
          <div><p class="font-medium text-black dark:text-white">Kelas</p><p>{{ student.class_room?.name }}</p></div>
          <div><p class="font-medium text-black dark:text-white">Jurusan</p><p>{{ student.class_room?.major?.name }}</p></div>
          <div><p class="font-medium text-black dark:text-white">Status</p><p>{{ student.status }}</p></div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Administrasi Bulanan (SPP dll) -->
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
          <div class="border-b border-stroke py-4 px-6 dark:border-strokedark bg-gray-2 dark:bg-meta-4">
            <h3 class="font-semibold text-black dark:text-white">Administrasi Bulanan</h3>
          </div>
          <div class="p-4">
            <div v-if="bulanan.length === 0" class="text-center py-4 text-gray-500">Tidak ada data tagihan bulanan.</div>
            <table v-else class="w-full text-sm">
              <thead>
                <tr class="border-b border-stroke dark:border-strokedark">
                  <th class="py-2 text-left">Bulan</th>
                  <th class="py-2 text-right">Tarif</th>
                  <th class="py-2 text-right">Kurang</th>
                  <th class="py-2 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in bulanan" :key="item.id" class="border-b border-stroke dark:border-strokedark last:border-0">
                  <td class="py-3 font-medium">{{ item.period }}</td>
                  <td class="py-3 text-right">{{ formatRupiah(item.amount) }}</td>
                  <td class="py-3 text-right" :class="{'text-danger': item.remaining_amount > 0}">{{ formatRupiah(item.remaining_amount) }}</td>
                  <td class="py-3 text-center">
                    <span class="inline-flex rounded-full bg-opacity-10 py-1 px-3 text-xs font-medium" 
                          :class="{
                            'bg-success text-success': item.status === 'LUNAS',
                            'bg-warning text-warning': item.status === 'SEBAGIAN',
                            'bg-danger text-danger': item.status === 'BELUM BAYAR'
                          }">
                      {{ item.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Administrasi Semester / Tahunan / Bebas -->
        <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
          <div class="border-b border-stroke py-4 px-6 dark:border-strokedark bg-gray-2 dark:bg-meta-4">
            <h3 class="font-semibold text-black dark:text-white">Administrasi Semester/Tahunan</h3>
          </div>
          <div class="p-4">
            <div v-if="tahunan.length === 0" class="text-center py-4 text-gray-500">Tidak ada data tagihan tahunan/semester.</div>
            <table v-else class="w-full text-sm">
              <thead>
                <tr class="border-b border-stroke dark:border-strokedark">
                  <th class="py-2 text-left">Jenis Tagihan</th>
                  <th class="py-2 text-right">Tarif</th>
                  <th class="py-2 text-right">Kurang</th>
                  <th class="py-2 text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in tahunan" :key="item.id" class="border-b border-stroke dark:border-strokedark last:border-0">
                  <td class="py-3 font-medium">{{ item.payment_type?.name }}</td>
                  <td class="py-3 text-right">{{ formatRupiah(item.amount) }}</td>
                  <td class="py-3 text-right" :class="{'text-danger': item.remaining_amount > 0}">{{ formatRupiah(item.remaining_amount) }}</td>
                  <td class="py-3 text-center">
                    <span class="inline-flex rounded-full bg-opacity-10 py-1 px-3 text-xs font-medium" 
                          :class="{
                            'bg-success text-success': item.status === 'LUNAS',
                            'bg-warning text-warning': item.status === 'SEBAGIAN',
                            'bg-danger text-danger': item.status === 'BELUM BAYAR'
                          }">
                      {{ item.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
