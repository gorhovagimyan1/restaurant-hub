<script setup>
import { ref, onMounted } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/http'

const router = useRouter()
const auth = useAuthStore()
const { user } = storeToRefs(auth)

const restaurantName = ref('')

onMounted(async () => {
  if (!user.value) {
    try {
      await auth.fetchMe()
    } catch {
      return // interceptor handles the 401 redirect
    }
  }
  try {
    // Auth-only endpoint (no manager permission needed).
    const { data } = await http.get('/dashboard/restaurant')
    restaurantName.value = data.data?.name || ''
  } catch {
    // non-fatal — the board still works without the name
  }
})

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-stone-900 text-stone-100">
    <header class="flex items-center justify-between border-b border-stone-700 px-5 py-3">
      <div class="flex items-center gap-3">
        <span class="text-xl">👨‍🍳</span>
        <div>
          <p class="text-sm font-bold leading-tight">Kitchen Display</p>
          <p class="text-xs text-stone-400">{{ restaurantName || '—' }}</p>
        </div>
      </div>
      <div class="flex items-center gap-3 text-sm">
        <span class="hidden text-stone-400 sm:inline">{{ user?.full_name }}</span>
        <button
          class="rounded-lg border border-stone-600 px-3 py-1.5 font-medium text-stone-300 hover:bg-stone-800"
          @click="logout"
        >
          Sign out
        </button>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto p-5">
      <RouterView />
    </main>
  </div>
</template>
