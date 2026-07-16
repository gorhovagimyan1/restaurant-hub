<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

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
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-stone-100 px-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-sm ring-1 ring-black/5">
      <h1 class="text-2xl font-bold text-stone-900">Restaurant Hub</h1>
      <p class="mt-1 text-sm text-stone-500">Sign in to manage your menu.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Email</label>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200"
          />
        </div>
        <div>
          <div class="mb-1 flex items-center justify-between">
            <label class="block text-sm font-medium text-stone-700">Password</label>
            <RouterLink
              :to="{ name: 'forgot-password' }"
              class="text-xs text-stone-400 hover:text-amber-600"
            >
              Forgot password?
            </RouterLink>
          </div>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200"
          />
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="w-full rounded-lg bg-amber-500 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60"
        >
          {{ auth.loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <div class="mt-4 flex justify-center gap-4 text-xs text-stone-400">
        <button class="hover:text-amber-600" @click="fillDemo('owner')">Demo owner</button>
        <span class="text-stone-300">·</span>
        <button class="hover:text-amber-600" @click="fillDemo('kitchen')">Demo kitchen</button>
      </div>

      <p class="mt-4 text-center text-xs text-stone-400">
        New here?
        <RouterLink :to="{ name: 'register' }" class="font-medium text-amber-600 hover:underline">
          Create your restaurant
        </RouterLink>
      </p>
    </div>
  </div>
</template>
