<template>
  <div v-if="isMobileOpen" class="fixed inset-0 z-30 bg-tinta/50 lg:hidden" aria-hidden="true" @click="toggleMobileSidebar"></div>
</template>

<script setup lang="ts">
import { useSidebar } from '@/composables/useSidebar'

const { toggleMobileSidebar, isMobileOpen } = useSidebar()
</script>
