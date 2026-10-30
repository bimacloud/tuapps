import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import VueApexCharts from 'vue3-apexcharts'
import axios from 'axios'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(VueApexCharts)

// Set base URL for API if needed, since it's the same origin we can just use relative
// axios.defaults.baseURL = 'http://localhost:8000'; // Not needed if integrated

// Axios Interceptor for 401 Unauthorized
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      import('./stores/auth').then(({ useAuthStore }) => {
        const authStore = useAuthStore()
        authStore.logout()
        router.push({ name: 'Signin' })
      })
    }
    return Promise.reject(error)
  }
)

// A preloaded chunk from an older build failed to load: reload once to pick up the new build.
window.addEventListener('vite:preloadError', () => {
  window.location.reload()
})

app.mount('#app')
