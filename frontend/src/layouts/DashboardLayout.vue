<script setup>
import { onMounted } from 'vue'
import { RouterView, RouterLink, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'

const router = useRouter()
const auth = useAuthStore()
const dashboard = useDashboardStore()
const { restaurant } = storeToRefs(dashboard)
const { user } = storeToRefs(auth)

onMounted(async () => {
  if (!user.value) {
    try {
      await auth.fetchMe()
    } catch {
      // interceptor handles the redirect on 401
    }
  }
  if (!restaurant.value) {
    await dashboard.init()
  }
})

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-screen bg-stone-100 text-stone-800">
    <!-- Sidebar -->
    <aside class="hidden w-60 shrink-0 flex-col border-r border-stone-200 bg-white sm:flex">
      <div class="border-b border-stone-200 px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Restaurant Hub</p>
        <p class="mt-1 truncate font-bold text-stone-900">{{ restaurant?.name || '—' }}</p>
      </div>
      <nav class="flex-1 space-y-1 p-3 text-sm">
        <RouterLink
          :to="{ name: 'dashboard-menu' }"
          class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium text-stone-600 hover:bg-stone-100"
          active-class="!bg-amber-100 !text-amber-700"
        >
          🍽️ Menu Management
        </RouterLink>
        <a
          :href="restaurant ? `/r/${restaurant.slug}` : '/'"
          target="_blank"
          class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium text-stone-600 hover:bg-stone-100"
        >
          👁️ View Customer Menu
        </a>
      </nav>
      <div class="border-t border-stone-200 p-3">
        <p class="px-3 text-xs text-stone-400">{{ user?.full_name }}</p>
        <button
          class="mt-1 w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-stone-600 hover:bg-stone-100"
          @click="logout"
        >
          ⏻ Sign out
        </button>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex items-center justify-between border-b border-stone-200 bg-white px-5 py-3 sm:hidden">
        <span class="font-bold">{{ restaurant?.name || 'Dashboard' }}</span>
        <button class="text-sm text-stone-500" @click="logout">Sign out</button>
      </header>
      <main class="flex-1 overflow-y-auto p-5">
        <RouterView />
      </main>
    </div>
  </div>
</template>
