<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

// Token + email arrive as query params from the emailed reset link.
const token = ref(route.query.token || '')
const email = ref(route.query.email || '')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref(null)
const message = ref(null)
const submitting = ref(false)

async function submit() {
  error.value = null
  message.value = null
  submitting.value = true
  try {
    message.value = await auth.resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    // Give the success message a beat, then send them to sign in.
    setTimeout(() => router.push({ name: 'login' }), 1500)
  } catch (err) {
    const errors = err?.response?.data?.errors
    error.value =
      errors?.email?.[0] ||
      errors?.password?.[0] ||
      err?.response?.data?.message ||
      'Unable to reset password. The link may have expired.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-canvas px-4">
    <div class="w-full max-w-sm card p-8">
      <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">Reset password</h1>
      <p class="mt-1 text-sm text-ink-500">Choose a new password for your account.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm font-medium text-ink-700">Email</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-lg border border-hairline-strong px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-ink-700">New password</label>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="new-password"
            class="w-full rounded-lg border border-hairline-strong px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-ink-700">Confirm password</label>
          <input
            v-model="passwordConfirmation"
            type="password"
            required
            autocomplete="new-password"
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
          {{ submitting ? 'Resetting…' : 'Reset password' }}
        </button>
      </form>

      <div class="mt-4 text-center text-xs text-ink-400">
        <RouterLink :to="{ name: 'login' }" class="hover:text-brand-600">
          Back to sign in
        </RouterLink>
      </div>
    </div>
  </div>
</template>
