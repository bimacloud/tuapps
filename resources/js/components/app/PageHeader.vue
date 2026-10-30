<template>
  <div class="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
    <div class="min-w-0 flex-1 basis-[22rem]">
      <h1 class="text-[2rem] font-bold leading-none text-tinta">{{ title }}</h1>
      <p v-if="description" class="mt-2 max-w-2xl text-tinta-2">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>
