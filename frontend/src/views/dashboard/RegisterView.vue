<script setup>
import { useI18n } from 'vue-i18n'
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AuthShell from '@/components/auth/AuthShell.vue'

const { t } = useI18n()

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
    // Straight to pricing rather than the dashboard: a new owner should see
    // what the product costs up front. They can still take the trial from
    // there, so this informs rather than blocks.
    router.push({ name: 'checkout', query: { welcome: '1' } })
  } catch (err) {
    errors.value = err?.response?.data?.errors || {}
    if (!Object.keys(errors.value).length) {
      generalError.value = err?.response?.data?.message || t('auth.register.failed')
    }
  }
}

const inputClass =
  'w-full field'
</script>

<template>
  <AuthShell :title="t('auth.register.title')" :subtitle="t('auth.register.subtitle')">
    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ t('auth.register.restaurantName') }}</label>
        <input v-model="form.restaurant_name" type="text" required :class="inputClass" />
        <p v-if="fieldError('restaurant_name')" class="mt-1 text-xs text-red-600">
          {{ fieldError('restaurant_name') }}
        </p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ t('auth.firstName') }}</label>
          <input v-model="form.first_name" type="text" required :class="inputClass" />
          <p v-if="fieldError('first_name')" class="mt-1 text-xs text-red-600">
            {{ fieldError('first_name') }}
          </p>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ t('auth.lastName') }}</label>
          <input v-model="form.last_name" type="text" required :class="inputClass" />
          <p v-if="fieldError('last_name')" class="mt-1 text-xs text-red-600">
            {{ fieldError('last_name') }}
          </p>
        </div>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ t('auth.email') }}</label>
        <input v-model="form.email" type="email" required autocomplete="username" :class="inputClass" />
        <p v-if="fieldError('email')" class="mt-1 text-xs text-red-600">{{ fieldError('email') }}</p>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ t('auth.register.phoneOptional') }}</label>
        <input v-model="form.phone" type="text" :class="inputClass" />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ t('auth.password') }}</label>
          <input v-model="form.password" type="password" required autocomplete="new-password" :class="inputClass" />
          <p v-if="fieldError('password')" class="mt-1 text-xs text-red-600">
            {{ fieldError('password') }}
          </p>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-ink-700">{{ t('auth.register.confirm') }}</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            required
            autocomplete="new-password"
            :class="inputClass"
          />
        </div>
      </div>

      <p v-if="generalError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600">
        {{ generalError }}
      </p>

      <button
        type="submit"
        :disabled="auth.loading"
        class="btn-brand w-full rounded-xl py-3 text-sm font-semibold transition hover:-translate-y-0.5 disabled:opacity-60"
      >
        {{ auth.loading ? t('auth.register.submitting') : t('auth.register.submit') }}
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-500">
      {{ t('auth.register.have') }}
      <RouterLink :to="{ name: 'login' }" class="font-semibold text-brand-600 hover:underline">
        {{ t('auth.register.signIn') }}
      </RouterLink>
    </p>
  </AuthShell>
</template>
