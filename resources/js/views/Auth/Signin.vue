<template>
  <div class="flex min-h-screen flex-col bg-krem lg:flex-row">
    <section
      class="flex flex-col justify-between gap-6 bg-tinta px-4 py-6 text-krem sm:px-8 lg:w-[44%] lg:px-12 lg:py-12"
      aria-label="Identitas sekolah"
    >
      <SchoolMark light />
      <div>
        <p class="font-display text-xl font-semibold text-krem lg:text-[2rem] lg:leading-tight">
          Pembayaran administrasi siswa
        </p>
        <p v-if="schoolStore.address" class="mt-2 max-w-sm text-sm text-pasir lg:mt-4 lg:text-base">
          {{ schoolStore.address }}
        </p>
      </div>
    </section>

    <main class="flex flex-1 items-start justify-center px-4 py-8 sm:px-8 lg:items-center lg:py-12">
      <div class="w-full max-w-sm">
        <h1 class="text-[2.25rem] font-bold leading-none">Masuk</h1>
        <p class="mt-2 text-tinta-2">Gunakan akun yang dibuat oleh pengelola aplikasi di sekolah.</p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="handleSubmit">
          <p
            v-if="loginError"
            id="login-error"
            role="alert"
            class="rounded-[var(--radius-kontrol)] bg-galat-muda px-3 py-2 text-sm font-semibold text-galat"
          >
            {{ loginError }}
          </p>

          <div>
            <label for="email" class="field-label">Email</label>
            <input
              id="email"
              v-model="email"
              type="email"
              name="email"
              autocomplete="username"
              required
              class="field-input"
              :aria-invalid="loginError ? 'true' : undefined"
              :aria-describedby="loginError ? 'login-error' : undefined"
            />
          </div>

          <div>
            <label for="password" class="field-label">Kata sandi</label>
            <div class="flex gap-2">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                name="password"
                autocomplete="current-password"
                required
                class="field-input"
                :aria-invalid="loginError ? 'true' : undefined"
                :aria-describedby="loginError ? 'login-error' : undefined"
              />
              <button
                type="button"
                class="btn btn-secondary shrink-0 px-3"
                :class="showPassword ? '!bg-amber-muda' : ''"
                :aria-pressed="showPassword ? 'true' : 'false'"
                aria-controls="password"
                aria-label="Tampilkan kata sandi"
                @click="showPassword = !showPassword"
              >
                Tampilkan
              </button>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-full" :disabled="isLoading">
            {{ isLoading ? 'Memeriksa akun...' : 'Masuk' }}
          </button>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSchoolStore } from '@/stores/school'
import { errorMessage } from '@/utils/format'
import SchoolMark from '@/components/app/SchoolMark.vue'

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loginError = ref('')
const isLoading = ref(false)

const router = useRouter()
const authStore = useAuthStore()
const schoolStore = useSchoolStore()

const handleSubmit = async () => {
  if (!email.value || !password.value) {
    loginError.value = 'Isi email dan kata sandi terlebih dahulu.'
    return
  }

  isLoading.value = true
  loginError.value = ''

  try {
    const success = await authStore.login({
      email: email.value,
      password: password.value,
    })

    if (success) {
      router.push({ name: 'Dashboard' })
    } else {
      loginError.value = 'Email atau kata sandi salah.'
    }
  } catch (error) {
    loginError.value = errorMessage(error, 'Server tidak dapat dihubungi. Periksa koneksi, lalu coba lagi.')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => schoolStore.loadProfile())
</script>
