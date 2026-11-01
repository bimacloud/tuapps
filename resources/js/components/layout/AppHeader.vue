<template>
  <header class="no-print sticky top-0 z-30 border-b border-garis bg-krem/95 backdrop-blur-sm">
    <div class="mx-auto flex h-16 max-w-[1280px] items-center gap-3 px-4 sm:px-6 lg:px-8">
      <button
        type="button"
        class="btn btn-secondary lg:hidden"
        aria-controls="app-sidebar"
        :aria-expanded="isMobileOpen"
        @click="toggleMobileSidebar"
      >
        <NavIcon name="menu" />
        <span>Menu</span>
      </button>

      <router-link
        v-if="schoolStore.activeYear"
        to="/master/academic-years"
        class="pita hidden bg-amber py-1.5 pl-3 text-sm font-semibold text-tinta sm:inline-block"
        title="Tahun pelajaran aktif"
      >
        TP {{ schoolStore.activeYear.name }}
      </router-link>

      <div class="ml-auto">
        <UserMenu />
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useSidebar } from '@/composables/useSidebar'
import { useSchoolStore } from '@/stores/school'
import UserMenu from './header/UserMenu.vue'
import NavIcon from './NavIcon.vue'

const { isMobileOpen, toggleMobileSidebar } = useSidebar()
const schoolStore = useSchoolStore()

onMounted(() => {
  if (!schoolStore.activeYear) schoolStore.loadActiveYear()
})
</script>
