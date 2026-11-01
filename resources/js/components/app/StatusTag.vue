<template>
  <span
    class="inline-flex items-center whitespace-nowrap rounded-[4px] px-2 py-0.5 text-[0.8rem] font-semibold"
    :class="toneClass"
  >
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * Text-first status label. Colors come from DESIGN.md (all >= 5.5:1); the label carries the meaning.
 */
const props = defineProps<{ status: string | boolean | null | undefined; label?: string }>()

type Tone = 'lunas' | 'belum' | 'menunggu' | 'redup' | 'galat'

const map: Record<string, { label: string; tone: Tone }> = {
  LUNAS: { label: 'Lunas', tone: 'lunas' },
  SEBAGIAN: { label: 'Sebagian', tone: 'menunggu' },
  'BELUM BAYAR': { label: 'Belum bayar', tone: 'belum' },
  CANCELLED: { label: 'Dibatalkan', tone: 'redup' },
  SUCCESS: { label: 'Berhasil', tone: 'lunas' },
  VOID: { label: 'Dibatalkan', tone: 'redup' },
  PENDING: { label: 'Menunggu', tone: 'menunggu' },
  APPROVED: { label: 'Disetujui', tone: 'lunas' },
  REJECTED: { label: 'Ditolak', tone: 'galat' },
  AKTIF: { label: 'Aktif', tone: 'lunas' },
  NONAKTIF: { label: 'Nonaktif', tone: 'redup' },
  LULUS: { label: 'Lulus', tone: 'redup' },
  PINDAH: { label: 'Pindah', tone: 'redup' },
  KELUAR: { label: 'Keluar', tone: 'redup' },
  OPEN: { label: 'Kas dibuka', tone: 'lunas' },
  CLOSED: { label: 'Kas ditutup', tone: 'redup' },
  BULANAN: { label: 'Bulanan', tone: 'redup' },
  SEMESTER: { label: 'Semester', tone: 'redup' },
  TAHUNAN: { label: 'Tahunan', tone: 'redup' },
  KHUSUS: { label: 'Khusus', tone: 'redup' },
}

const resolved = computed(() => {
  if (typeof props.status === 'boolean') {
    return props.status ? map.AKTIF : map.NONAKTIF
  }
  const key = String(props.status ?? '').toUpperCase()
  return map[key] ?? { label: String(props.status ?? '-'), tone: 'redup' as Tone }
})

const label = computed(() => props.label || resolved.value.label)

const toneClass = computed(
  () =>
    ({
      lunas: 'bg-lunas-muda text-lunas',
      belum: 'bg-bata-muda text-belum',
      menunggu: 'bg-amber-muda text-tinta',
      redup: 'bg-redup text-tinta-2',
      galat: 'bg-galat-muda text-galat',
    })[resolved.value.tone],
)
</script>
