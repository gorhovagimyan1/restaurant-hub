<script setup>
import { onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { user } = storeToRefs(auth)

// --- Profile details form ---
const profile = reactive({ first_name: '', last_name: '', email: '', phone: '' })
const profileErrors = ref({})
const profileMessage = ref(null)
const savingProfile = ref(false)

// --- Change password form ---
const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwErrors = ref({})
const pwMessage = ref(null)
const savingPassword = ref(false)

function syncProfile() {
  if (!user.value) return
  profile.first_name = user.value.first_name ?? ''
  profile.last_name = user.value.last_name ?? ''
  profile.email = user.value.email ?? ''
  profile.phone = user.value.phone ?? ''
}

onMounted(async () => {
  if (!user.value) {
    try {
      await auth.fetchMe()
    } catch {
      return // interceptor handles the 401 redirect
    }
  }
  syncProfile()
})

async function saveProfile() {
  profileErrors.value = {}
  profileMessage.value = null
  savingProfile.value = true
  try {
    await auth.updateProfile({ ...profile })
    profileMessage.value = 'Profile updated.'
  } catch (err) {
    profileErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(profileErrors.value).length) {
      profileErrors.value = { _: [err?.response?.data?.message || 'Update failed.'] }
    }
  } finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  pwErrors.value = {}
  pwMessage.value = null
  savingPassword.value = true
  try {
    pwMessage.value = await auth.changePassword({ ...pw })
    pw.current_password = ''
    pw.password = ''
    pw.password_confirmation = ''
  } catch (err) {
    pwErrors.value = err?.response?.data?.errors || {}
    if (!Object.keys(pwErrors.value).length) {
      pwErrors.value = { _: [err?.response?.data?.message || 'Change failed.'] }
    }
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-stone-100 text-stone-800">
    <header class="border-b border-stone-200 bg-white px-5 py-3">
      <div class="mx-auto flex max-w-2xl items-center justify-between">
        <RouterLink :to="auth.homeRoute" class="text-sm text-stone-500 hover:text-brand-600">
          ← Back
        </RouterLink>
        <span class="font-bold">My Profile</span>
        <span class="w-10"></span>
      </div>
    </header>

    <main class="mx-auto max-w-2xl space-y-6 p-5">
      <!-- Profile details -->
      <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <h2 class="text-lg font-bold text-stone-900">Profile details</h2>
        <p v-if="user?.roles?.length" class="mt-1 text-xs text-stone-400">
          Role: {{ user.roles.join(', ') }}
        </p>

        <form class="mt-4 space-y-4" @submit.prevent="saveProfile">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-stone-700">First name</label>
              <input
                v-model="profile.first_name"
                type="text"
                required
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
              />
              <p v-if="profileErrors.first_name" class="mt-1 text-xs text-red-600">
                {{ profileErrors.first_name[0] }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-stone-700">Last name</label>
              <input
                v-model="profile.last_name"
                type="text"
                required
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
              />
              <p v-if="profileErrors.last_name" class="mt-1 text-xs text-red-600">
                {{ profileErrors.last_name[0] }}
              </p>
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Email</label>
            <input
              v-model="profile.email"
              type="email"
              required
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
            />
            <p v-if="profileErrors.email" class="mt-1 text-xs text-red-600">
              {{ profileErrors.email[0] }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Phone</label>
            <input
              v-model="profile.phone"
              type="text"
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
            />
            <p v-if="profileErrors.phone" class="mt-1 text-xs text-red-600">
              {{ profileErrors.phone[0] }}
            </p>
          </div>

          <p v-if="profileErrors._" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
            {{ profileErrors._[0] }}
          </p>
          <p v-if="profileMessage" class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
            {{ profileMessage }}
          </p>

          <button
            type="submit"
            :disabled="savingProfile"
            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
          >
            {{ savingProfile ? 'Saving…' : 'Save changes' }}
          </button>
        </form>
      </section>

      <!-- Change password -->
      <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <h2 class="text-lg font-bold text-stone-900">Change password</h2>
        <p class="mt-1 text-xs text-stone-400">
          Changing your password signs out your other devices.
        </p>

        <form class="mt-4 space-y-4" @submit.prevent="savePassword">
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Current password</label>
            <input
              v-model="pw.current_password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
            />
            <p v-if="pwErrors.current_password" class="mt-1 text-xs text-red-600">
              {{ pwErrors.current_password[0] }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">New password</label>
            <input
              v-model="pw.password"
              type="password"
              required
              autocomplete="new-password"
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
            />
            <p v-if="pwErrors.password" class="mt-1 text-xs text-red-600">
              {{ pwErrors.password[0] }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-stone-700">Confirm new password</label>
            <input
              v-model="pw.password_confirmation"
              type="password"
              required
              autocomplete="new-password"
              class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
            />
          </div>

          <p v-if="pwErrors._" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
            {{ pwErrors._[0] }}
          </p>
          <p v-if="pwMessage" class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
            {{ pwMessage }}
          </p>

          <button
            type="submit"
            :disabled="savingPassword"
            class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
          >
            {{ savingPassword ? 'Updating…' : 'Change password' }}
          </button>
        </form>
      </section>
    </main>
  </div>
</template>
