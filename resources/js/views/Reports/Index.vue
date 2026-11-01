<template>
  <admin-layout>
    <div class="no-print">
      <PageHeader title="Laporan" description="Pembayaran harian, tunggakan siswa, dan rekap tagihan. Pilih laporan, atur filternya, lalu cetak bila perlu." />

      <nav aria-label="Jenis laporan" class="mb-6 border-b border-garis">
        <ul class="flex flex-wrap gap-x-1">
          <li v-for="tab in tabs" :key="tab.to">
            <router-link
              :to="tab.to"
              class="-mb-px inline-flex min-h-[44px] items-center border-b-[3px] px-4 font-semibold transition-colors duration-150"
              :class="isActive(tab.to) ? 'border-bata text-tinta' : 'border-transparent text-tinta-2 hover:border-garis-kuat hover:text-tinta'"
              :aria-current="isActive(tab.to) ? 'page' : undefined"
            >
              {{ tab.label }}
            </router-link>
          </li>
        </ul>
      </nav>
    </div>

    <router-view />
  </admin-layout>
</template>

<script setup>
import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageHeader from '@/components/app/PageHeader.vue'

const route = useRoute()
const router = useRouter()

const tabs = [
  { to: '/reports/harian', label: 'Harian' },
  { to: '/reports/tunggakan', label: 'Tunggakan' },
  { to: '/reports/aggregated', label: 'Rekapitulasi' },
]

const isActive = (path) => route.path === path || route.path.startsWith(`${path}/`)

/** /reports has no content of its own; open the first report instead of an empty page. */
watch(
  () => route.path,
  (path) => {
    if (path.replace(/\/+$/, '') === '/reports') {
      router.replace(tabs[0].to)
    }
  },
  { immediate: true },
)
</script>
