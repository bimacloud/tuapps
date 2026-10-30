<template>
  <SidebarProvider>
    <RouterView />
    <NoticeHost />
  </SidebarProvider>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import SidebarProvider from './components/layout/SidebarProvider.vue'
import NoticeHost from './components/app/NoticeHost.vue'
import { useSchoolStore } from './stores/school'

const schoolStore = useSchoolStore()

onMounted(() => {
  schoolStore.loadProfile()
})
</script>
