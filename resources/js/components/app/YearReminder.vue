<template>
  <div v-if="reminder" class="rounded-[var(--radius-panel)] border border-amber bg-amber-muda px-4 py-3" role="status">
    <p class="font-semibold">
      <template v-if="reminder.kind === 'newer'">
        Tahun pelajaran {{ reminder.next.name }} sudah dimulai {{ formatDate(reminder.next.start_date) }}, tetapi yang aktif
        masih {{ reminder.active.name }}.
      </template>
      <template v-else>
        Tahun pelajaran {{ reminder.active.name }} sudah berakhir {{ formatDate(reminder.active.end_date) }}.
      </template>
    </p>
    <p class="mt-1 text-sm">
      <template v-if="reminder.kind === 'newer'">
        Aktifkan {{ reminder.next.name }} setelah kelas, kenaikan siswa, dan tarif tahun baru siap.
      </template>
      <template v-else>
        Tambahkan tahun pelajaran berikutnya, lalu aktifkan setelah kelas, kenaikan siswa, dan tarif tahun baru siap.
      </template>
      <router-link v-if="showLink" to="/master/academic-years" class="link ml-1">Buka Tahun Pelajaran</router-link>
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useSchoolStore } from '@/stores/school'
import { formatDate } from '@/utils/format'

/** Reminds TU to switch the active academic year; switching itself stays a manual decision. */
withDefaults(defineProps<{ showLink?: boolean }>(), { showLink: true })

const schoolStore = useSchoolStore()
const reminder = computed(() => schoolStore.yearReminder)

onMounted(() => {
  if (!schoolStore.years.length) schoolStore.loadActiveYear()
})
</script>
