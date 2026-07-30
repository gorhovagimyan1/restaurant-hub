<script setup>
import { ref, onMounted } from 'vue'
import { RouterView, RouterLink, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { ChefHat } from 'lucide-vue-next'
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
      <!--
        The brand mark is the way out of the board. It targets the signed-in
        role's own home, so a manager who opened the display lands back on the
        dashboard while kitchen-only staff stay here (they have no dashboard).
      -->
      <RouterLink
        :to="auth.homeRoute"
        class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-1 transition hover:bg-stone-800"
        title="Go to your home screen"
      >
        <ChefHat :size="22" class="text-brand-400" />
        <span>
          <span class="block text-sm font-bold leading-tight">Kitchen Display</span>
          <span class="block text-xs text-stone-400">{{ restaurantName || '—' }}</span>
        </span>
      </RouterLink>
      <div class="flex items-center gap-3 text-sm">
        <RouterLink
          :to="{ name: 'profile' }"
          class="hidden text-stone-400 hover:text-brand-400 sm:inline"
        >
          {{ user?.full_name }}
        </RouterLink>
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
