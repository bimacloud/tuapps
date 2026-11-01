<template>
  <div class="min-h-screen">
    <a
      href="#konten"
      class="sr-only z-50 rounded-[var(--radius-kontrol)] bg-tinta px-4 py-2 font-semibold text-krem focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
    >
      Lewati ke konten
    </a>
    <AppSidebar />
    <Backdrop />
    <div class="lg:pl-[264px] print:pl-0">
      <AppHeader />
      <main id="konten" tabindex="-1" class="mx-auto max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8 print:m-0 print:max-w-none print:p-0">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'
import Backdrop from './Backdrop.vue'
</script>
