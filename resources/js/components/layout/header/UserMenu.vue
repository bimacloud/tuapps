<template>
  <div ref="rootRef" class="relative" @keydown.esc="closeMenu(true)">
    <button
      ref="triggerRef"
      type="button"
      class="flex min-h-11 items-center gap-3 rounded-[var(--radius-kontrol)] px-2 text-left hover:bg-garis/60"
      aria-haspopup="menu"
      :aria-expanded="open"
      @click="open = !open"
    >
      <span
        class="flex size-9 items-center justify-center rounded-full bg-tinta font-display text-base font-bold text-krem"
        aria-hidden="true"
        >{{ initial }}</span
      >
      <span class="hidden leading-tight sm:block">
        <span class="block font-semibold">{{ authStore.user?.name || 'Pengguna' }}</span>
        <span class="block text-sm text-tinta-2">{{ roleLabel }}</span>
      </span>
    </button>

    <div
      v-if="open"
      role="menu"
      class="absolute right-0 mt-2 w-56 rounded-[var(--radius-panel)] border border-garis bg-kertas p-1.5 shadow-angkat"
    >
      <p class="px-3 pb-2 pt-1.5 text-sm text-tinta-2 sm:hidden">
        {{ authStore.user?.name }} · {{ roleLabel }}
      </p>
      <button
        ref="signOutRef"
        type="button"
        role="menuitem"
        class="flex min-h-11 w-full items-center gap-3 rounded-[var(--radius-kontrol)] px-3 font-semibold text-tinta hover:bg-krem"
        @click="signOut"
      >
        <NavIcon name="keluar" />
        Keluar
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import NavIcon from '../NavIcon.vue'

const router = useRouter()
const authStore = useAuthStore()
const open = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLElement | null>(null)
const signOutRef = ref<HTMLElement | null>(null)

const initial = computed(() => (authStore.user?.name || 'P').trim()[0].toUpperCase())
const roleLabel = computed(() => authStore.roles?.[0] || '')

const closeMenu = (returnFocus = false) => {
  if (!open.value) return
  open.value = false
  if (returnFocus) triggerRef.value?.focus()
}

watch(open, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    signOutRef.value?.focus()
  }
})

const onDocumentClick = (event: MouseEvent) => {
  if (rootRef.value && !rootRef.value.contains(event.target as Node)) closeMenu()
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))

const signOut = async () => {
  await authStore.logout()
  router.push({ name: 'Signin' })
}
</script>
