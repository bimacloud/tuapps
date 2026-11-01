<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-end justify-center bg-tinta/50 p-0 sm:items-center sm:p-4"
      @mousedown.self="!persistent && emit('close')"
    >
      <div
        ref="dialogRef"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        class="flex max-h-[92vh] w-full flex-col rounded-t-[var(--radius-panel)] bg-kertas shadow-angkat sm:rounded-[var(--radius-panel)]"
        :class="sizeClass"
        @keydown="onKeydown"
      >
        <header class="flex items-start justify-between gap-4 border-b border-garis px-5 py-4">
          <div>
            <h2 :id="titleId" class="text-2xl font-bold leading-tight">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-tinta-2">{{ description }}</p>
          </div>
          <button type="button" class="btn btn-ghost btn-sm -mr-2 shrink-0" @click="emit('close')">
            Tutup
          </button>
        </header>
        <div class="overflow-y-auto px-5 py-5">
          <slot />
        </div>
        <footer v-if="$slots.footer" class="flex flex-wrap justify-end gap-3 border-t border-garis px-5 py-4">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    description?: string
    size?: 'sm' | 'md' | 'lg' | 'xl'
    persistent?: boolean
  }>(),
  { size: 'md', persistent: false, description: '' },
)

const emit = defineEmits<{ close: [] }>()

const titleId = useId()
const dialogRef = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const sizeClass = computed(
  () =>
    ({
      sm: 'sm:max-w-md',
      md: 'sm:max-w-lg',
      lg: 'sm:max-w-2xl',
      xl: 'sm:max-w-4xl',
    })[props.size],
)

const focusableSelector =
  'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

const focusables = (): HTMLElement[] =>
  dialogRef.value ? Array.from(dialogRef.value.querySelectorAll<HTMLElement>(focusableSelector)) : []

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      previouslyFocused = document.activeElement as HTMLElement | null
      await nextTick()
      const items = focusables()
      const firstField = items.find((element) => element.matches('input, select, textarea'))
      ;(firstField ?? items[1] ?? items[0])?.focus()
    } else {
      previouslyFocused?.focus?.()
    }
  },
  { immediate: true },
)

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.stopPropagation()
    emit('close')
    return
  }

  if (event.key !== 'Tab') return

  const items = focusables()
  if (!items.length) return
  const first = items[0]
  const last = items[items.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
</script>
