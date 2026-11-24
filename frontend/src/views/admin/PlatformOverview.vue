<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Store, Users, ReceiptText, Wallet, RefreshCw } from 'lucide-vue-next'
import { getPlatformOverview, restaurantStatusMeta } from '@/services/admin'
import { formatPrice } from '@/utils/format'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const router = useRouter()

const data = ref(null)
const loading = ref(true)
const error = ref(null)

const tiles = computed(() => {
  const d = data.value
  if (!d) return []
  return [
    {
      label: t('admin.overview.revenueToday'),
      value: formatPrice(d.orders.revenue_today, 'AMD'),
      hint: t('admin.overview.completedCount', { n: d.orders.completed_today }),
      icon: Wallet,
      featured: true,
    },
    {
      label: t('admin.overview.restaurants'),
      value: d.restaurants.total,
      hint: t('admin.overview.restaurantsHint', { active: d.restaurants.active, suspended: d.restaurants.suspended }),
      icon: Store,
      to: 'admin-restaurants',
    },
    {
      label: t('admin.overview.users'),
      value: d.users.total,
      hint: t('admin.overview.usersHint', { n: d.users.active }),
      icon: Users,
      to: 'admin-users',
    },
    {
      label: t('admin.overview.ordersToday'),
      value: d.orders.today,
      hint: t('admin.overview.ordersTotal', { n: d.orders.total }),
      icon: ReceiptText,
    },
  ]
})

function timeAgo(iso) {
  if (!iso) return ''
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (mins < 1) return t('admin.time.justNow')
  if (mins < 60) return t('admin.time.minutesAgo', { n: mins })
  const hrs = Math.round(mins / 60)
  if (hrs < 24) return t('admin.time.hoursAgo', { n: hrs })
  return t('admin.time.daysAgo', Math.round(hrs / 24))
}

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = await getPlatformOverview()
  } catch (err) {
    error.value = err?.response?.data?.message || t('admin.overview.loadError')
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
    <header class="mb-5 flex items-center justify-between gap-3">
      <div>
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ t('admin.overview.title') }}</h1>
        <p class="text-sm text-ink-500">{{ t('admin.overview.subtitle') }}</p>
      </div>
      <button
        class="flex shrink-0 items-center gap-2 rounded-xl border border-hairline bg-white px-3.5 py-2 text-sm font-medium text-ink-600 transition hover:bg-canvas disabled:opacity-60"
        :disabled="loading"
        @click="load"
      >
        <RefreshCw :size="16" :class="loading && 'animate-spin'" />
        {{ loading ? t('admin.refreshing') : t('admin.refresh') }}
      </button>
    </header>

    <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
    <p v-if="loading && !data" class="text-sm text-ink-400">{{ t('common.loading') }}</p>

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
              : 'bg-white ring-1 ring-hairline text-ink-900',
            tile.to ? 'cursor-pointer hover:-translate-y-0.5 hover:shadow-md' : 'cursor-default',
          ]"
          @click="go(tile.to)"
        >
          <div class="flex items-start justify-between">
            <p
              class="text-xs font-medium uppercase tracking-wide"
              :class="tile.featured ? 'text-white/80' : 'text-ink-400'"
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
          <p class="mt-1 text-xs" :class="tile.featured ? 'text-white/75' : 'text-ink-400'">
            {{ tile.hint }}
          </p>
        </button>
      </div>

      <!-- Recent restaurants -->
      <section class="card p-5">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="font-bold text-ink-900">{{ t('admin.overview.newest') }}</h2>
          <button class="text-xs text-brand-600 hover:underline" @click="go('admin-restaurants')">
            {{ t('admin.overview.viewAll') }}
          </button>
        </div>

        <p v-if="!data.recent_restaurants.length" class="py-4 text-sm text-ink-400">
          {{ t('admin.overview.none') }}
        </p>
        <ul v-else class="divide-y divide-hairline">
          <li
            v-for="r in data.recent_restaurants"
            :key="r.id"
            class="flex items-center gap-3 py-2.5"
          >
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-ink-800">{{ r.name }}</p>
              <p class="text-xs text-ink-400">
                {{ t('admin.userCount', r.users_count) }}
                · {{ t('admin.orderCount', r.orders_count) }}
                · {{ timeAgo(r.created_at) }}
              </p>
            </div>
            <span
              class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
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
