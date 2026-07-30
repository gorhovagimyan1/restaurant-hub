<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Wallet, ReceiptText, Flame, Armchair, BellRing, RefreshCw, ArrowRight } from 'lucide-vue-next'
import { getOverview } from '@/services/dashboard'
import { formatPrice } from '@/utils/format'
import PageHeader from '@/components/ui/PageHeader.vue'
import AppCard from '@/components/ui/AppCard.vue'
import StatCard from '@/components/ui/StatCard.vue'

const router = useRouter()

const data = ref(null)
const loading = ref(true)
const error = ref(null)

const currency = computed(() => data.value?.currency || 'AMD')

const statusColors = {
  pending: 'bg-ink-900/5 text-ink-600',
  accepted: 'bg-sky-50 text-sky-700',
  preparing: 'bg-indigo-50 text-indigo-700',
  ready: 'bg-brand-50 text-brand-700',
  served: 'bg-teal-50 text-teal-700',
  completed: 'bg-ink-900/5 text-ink-500',
  cancelled: 'bg-red-50 text-red-600',
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

// The busiest seller sets the bar length for the rest.
const topPeak = computed(() =>
  Math.max(1, ...(data.value?.top_products || []).map((p) => p.quantity)),
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
    <PageHeader title="Overview" subtitle="Today at a glance.">
      <template #actions>
        <button
          class="btn-ghost flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium disabled:opacity-60"
          :disabled="loading"
          @click="load"
        >
          <RefreshCw :size="15" :class="loading && 'animate-spin'" />
          {{ loading ? 'Refreshing…' : 'Refresh' }}
        </button>
      </template>
    </PageHeader>

    <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

    <!-- Skeleton keeps the layout from jumping once the numbers land. -->
    <div v-if="loading && !data" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div v-for="n in 4" :key="n" class="card h-[7.5rem] animate-pulse bg-surface-muted"></div>
    </div>

    <div v-else-if="data" class="space-y-5">
      <!-- Stat tiles -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard
          v-for="tile in tiles"
          :key="tile.label"
          v-bind="tile"
          @open="go"
        />
      </div>

      <!-- Service calls banner -->
      <button
        v-if="serviceTotal"
        type="button"
        class="flex w-full items-center gap-3 rounded-2xl border border-brand-200/70 bg-brand-50/60 px-4 py-3 text-left transition hover:bg-brand-50"
        @click="go('dashboard-orders')"
      >
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-500/10 text-brand-600">
          <BellRing :size="17" />
        </span>
        <span class="min-w-0 flex-1 text-sm font-medium text-brand-800">
          {{ data.service.waiter_calls }} waiter call{{ data.service.waiter_calls === 1 ? '' : 's' }}
          · {{ data.service.bill_requests }} bill request{{ data.service.bill_requests === 1 ? '' : 's' }}
          waiting
        </span>
        <ArrowRight :size="16" class="shrink-0 text-brand-600" />
      </button>

      <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- Recent orders -->
        <AppCard title="Recent orders" flush>
          <template #action>
            <button
              class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand-600 transition hover:bg-brand-50"
              @click="go('dashboard-orders')"
            >
              View all <ArrowRight :size="13" />
            </button>
          </template>

          <p v-if="!data.recent_orders.length" class="px-5 pb-5 text-sm text-ink-400">
            No orders yet today.
          </p>
          <ul v-else class="divide-y divide-hairline">
            <li
              v-for="order in data.recent_orders"
              :key="order.id"
              class="flex items-center gap-3 px-5 py-3"
            >
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-ink-800">
                  {{ order.table?.name || order.order_number }}
                </p>
                <p class="text-xs text-ink-400">{{ timeAgo(order.created_at) }}</p>
              </div>
              <span
                class="rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                :class="statusColors[order.status] || 'bg-ink-900/5 text-ink-600'"
              >
                {{ order.status }}
              </span>
              <span class="w-24 text-right text-sm font-semibold tabular-nums text-ink-800">
                {{ formatPrice(order.total, currency) }}
              </span>
            </li>
          </ul>
        </AppCard>

        <!-- Top products -->
        <AppCard title="Top sellers today" hint="By quantity sold." flush>
          <p v-if="!data.top_products.length" class="px-5 pb-5 text-sm text-ink-400">
            No items sold yet today.
          </p>
          <ul v-else class="space-y-3 px-5 pb-5">
            <li v-for="(product, i) in data.top_products" :key="product.name">
              <div class="flex items-center gap-3">
                <span class="w-4 text-xs font-semibold tabular-nums text-ink-300">{{ i + 1 }}</span>
                <span class="min-w-0 flex-1 truncate text-sm text-ink-800">{{ product.name }}</span>
                <span class="text-sm font-semibold tabular-nums text-ink-600">
                  ×{{ product.quantity }}
                </span>
              </div>
              <!-- A bar makes the gap between #1 and #5 readable at a glance. -->
              <div class="ml-7 mt-1.5 h-1 overflow-hidden rounded-full bg-canvas">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-brand-500 to-accent-500"
                  :style="{ width: `${Math.round((product.quantity / topPeak) * 100)}%` }"
                ></div>
              </div>
            </li>
          </ul>
        </AppCard>
      </div>
    </div>
  </div>
</template>
