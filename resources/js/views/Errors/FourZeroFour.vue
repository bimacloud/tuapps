<template>
  <component :is="authStore.isAuthenticated ? AdminLayout : 'div'" :class="authStore.isAuthenticated ? '' : 'min-h-screen bg-krem px-4 py-8 sm:px-8'">
    <div v-if="!authStore.isAuthenticated" class="mb-12">
      <SchoolMark />
    </div>

    <section class="panel max-w-xl px-5 py-8 sm:px-8">
      <p class="font-display text-lg font-semibold text-tinta-2">Kode 404</p>
      <h1 class="mt-1 text-[2rem] font-bold leading-tight">Halaman tidak ditemukan</h1>
      <p class="mt-3 text-tinta-2">
        Alamat <span class="break-all font-semibold text-tinta">{{ route.fullPath }}</span> tidak ada di aplikasi ini.
        Mungkin tautannya salah ketik atau menunya sudah dipindah. Pilih menu lain dari Beranda.
      </p>
      <router-link to="/" class="btn btn-primary mt-6">Buka Beranda</router-link>
    </section>
  </component>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSchoolStore } from '@/stores/school'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import SchoolMark from '@/components/app/SchoolMark.vue'

const route = useRoute()
const authStore = useAuthStore()
const schoolStore = useSchoolStore()

onMounted(() => schoolStore.loadProfile())
</script>
