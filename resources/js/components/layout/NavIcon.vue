<template>
  <svg
    viewBox="0 0 24 24"
    width="20"
    height="20"
    fill="none"
    stroke="currentColor"
    stroke-width="1.9"
    stroke-linecap="square"
    stroke-linejoin="miter"
    aria-hidden="true"
  >
    <path v-for="(d, index) in paths[name]" :key="index" :d="d" />
  </svg>
</template>

<script setup lang="ts">
/**
 * Sidebar icon set. Square caps and mitred joins to match the blocky lettering of the school flyer.
 */
defineProps<{ name: keyof typeof paths }>()

const paths = {
  beranda: ['M3 11 12 4l9 7', 'M5 9.5V20h5v-6h4v6h5V9.5'],
  kasir: ['M3 6h18v12H3z', 'M7 6v12', 'M17 6v12', 'M12 9.5v5', 'M10.5 12h3'],
  riwayat: ['M4 5h16', 'M4 10h16', 'M4 15h9', 'M17 14v3.5l2 1.5'],
  tagihan: ['M6 3h9l4 4v14H6z', 'M14 3v5h5', 'M9 12h7', 'M9 16h7'],
  kartu: ['M3 5h18v14H3z', 'M12 5v14', 'M5.5 9h4', 'M5.5 12.5h4', 'M14.5 9h4', 'M14.5 12.5h4'],
  jenis: ['M3 3h8l10 10-8 8L3 11z', 'M7.5 7.5h.01'],
  tarif: ['M3 5h18v14H3z', 'M3 10h18', 'M9 10v9'],
  laporan: ['M4 20V10', 'M10 20V4', 'M16 20v-7', 'M21 20H3'],
  persetujuan: ['M4 4h16v16H4z', 'M8 12l3 3 5-6'],
  tahun: ['M3 5h18v16H3z', 'M3 10h18', 'M8 3v4', 'M16 3v4'],
  jurusan: ['M5 21V4', 'M5 4h13l-3 4 3 4H5'],
  kelas: ['M3 4h7v7H3z', 'M14 4h7v7h-7z', 'M3 15h7v6H3z', 'M14 15h7v6h-7z'],
  siswa: ['M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z', 'M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6', 'M16 4.5a3.5 3.5 0 0 1 0 6.5', 'M18 14.5c1.8.8 3 2.6 3 5.5'],
  audit: ['M5 3h10l4 4v6', 'M5 3v18h7', 'M17 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'M21 22l-1.8-1.8'],
  pengaturan: ['M4 7h10', 'M18 7h2', 'M4 17h4', 'M12 17h8', 'M14 4v6', 'M8 14v6'],
  keluar: ['M14 4H4v16h10', 'M10 12h11', 'M17 8l4 4-4 4'],
  menu: ['M3 6h18', 'M3 12h18', 'M3 18h18'],
}
</script>
