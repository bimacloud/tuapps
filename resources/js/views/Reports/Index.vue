<template>
  <admin-layout>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="text-title-md2 font-semibold text-black dark:text-white">Laporan Keuangan</h2>
    </div>
    
    <!-- Tab Navigation -->
    <div class="mb-6 border-b border-stroke dark:border-strokedark">
      <ul class="flex flex-wrap text-sm font-medium text-center text-gray-500">
        <li class="mr-2">
          <router-link to="/reports/harian" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" active-class="text-primary border-b-2 border-primary bg-gray-2 dark:bg-meta-4">
            Harian
          </router-link>
        </li>
        <li class="mr-2">
          <router-link to="/reports/tunggakan" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" active-class="text-primary border-b-2 border-primary bg-gray-2 dark:bg-meta-4">
            Tunggakan Siswa
          </router-link>
        </li>
        <li class="mr-2">
          <router-link to="/reports/aggregated" class="inline-block p-4 rounded-t-lg hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 dark:hover:text-gray-300" active-class="text-primary border-b-2 border-primary bg-gray-2 dark:bg-meta-4">
            Rekapitulasi
          </router-link>
        </li>
      </ul>
    </div>

    <!-- Render Tab Content -->
    <router-view></router-view>

  </admin-layout>
</template>

<script setup>
import AdminLayout from '../../components/layout/AdminLayout.vue'
</script>
