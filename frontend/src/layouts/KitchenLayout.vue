<script setup>
import { useI18n } from 'vue-i18n'
import { ref, computed, onMounted } from 'vue'
import { RouterView, RouterLink, useRouter, useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { ChefHat, Martini } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import http from '@/services/http'
import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue'

const { t } = useI18n()

const router = useRouter()
const route = useRoute()

// Kitchen and bar share this screen; staff switch between them here.
const DISPLAYS = [
  { name: 'kitchen', label: 'shell.kitchenDisplay', icon: ChefHat },
  { name: 'bar', label: 'shell.barDisplay', icon: Martini },
]
const current = computed(() => DISPLAYS.find((d) => d.name === route.name) || DISPLAYS[0])
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
        :title="t('shell.goHome')"
      >
        <component :is="current.icon" :size="22" class="text-brand-400" />
        <span>
          <span class="block text-sm font-bold leading-tight">{{ t(current.label) }}</span>
          <span class="block text-xs text-stone-400">{{ restaurantName || '—' }}</span>
        </span>
      </RouterLink>
      <div class="flex items-center gap-3 text-sm">
        <nav class="flex rounded-full bg-stone-800 p-0.5" :aria-label="t('shell.displays')">
          <RouterLink
            v-for="d in DISPLAYS"
            :key="d.name"
            :to="{ name: d.name }"
            class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition"
            :class="current.name === d.name ? 'bg-stone-100 text-stone-900' : 'text-stone-400 hover:text-stone-100'"
          >
            <component :is="d.icon" :size="14" />
            <span class="hidden sm:inline">{{ t(d.label) }}</span>
          </RouterLink>
        </nav>
        <LanguageSwitcher tone="dark" />
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
          {{ t('shell.signOut') }}
        </button>
      </div>
    </header>

    <main class="flex-1 overflow-y-auto p-5">
      <RouterView />
    </main>
  </div>
</template>
