<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Store, Users, ReceiptText, Wallet, RefreshCw } from 'lucide-vue-next'
import { getPlatformOverview, restaurantStatusMeta } from '@/services/admin'
import { formatPrice } from '@/utils/format'

const router = useRouter()

const data = ref(null)
const loading = ref(true)
const error = ref(null)

const tiles = computed(() => {
  const d = data.value
  if (!d) return []
  return [
    {
      label: "Today's revenue",
      value: formatPrice(d.orders.revenue_today, 'AMD'),
      hint: `${d.orders.completed_today} completed`,
      icon: Wallet,
      featured: true,
    },
    {
      label: 'Restaurants',
      value: d.restaurants.total,
      hint: `${d.restaurants.active} active · ${d.restaurants.suspended} suspended`,
      icon: Store,
      to: 'admin-restaurants',
    },
    {
      label: 'Users',
      value: d.users.total,
      hint: `${d.users.active} active`,
      icon: Users,
      to: 'admin-users',
    },
    {
      label: "Today's orders",
      value: d.orders.today,
      hint: `${d.orders.total} all-time`,
      icon: ReceiptText,
    },
  ]
})

function timeAgo(iso) {
  if (!iso) return ''
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.round(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  return `${Math.round(hrs / 24)}d ago`
}

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = await getPlatformOverview()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load the platform overview.'
  } finally {
    loading.value = false
  }
}

function go(name) {
  if (name) router.push({ name })
}

onMounted(load)
</script>

<template>
  <div>
    <header class="mb-5 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-stone-900">Platform Overview</h1>
        <p class="text-sm text-stone-500">Every restaurant and user, at a glance.</p>
      </div>
      <button
        class="flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-sm font-medium text-stone-600 transition hover:bg-stone-100 disabled:opacity-60"
        :disabled="loading"
        @click="load"
      >
        <RefreshCw :size="16" :class="loading && 'animate-spin'" />
        {{ loading ? 'Refreshing…' : 'Refresh' }}
      </button>
    </header>

    <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading && !data" class="text-sm text-stone-400">Loading…</p>

    <div v-else-if="data" class="space-y-6">
      <!-- Stat tiles -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <button
          v-for="tile in tiles"
          :key="tile.label"
          type="button"
          class="group relative overflow-hidden rounded-2xl p-5 text-left shadow-sm transition"
          :class="[
            tile.featured
              ? 'bg-gradient-to-br from-slate-800 via-slate-700 to-slate-900 text-white'
              : 'bg-white ring-1 ring-black/5 text-stone-900',
            tile.to ? 'cursor-pointer hover:-translate-y-0.5 hover:shadow-md' : 'cursor-default',
          ]"
          @click="go(tile.to)"
        >
          <div class="flex items-start justify-between">
            <p
              class="text-xs font-medium uppercase tracking-wide"
              :class="tile.featured ? 'text-white/80' : 'text-stone-400'"
            >
              {{ tile.label }}
            </p>
            <span
              class="flex h-9 w-9 items-center justify-center rounded-xl"
              :class="tile.featured ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
            >
              <component :is="tile.icon" :size="18" />
            </span>
          </div>
          <p class="mt-2 text-3xl font-extrabold tracking-tight">{{ tile.value }}</p>
          <p class="mt-1 text-xs" :class="tile.featured ? 'text-white/75' : 'text-stone-400'">
            {{ tile.hint }}
          </p>
        </button>
      </div>

      <!-- Recent restaurants -->
      <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-bold text-stone-900">Newest restaurants</h2>
          <button class="text-xs text-brand-600 hover:underline" @click="go('admin-restaurants')">
            View all
          </button>
        </div>

        <p v-if="!data.recent_restaurants.length" class="py-4 text-sm text-stone-400">
          No restaurants yet.
        </p>
        <ul v-else class="divide-y divide-stone-100">
          <li
            v-for="r in data.recent_restaurants"
            :key="r.id"
            class="flex items-center gap-3 py-2.5"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-stone-800">{{ r.name }}</p>
              <p class="text-xs text-stone-400">
                {{ r.users_count }} user{{ r.users_count === 1 ? '' : 's' }}
                · {{ r.orders_count }} order{{ r.orders_count === 1 ? '' : 's' }}
                · {{ timeAgo(r.created_at) }}
              </p>
            </div>
            <span
              class="rounded-full px-2 py-0.5 text-xs font-medium"
              :class="restaurantStatusMeta(r.status).tone"
            >
              {{ restaurantStatusMeta(r.status).label }}
            </span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
