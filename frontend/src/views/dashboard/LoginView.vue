<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AuthShell from '@/components/auth/AuthShell.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref(null)

async function submit() {
  error.value = null
  try {
    await auth.login({ email: email.value, password: password.value })
    // Kitchen/waiter staff land on the kitchen display; owners on the dashboard.
    const redirect = route.query.redirect || auth.homeRoute
    router.push(redirect)
  } catch (err) {
    error.value =
      err?.response?.data?.message || 'Login failed. Please check your credentials.'
  }
}

function fillDemo(role) {
  email.value = role === 'kitchen' ? 'kitchen@thegoldenfork.test' : 'owner@thegoldenfork.test'
  password.value = 'password'
}

const inputClass =
  'w-full field'
</script>

<template>
  <AuthShell title="Welcome back" subtitle="Sign in to manage your restaurant.">
    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-ink-700">Email</label>
        <input v-model="email" type="email" required autocomplete="username" :class="inputClass" />
      </div>
      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label class="block text-sm font-medium text-ink-700">Password</label>
          <RouterLink :to="{ name: 'forgot-password' }" class="text-xs font-medium text-brand-600 hover:text-brand-700">
            Forgot password?
          </RouterLink>
        </div>
        <input
          v-model="password"
          type="password"
          required
          autocomplete="current-password"
          :class="inputClass"
        />
      </div>

      <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

      <button
        type="submit"
        :disabled="auth.loading"
        class="btn-brand w-full rounded-xl py-3 text-sm font-semibold transition hover:-translate-y-0.5 disabled:opacity-60"
      >
        {{ auth.loading ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>

    <div class="mt-5 flex items-center justify-center gap-3 text-xs text-ink-400">
      <button class="rounded-lg px-2 py-1 font-medium hover:bg-canvas hover:text-brand-600" @click="fillDemo('owner')">
        Demo owner
      </button>
      <span class="text-ink-300">·</span>
      <button class="rounded-lg px-2 py-1 font-medium hover:bg-canvas hover:text-brand-600" @click="fillDemo('kitchen')">
        Demo kitchen
      </button>
    </div>

    <p class="mt-6 text-center text-sm text-ink-500">
      New here?
      <RouterLink :to="{ name: 'register' }" class="font-semibold text-brand-600 hover:underline">
        Create your restaurant
      </RouterLink>
    </p>
  </AuthShell>
</template>
