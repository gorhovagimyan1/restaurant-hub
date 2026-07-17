<script setup>
import { computed, onMounted } from 'vue'
import { RouterView, RouterLink, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import {
  LayoutDashboard,
  ReceiptText,
  ClipboardList,
  ChefHat,
  BookOpen,
  QrCode,
  Users,
  Settings,
  ExternalLink,
  UtensilsCrossed,
  CircleUserRound,
  LogOut,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'

const router = useRouter()
const auth = useAuthStore()
const dashboard = useDashboardStore()
const { restaurant } = storeToRefs(dashboard)
const { user } = storeToRefs(auth)

const nav = computed(() =>
  [
    { name: 'dashboard-overview', label: 'Overview', icon: LayoutDashboard, exact: true },
    { name: 'dashboard-orders', label: 'Live Orders', icon: ReceiptText, exact: true },
    { name: 'dashboard-orders-history', label: 'All Orders', icon: ClipboardList },
    { name: 'kitchen', label: 'Kitchen Display', icon: ChefHat },
    { name: 'dashboard-menu', label: 'Menu Management', icon: BookOpen, exact: true },
    { name: 'dashboard-tables', label: 'Tables & QR', icon: QrCode },
    { name: 'dashboard-team', label: 'Team', icon: Users, show: auth.can('employees.manage') },
    { name: 'dashboard-settings', label: 'Settings', icon: Settings, show: auth.can('restaurant.manage') },
  ].filter((i) => i.show !== false),
)

const linkClass =
  'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-100'
const activeClass = 'bg-brand-50 text-brand-700 font-semibold ring-1 ring-brand-100'

onMounted(async () => {
  if (!user.value) {
    try {
      await auth.fetchMe()
    } catch {
      // interceptor handles the redirect on 401
      return
    }
  }
  // Kitchen/waiter staff don't have menu-management access — send them to the
  // kitchen display instead of loading (and failing) the owner dashboard.
  if (auth.isKitchenOnly) {
    router.replace({ name: 'kitchen' })
    return
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
  <div class="flex min-h-screen bg-stone-50 text-stone-800">
    <!-- Sidebar -->
    <aside class="hidden w-64 shrink-0 flex-col border-r border-stone-200 bg-white sm:flex">
      <div class="flex items-center gap-3 border-b border-stone-200/70 px-5 py-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow-sm shadow-brand-500/30">
          <UtensilsCrossed :size="20" :stroke-width="2.25" />
        </span>
        <div class="min-w-0">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-600">Restaurant Hub</p>
          <p class="truncate font-bold text-stone-900">{{ restaurant?.name || '—' }}</p>
        </div>
      </div>

      <nav class="flex-1 space-y-1 p-3">
        <RouterLink
          v-for="item in nav"
          :key="item.name"
          :to="{ name: item.name }"
          :class="linkClass"
          :active-class="item.exact ? undefined : activeClass"
          :exact-active-class="item.exact ? activeClass : undefined"
        >
          <component :is="item.icon" :size="18" class="shrink-0" />
          {{ item.label }}
        </RouterLink>

        <a
          :href="restaurant ? `/r/${restaurant.slug}` : '/'"
          target="_blank"
          :class="linkClass"
        >
          <ExternalLink :size="18" class="shrink-0" />
          View Customer Menu
        </a>
      </nav>

      <div class="border-t border-stone-200/70 p-3">
        <RouterLink :to="{ name: 'profile' }" :class="linkClass">
          <CircleUserRound :size="18" class="shrink-0" />
          <span class="truncate">{{ user?.full_name || 'My Profile' }}</span>
        </RouterLink>
        <button :class="[linkClass, 'mt-1 w-full text-left']" @click="logout">
          <LogOut :size="18" class="shrink-0" />
          Sign out
        </button>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex items-center justify-between border-b border-stone-200 bg-white px-5 py-3 sm:hidden">
        <span class="font-bold">{{ restaurant?.name || 'Dashboard' }}</span>
        <button class="flex items-center gap-1.5 text-sm text-stone-500" @click="logout">
          <LogOut :size="16" /> Sign out
        </button>
      </header>
      <main class="flex-1 overflow-y-auto p-5">
        <RouterView />
      </main>
    </div>
  </div>
</template>
