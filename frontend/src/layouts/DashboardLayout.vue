<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { RouterView, RouterLink, useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import {
  LayoutDashboard,
  ReceiptText,
  ClipboardList,
  ChefHat,
  BookOpen,
  Palette,
  QrCode,
  Users,
  Settings,
  ExternalLink,
  UtensilsCrossed,
  CircleUserRound,
  LogOut,
  Menu as MenuIcon,
  X,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const dashboard = useDashboardStore()
const { restaurant } = storeToRefs(dashboard)
const { user } = storeToRefs(auth)

// Mobile slide-in navigation.
const navOpen = ref(false)
watch(() => route.fullPath, () => (navOpen.value = false))

// Grouped so the sidebar reads as "what's happening now" vs "what I've set up"
// rather than one long undifferentiated list.
const sections = computed(() =>
  [
    {
      label: 'Operations',
      items: [
        { name: 'dashboard-overview', label: 'Overview', icon: LayoutDashboard, exact: true },
        { name: 'dashboard-orders', label: 'Live Orders', icon: ReceiptText, exact: true },
        { name: 'dashboard-orders-history', label: 'All Orders', icon: ClipboardList },
        { name: 'kitchen', label: 'Kitchen Display', icon: ChefHat },
      ],
    },
    {
      label: 'Your restaurant',
      items: [
        { name: 'dashboard-menu', label: 'Menu', icon: BookOpen, exact: true },
        {
          name: 'dashboard-design',
          label: 'Menu Design',
          icon: Palette,
          show: auth.can('restaurant.manage'),
        },
        { name: 'dashboard-tables', label: 'Tables & QR', icon: QrCode },
        { name: 'dashboard-team', label: 'Team', icon: Users, show: auth.can('employees.manage') },
        {
          name: 'dashboard-settings',
          label: 'Settings',
          icon: Settings,
          show: auth.can('restaurant.manage'),
        },
      ],
    },
  ]
    .map((section) => ({ ...section, items: section.items.filter((i) => i.show !== false) }))
    .filter((section) => section.items.length),
)

// The current screen's name, shown in the desktop top bar.
const currentLabel = computed(() => {
  for (const section of sections.value) {
    const match = section.items.find((item) => item.name === route.name)
    if (match) return match.label
  }
  return 'Dashboard'
})

const linkClass =
  'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-ink-600 transition hover:bg-canvas hover:text-ink-900'
const activeClass = 'bg-brand-50/80 text-brand-700 font-semibold'

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
  <div class="flex min-h-screen bg-canvas text-ink-800">
    <!-- Mobile drawer backdrop -->
    <div
      v-if="navOpen"
      class="fixed inset-0 z-40 bg-ink-900/40 backdrop-blur-[2px] sm:hidden"
      @click="navOpen = false"
    ></div>

    <!-- Sidebar: static on desktop, slide-in drawer on mobile -->
    <aside
      class="fixed inset-y-0 left-0 z-50 flex w-[17rem] shrink-0 flex-col border-r border-hairline bg-surface transition-transform duration-200 sm:static sm:z-auto sm:translate-x-0"
      :class="navOpen ? 'translate-x-0 shadow-pop' : '-translate-x-full'"
    >
      <div class="flex items-center gap-1 px-3 py-4">
        <!-- The brand block doubles as the way home, as it does on most apps. -->
        <RouterLink
          :to="{ name: 'dashboard-overview' }"
          class="flex min-w-0 flex-1 items-center gap-3 rounded-xl px-3 py-1.5 transition hover:bg-canvas"
          :title="`${restaurant?.name || 'Restaurant Hub'} — go to Overview`"
        >
          <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow-[0_6px_16px_-6px_rgba(5,150,105,0.8)]"
          >
            <UtensilsCrossed :size="18" :stroke-width="2.25" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-ink-900">
              {{ restaurant?.name || 'Restaurant Hub' }}
            </span>
            <span class="block truncate text-xs text-ink-400">Restaurant Hub</span>
          </span>
        </RouterLink>
        <button
          class="shrink-0 rounded-lg p-1 text-ink-400 transition hover:bg-canvas sm:hidden"
          aria-label="Close menu"
          @click="navOpen = false"
        >
          <X :size="20" />
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 pb-3">
        <div v-for="section in sections" :key="section.label" class="mb-5 last:mb-0">
          <p class="eyebrow px-3 pb-1.5">{{ section.label }}</p>
          <div class="space-y-0.5">
            <RouterLink
              v-for="item in section.items"
              :key="item.name"
              :to="{ name: item.name }"
              :class="linkClass"
              :active-class="item.exact ? undefined : activeClass"
              :exact-active-class="item.exact ? activeClass : undefined"
            >
              <component :is="item.icon" :size="17" class="shrink-0" />
              {{ item.label }}
            </RouterLink>
          </div>
        </div>

        <div class="mt-5 px-1">
          <a
            :href="restaurant ? `/r/${restaurant.slug}` : '/'"
            target="_blank"
            class="flex items-center justify-between gap-2 rounded-xl border border-dashed border-hairline-strong px-3 py-2.5 text-sm font-medium text-ink-500 transition hover:border-brand-300 hover:text-brand-700"
          >
            View customer menu
            <ExternalLink :size="15" class="shrink-0" />
          </a>
        </div>
      </nav>

      <div class="border-t border-hairline p-3">
        <RouterLink :to="{ name: 'profile' }" :class="[linkClass, 'gap-3']">
          <CircleUserRound :size="17" class="shrink-0" />
          <span class="min-w-0 flex-1 truncate">{{ user?.full_name || 'My Profile' }}</span>
        </RouterLink>
        <button :class="[linkClass, 'mt-0.5 w-full text-left']" @click="logout">
          <LogOut :size="17" class="shrink-0" />
          Sign out
        </button>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col">
      <!-- Mobile bar -->
      <header
        class="sticky top-0 z-30 flex items-center gap-3 border-b border-hairline bg-surface/85 px-4 py-3 backdrop-blur sm:hidden"
      >
        <button
          class="rounded-lg p-1.5 text-ink-600 transition hover:bg-canvas"
          aria-label="Open menu"
          @click="navOpen = true"
        >
          <MenuIcon :size="22" />
        </button>
        <span class="min-w-0 flex-1 truncate font-semibold text-ink-900">
          {{ restaurant?.name || 'Dashboard' }}
        </span>
        <button
          class="flex items-center gap-1.5 text-sm text-ink-500 transition hover:text-ink-800"
          @click="logout"
        >
          <LogOut :size="16" /> Sign out
        </button>
      </header>

      <!-- Desktop bar: keeps the current screen named and the public menu one click away -->
      <header
        class="sticky top-0 z-30 hidden items-center gap-4 border-b border-hairline bg-surface/80 px-8 py-3.5 backdrop-blur sm:flex"
      >
        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-ink-800">
          {{ currentLabel }}
        </span>
        <a
          v-if="restaurant"
          :href="`/r/${restaurant.slug}`"
          target="_blank"
          class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-ink-500 transition hover:bg-canvas hover:text-ink-800"
        >
          <ExternalLink :size="14" /> /r/{{ restaurant.slug }}
        </a>
        <RouterLink
          :to="{ name: 'profile' }"
          class="flex h-8 w-8 items-center justify-center rounded-full bg-canvas text-xs font-semibold text-ink-600 transition hover:bg-hairline"
          :title="user?.full_name || 'My Profile'"
        >
          {{ (user?.full_name || '?').charAt(0).toUpperCase() }}
        </RouterLink>
      </header>

      <main class="flex-1 overflow-y-auto p-5 sm:p-8">
        <div class="mx-auto w-full max-w-[84rem]">
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>
