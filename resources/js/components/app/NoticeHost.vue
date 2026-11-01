<template>
  <div
    class="no-print pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end"
    aria-live="polite"
    role="status"
  >
    <div
      v-for="notice in notices"
      :key="notice.id"
      class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-[var(--radius-panel)] border px-4 py-3 shadow-angkat"
      :class="notice.tone === 'success' ? 'border-lunas bg-lunas-muda text-lunas' : 'border-galat bg-galat-muda text-galat'"
    >
      <p class="flex-1 text-sm font-semibold">
        <span class="sr-only">{{ notice.tone === 'success' ? 'Berhasil: ' : 'Gagal: ' }}</span>{{ notice.message }}
      </p>
      <button type="button" class="text-sm font-semibold underline underline-offset-2" @click="dismiss(notice.id)">
        Tutup
      </button>
    </div>
  </div>

  <BaseModal :open="confirmRequest.open" :title="confirmRequest.title" size="sm" @close="answerConfirm(false)">
    <p class="text-tinta">{{ confirmRequest.message }}</p>
    <template #footer>
      <button type="button" class="btn btn-secondary" @click="answerConfirm(false)">Batal</button>
      <button
        type="button"
        class="btn"
        :class="confirmRequest.danger ? 'btn-danger' : 'btn-primary'"
        @click="answerConfirm(true)"
      >
        {{ confirmRequest.confirmLabel }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import BaseModal from './BaseModal.vue'
import { useNotice } from '@/composables/useNotice'

const { notices, confirmRequest, dismiss, answerConfirm } = useNotice()
</script>
