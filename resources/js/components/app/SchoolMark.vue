<template>
  <div class="flex items-center gap-3">
    <img
      v-if="schoolStore.logo && !logoFailed"
      :src="schoolStore.logo"
      alt=""
      class="size-11 shrink-0 rounded-full bg-kertas object-contain p-0.5"
      @error="logoFailed = true"
    />
    <span
      v-else
      class="flex size-11 shrink-0 items-center justify-center rounded-full bg-amber font-display text-lg font-bold text-tinta"
      aria-hidden="true"
      title="Logo belum diunggah di Pengaturan"
    >
      {{ initials }}
    </span>
    <span class="font-display text-[1.15rem] font-bold uppercase leading-[1.05]" :class="light ? 'text-krem' : 'text-tinta'">
      {{ schoolStore.name || 'Pembayaran Sekolah' }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useSchoolStore } from '@/stores/school'

/** School logo + name from Pengaturan. Without a logo it shows initials, never an invented mark. */
defineProps<{ light?: boolean }>()

const schoolStore = useSchoolStore()
const logoFailed = ref(false)

const initials = computed(() => {
  const words = (schoolStore.name || 'Sekolah').replace(/^SMK\s+/i, '').split(/\s+/).filter(Boolean)
  return words
    .slice(0, 2)
    .map((word: string) => word[0])
    .join('')
    .toUpperCase()
})
</script>
