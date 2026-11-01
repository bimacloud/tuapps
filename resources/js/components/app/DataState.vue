<template>
  <div
    :role="state === 'error' ? 'alert' : 'status'"
    class="flex flex-col items-center px-6 text-center"
    :class="compact ? 'py-8' : 'py-14'"
  >
    <span
      v-if="state === 'loading'"
      class="mb-3 inline-block size-6 animate-spin rounded-full border-[3px] border-garis border-t-bata"
      aria-hidden="true"
    ></span>
    <p class="font-semibold" :class="state === 'error' ? 'text-galat' : 'text-tinta'">
      {{ title || defaults[state] }}
    </p>
    <p v-if="message" class="mt-1 max-w-md text-sm text-tinta-2">{{ message }}</p>
    <div v-if="$slots.default" class="mt-4 flex flex-wrap justify-center gap-2">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Loading / empty / error block. Empty and error states should say why and offer the next action via the slot.
 */
withDefaults(
  defineProps<{
    state: 'loading' | 'empty' | 'error'
    title?: string
    message?: string
    compact?: boolean
  }>(),
  { title: '', message: '', compact: false },
)

const defaults = {
  loading: 'Memuat data...',
  empty: 'Belum ada data.',
  error: 'Data gagal dimuat.',
}
</script>
