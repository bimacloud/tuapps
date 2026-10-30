<template>
  <aside
    id="app-sidebar"
    class="no-print fixed inset-y-0 left-0 z-40 flex w-[264px] flex-col bg-tinta text-krem transition-transform duration-150 lg:translate-x-0"
    :class="isMobileOpen ? 'translate-x-0' : '-translate-x-full max-lg:invisible'"
    aria-label="Menu utama"
    @keydown.esc="isMobileOpen && toggleMobileSidebar()"
  >
    <div class="flex items-center justify-between gap-2 px-5 pb-5 pt-6">
      <router-link to="/" class="rounded-[var(--radius-kontrol)]" @click="closeOnMobile">
        <SchoolMark light />
      </router-link>
      <button
        type="button"
        class="btn text-krem hover:bg-tinta-3 lg:hidden"
        aria-label="Tutup menu"
        @click="toggleMobileSidebar"
      >
        Tutup
      </button>
    </div>

    <nav class="custom-scrollbar flex-1 overflow-y-auto pb-6">
      <div v-for="group in menuGroups" :key="group.title" class="mt-4 first:mt-0">
        <p class="px-5 pb-1.5 text-[0.8rem] font-semibold text-pasir">{{ group.title }}</p>
        <ul>
          <li v-for="item in group.items" :key="item.path" class="pr-4">
            <router-link
              :to="item.path"
              class="flex min-h-11 items-center gap-3 py-2 pl-5 text-[0.95rem] font-medium transition-colors"
              :class="
                isActive(item.path)
                  ? 'pita bg-amber font-semibold text-tinta'
                  : 'text-krem hover:bg-tinta-3'
              "
              :aria-current="isActive(item.path) ? 'page' : undefined"
              @click="closeOnMobile"
            >
              <NavIcon :name="item.icon" />
              <span>{{ item.name }}</span>
            </router-link>
          </li>
        </ul>
      </div>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSidebar } from '@/composables/useSidebar'
import SchoolMark from '@/components/app/SchoolMark.vue'
import NavIcon from './NavIcon.vue'

type NavItem = { name: string; path: string; icon: InstanceType<typeof NavIcon>['$props']['name'] }

const route = useRoute()
const authStore = useAuthStore()
const { isMobileOpen, toggleMobileSidebar } = useSidebar()

/** Flat groups (no collapsible submenus) so every page is one click and one Tab stop away. */
const menuGroups = computed(() => {
  const isKepsek = authStore.hasRole('Kepala Sekolah')
  const isBendahara = authStore.hasRole('Bendahara')
  const groups: { title: string; items: NavItem[] }[] = []

  const harian: NavItem[] = [{ name: 'Beranda', path: '/', icon: 'beranda' }]
  if (isBendahara) {
    harian.push({ name: 'Kasir', path: '/kasir', icon: 'kasir' })
    harian.push({ name: 'Riwayat Pembayaran', path: '/pembayaran', icon: 'riwayat' })
  }
  if (isKepsek) {
    harian.push({ name: 'Persetujuan', path: '/approvals', icon: 'persetujuan' })
  }
  harian.push({ name: 'Laporan', path: '/reports/harian', icon: 'laporan' })
  groups.push({ title: 'Harian', items: harian })

  if (isBendahara) {
    groups.push({
      title: 'Tagihan dan tarif',
      items: [
        { name: 'Tagihan', path: '/invoices', icon: 'tagihan' },
        { name: 'Kartu Pembayaran', path: '/kartu-pembayaran', icon: 'kartu' },
        { name: 'Jenis Pembayaran', path: '/billing/payment-types', icon: 'jenis' },
        { name: 'Tarif Pembayaran', path: '/billing/payment-rates', icon: 'tarif' },
      ],
    })
  }

  if (isKepsek || isBendahara) {
    groups.push({
      title: 'Data sekolah',
      items: [
        { name: 'Siswa', path: '/master/students', icon: 'siswa' },
        { name: 'Kelas', path: '/master/classes', icon: 'kelas' },
        { name: 'Jurusan', path: '/master/majors', icon: 'jurusan' },
        { name: 'Tahun Pelajaran', path: '/master/academic-years', icon: 'tahun' },
      ],
    })
  }

  const sistem: NavItem[] = []
  if (isKepsek) {
    sistem.push({ name: 'Audit Log', path: '/audit-logs', icon: 'audit' })
  }
  if (isKepsek || isBendahara) {
    sistem.push({ name: 'Pengaturan', path: '/settings', icon: 'pengaturan' })
  }
  if (sistem.length) {
    groups.push({ title: 'Sistem', items: sistem })
  }

  return groups
})

const isActive = (path: string) => {
  if (path === '/') return route.path === '/'
  if (path.startsWith('/reports')) return route.path.startsWith('/reports')
  if (path === '/invoices') return route.path === '/invoices' || route.path.startsWith('/billing/invoices')
  if (path === '/master/students') return route.path === path || route.path.startsWith('/siswa/')
  if (path === '/pembayaran') return route.path.startsWith('/pembayaran')
  return route.path === path
}

const closeOnMobile = () => {
  if (isMobileOpen.value) toggleMobileSidebar()
}
</script>
