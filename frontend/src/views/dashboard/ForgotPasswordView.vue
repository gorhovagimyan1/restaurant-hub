<script setup>
import { useI18n } from 'vue-i18n'
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()

const auth = useAuthStore()

const email = ref('')
const message = ref(null)
const error = ref(null)
const submitting = ref(false)

async function submit() {
  message.value = null
  error.value = null
  submitting.value = true
  try {
    message.value = await auth.forgotPassword(email.value)
  } catch (err) {
    error.value =
      err?.response?.data?.message || t('auth.forgot.failed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-canvas px-4">
    <div class="w-full max-w-sm card p-8">
      <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ t('auth.forgot.title') }}</h1>
      <p class="mt-1 text-sm text-ink-500">
        {{ t('auth.forgot.subtitle') }}
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm font-medium text-ink-700">{{ t('auth.email') }}</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-lg border border-hairline-strong px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
          />
        </div>

        <p v-if="message" class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
          {{ message }}
        </p>
        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
          {{ error }}
        </p>

        <button
          type="submit"
          :disabled="submitting"
          class="w-full rounded-lg bg-brand-500 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
        >
          {{ submitting ? t('auth.forgot.submitting') : t('auth.forgot.submit') }}
        </button>
      </form>

      <div class="mt-4 text-center text-xs text-ink-400">
        <RouterLink :to="{ name: 'login' }" class="hover:text-brand-600">
          {{ t('auth.backToSignIn') }}
        </RouterLink>
      </div>
    </div>
  </div>
</template>
