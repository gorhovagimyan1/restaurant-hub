<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  restaurant_name: '',
  password: '',
  password_confirmation: '',
})

const errors = ref({})
const generalError = ref(null)

function fieldError(field) {
  return errors.value[field]?.[0]
}

async function submit() {
  errors.value = {}
  generalError.value = null
  try {
    await auth.register({ ...form, phone: form.phone.trim() || null })
    // Owners land on their new dashboard.
    router.push(auth.homeRoute)
  } catch (err) {
    errors.value = err?.response?.data?.errors || {}
    if (!Object.keys(errors.value).length) {
      generalError.value = err?.response?.data?.message || 'Registration failed. Please try again.'
    }
  }
}

const inputClass =
  'w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200'
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-stone-100 px-4 py-10">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-black/5">
      <h1 class="text-2xl font-bold text-stone-900">Create your restaurant</h1>
      <p class="mt-1 text-sm text-stone-500">Set up your account and start managing your menu.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Restaurant name</label>
          <input v-model="form.restaurant_name" type="text" required :class="inputClass" />
          <p v-if="fieldError('restaurant_name')" class="mt-1 text-xs text-red-600">
            {{ fieldError('restaurant_name') }}
          </p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">First name</label>
            <input v-model="form.first_name" type="text" required :class="inputClass" />
            <p v-if="fieldError('first_name')" class="mt-1 text-xs text-red-600">
              {{ fieldError('first_name') }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Last name</label>
            <input v-model="form.last_name" type="text" required :class="inputClass" />
            <p v-if="fieldError('last_name')" class="mt-1 text-xs text-red-600">
              {{ fieldError('last_name') }}
            </p>
          </div>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Email</label>
          <input v-model="form.email" type="email" required autocomplete="username" :class="inputClass" />
          <p v-if="fieldError('email')" class="mt-1 text-xs text-red-600">{{ fieldError('email') }}</p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Phone (optional)</label>
          <input v-model="form.phone" type="text" :class="inputClass" />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Password</label>
          <input
            v-model="form.password"
            type="password"
            required
            autocomplete="new-password"
            :class="inputClass"
          />
          <p v-if="fieldError('password')" class="mt-1 text-xs text-red-600">
            {{ fieldError('password') }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-stone-700">Confirm password</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            required
            autocomplete="new-password"
            :class="inputClass"
          />
        </div>

        <p v-if="generalError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
          {{ generalError }}
        </p>

        <button
          type="submit"
          :disabled="auth.loading"
          class="w-full rounded-lg bg-amber-500 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60"
        >
          {{ auth.loading ? 'Creating…' : 'Create account' }}
        </button>
      </form>

      <p class="mt-4 text-center text-xs text-stone-400">
        Already have an account?
        <RouterLink :to="{ name: 'login' }" class="font-medium text-amber-600 hover:underline">
          Sign in
        </RouterLink>
      </p>
    </div>
  </div>
</template>
