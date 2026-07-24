<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Wallet, ReceiptText, Flame, Armchair, BellRing, RefreshCw } from 'lucide-vue-next'
import { getOverview } from '@/services/dashboard'
import { formatPrice } from '@/utils/format'

const router = useRouter()

const data = ref(null)
const loading = ref(true)
const error = ref(null)

const currency = computed(() => data.value?.currency || 'AMD')

const statusColors = {
  pending: 'bg-slate-200 text-slate-700',
  accepted: 'bg-sky-100 text-sky-700',
  preparing: 'bg-indigo-100 text-indigo-700',
  ready: 'bg-brand-100 text-brand-700',
  served: 'bg-teal-100 text-teal-700',
  completed: 'bg-stone-100 text-stone-500',
  cancelled: 'bg-red-100 text-red-600',
}

// Primary stat tiles derived from the payload.
const tiles = computed(() => {
  const d = data.value
  if (!d) return []
  return [
    { label: "Today's revenue", value: formatPrice(d.today.revenue, currency.value), hint: `${d.today.completed} completed`, icon: Wallet, featured: true },
    { label: "Today's orders", value: d.today.orders, hint: `avg ${formatPrice(d.today.avg_order, currency.value)}`, icon: ReceiptText },
    { label: 'Active orders', value: d.live.active_orders, hint: `${d.live.pending} pending · ${d.live.ready} ready`, icon: Flame, to: 'dashboard-orders' },
    { label: 'Occupied tables', value: `${d.tables.occupied}/${d.tables.total}`, hint: `${d.tables.available} free`, icon: Armchair, to: 'dashboard-tables' },
  ]
})

const serviceTotal = computed(() =>
  data.value ? data.value.service.waiter_calls + data.value.service.bill_requests : 0,
)

function timeAgo(iso) {
  if (!iso) return ''
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.round(mins / 60)
  return `${hrs}h ago`
}

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = await getOverview()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load the overview.'
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
        <h1 class="text-2xl font-bold text-stone-900">Overview</h1>
        <p class="text-sm text-stone-500">Today at a glance.</p>
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
              ? 'bg-gradient-to-br from-brand-600 via-brand-500 to-accent-500 text-white shadow-brand-500/25'
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
              :class="tile.featured ? 'bg-white/20 text-white' : 'bg-brand-50 text-brand-600'"
            >
              <component :is="tile.icon" :size="18" />
            </span>
          </div>
          <p class="mt-2 text-3xl font-extrabold tracking-tight">{{ tile.value }}</p>
          <p class="mt-1 text-xs" :class="tile.featured ? 'text-white/75' : 'text-stone-400'">
            {{ tile.hint }}
          </p>
          <div
            v-if="tile.featured"
            class="pointer-events-none absolute -bottom-8 -right-8 h-28 w-28 rounded-full bg-white/15 blur-2xl"
          ></div>
        </button>
      </div>

      <!-- Service calls banner -->
      <button
        v-if="serviceTotal"
        type="button"
        class="flex w-full items-center gap-3 rounded-2xl bg-brand-50 px-4 py-3 text-left ring-1 ring-brand-200 transition hover:bg-brand-100"
        @click="go('dashboard-orders')"
      >
        <BellRing :size="20" class="shrink-0 text-brand-600" />
        <span class="text-sm font-medium text-brand-800">
          {{ data.service.waiter_calls }} waiter call{{ data.service.waiter_calls === 1 ? '' : 's' }}
          · {{ data.service.bill_requests }} bill request{{ data.service.bill_requests === 1 ? '' : 's' }}
          waiting
        </span>
      </button>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Recent orders -->
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="font-bold text-stone-900">Recent orders</h2>
            <button class="text-xs text-brand-600 hover:underline" @click="go('dashboard-orders')">
              View all
            </button>
          </div>

          <p v-if="!data.recent_orders.length" class="py-4 text-sm text-stone-400">No orders yet.</p>
          <ul v-else class="divide-y divide-stone-100">
            <li
              v-for="order in data.recent_orders"
              :key="order.id"
              class="flex items-center gap-3 py-2.5"
            >
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-stone-800">
                  {{ order.table?.name || order.order_number }}
                </p>
                <p class="text-xs text-stone-400">{{ timeAgo(order.created_at) }}</p>
              </div>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusColors[order.status] || 'bg-stone-100 text-stone-600'"
              >
                {{ order.status }}
              </span>
              <span class="w-20 text-right text-sm font-semibold tabular-nums text-stone-700">
                {{ formatPrice(order.total, currency) }}
              </span>
            </li>
          </ul>
        </section>

        <!-- Top products -->
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-black/5">
          <h2 class="mb-3 font-bold text-stone-900">Top sellers today</h2>

          <p v-if="!data.top_products.length" class="py-4 text-sm text-stone-400">
            No items sold yet today.
          </p>
          <ul v-else class="space-y-2">
            <li
              v-for="(product, i) in data.top_products"
              :key="product.name"
              class="flex items-center gap-3"
            >
              <span class="w-5 text-sm font-semibold text-stone-400">{{ i + 1 }}</span>
              <span class="min-w-0 flex-1 truncate text-sm text-stone-800">{{ product.name }}</span>
              <span class="text-sm font-semibold tabular-nums text-stone-600">×{{ product.quantity }}</span>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>
