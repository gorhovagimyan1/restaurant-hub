<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

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
      err?.response?.data?.message || 'Something went wrong. Please try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-stone-100 px-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-sm ring-1 ring-black/5">
      <h1 class="text-2xl font-bold text-stone-900">Forgot password?</h1>
      <p class="mt-1 text-sm text-stone-500">
        Enter your email and we'll send you a reset link.
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Email</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
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
          {{ submitting ? 'Sending…' : 'Send reset link' }}
        </button>
      </form>

      <div class="mt-4 text-center text-xs text-stone-400">
        <RouterLink :to="{ name: 'login' }" class="hover:text-brand-600">
          Back to sign in
        </RouterLink>
      </div>
    </div>
  </div>
</template>
