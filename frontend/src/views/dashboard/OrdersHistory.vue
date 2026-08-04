<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { User, StickyNote, ChevronDown, ChevronRight } from 'lucide-vue-next'
import { fetchOrderHistory } from '@/services/orders'
import { statusMeta, itemStatusMeta, ORDER_STATUS } from '@/utils/orderStatus'
import { formatPrice } from '@/utils/format'

const orders = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const error = ref(null)

const status = ref('')
const search = ref('')
const page = ref(1)
const expanded = ref(new Set())

const statusOptions = Object.entries(ORDER_STATUS).map(([value, m]) => ({ value, label: m.label }))

let searchTimer = null

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await fetchOrderHistory({
      status: status.value || undefined,
      search: search.value || undefined,
      page: page.value,
    })
    orders.value = data.orders
    meta.value = data.meta
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load orders.'
  } finally {
    loading.value = false
  }
}

// Reset to page 1 when a filter changes.
watch(status, () => {
  page.value = 1
  load()
})
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})
watch(page, load)

function toggle(id) {
  const set = new Set(expanded.value)
  if (set.has(id)) {
    set.delete(id)
  } else {
    set.add(id)
  }
  expanded.value = set
}

function formatWhen(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const rangeLabel = computed(() => {
  if (!meta.value.total) return 'No orders'
  const start = (meta.value.current_page - 1) * 20 + 1
  const end = Math.min(meta.value.current_page * 20, meta.value.total)
  return `${start}–${end} of ${meta.value.total}`
})

onMounted(load)
</script>

<template>
  <div>
    <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">All orders</h1>
        <p class="text-sm text-ink-500">{{ rangeLabel }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="search"
          type="search"
          placeholder="Search order #, table, name…"
          class="w-56 field"
        />
        <select
          v-model="status"
          class="rounded-full border border-hairline px-3 py-2 text-sm outline-none focus:border-brand-400"
        >
          <option value="">All statuses</option>
          <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
    </header>

    <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

    <div class="overflow-hidden card">
      <!-- Header row -->
      <div class="hidden grid-cols-12 gap-2 border-b border-hairline px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-ink-400 sm:grid">
        <span class="col-span-3">Order</span>
        <span class="col-span-2">Table</span>
        <span class="col-span-3">When</span>
        <span class="col-span-2 text-right">Total</span>
        <span class="col-span-2 text-right">Status</span>
      </div>

      <div v-if="loading && !orders.length" class="px-4 py-16 text-center text-sm text-ink-400">Loading…</div>
      <div v-else-if="!orders.length" class="px-4 py-16 text-center text-sm text-ink-400">No orders found.</div>

      <ul v-else class="divide-y divide-hairline">
        <li v-for="order in orders" :key="order.id">
          <button
            class="w-full px-4 py-3 text-left hover:bg-canvas"
            @click="toggle(order.id)"
          >
            <!-- Mobile: stacked -->
            <div class="sm:hidden">
              <div class="flex items-center gap-2">
                <component
                  :is="expanded.has(order.id) ? ChevronDown : ChevronRight"
                  :size="16"
                  class="shrink-0 text-ink-400"
                />
                <span class="min-w-0 flex-1 truncate font-semibold text-ink-800">{{ order.order_number }}</span>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusMeta(order.status).badge">
                  {{ statusMeta(order.status).label }}
                </span>
              </div>
              <div class="mt-1 flex items-center justify-between pl-6 text-sm">
                <span class="truncate text-ink-500">{{ order.table?.name || '—' }} · {{ formatWhen(order.created_at) }}</span>
                <span class="shrink-0 pl-2 font-semibold text-ink-800">{{ formatPrice(order.total) }}</span>
              </div>
            </div>

            <!-- Desktop: table grid -->
            <div class="hidden grid-cols-12 items-center gap-2 sm:grid">
              <span class="col-span-3 flex items-center gap-1.5 font-semibold text-ink-800">
                <component
                  :is="expanded.has(order.id) ? ChevronDown : ChevronRight"
                  :size="14"
                  class="shrink-0 text-ink-400"
                />
                <span class="truncate">{{ order.order_number }}</span>
              </span>
              <span class="col-span-2 text-sm text-ink-600">{{ order.table?.name || '—' }}</span>
              <span class="col-span-3 text-sm text-ink-500">{{ formatWhen(order.created_at) }}</span>
              <span class="col-span-2 text-right font-semibold text-ink-800">{{ formatPrice(order.total) }}</span>
              <span class="col-span-2 flex justify-end">
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusMeta(order.status).badge">
                  {{ statusMeta(order.status).label }}
                </span>
              </span>
            </div>
          </button>

          <!-- Expanded item detail -->
          <div v-if="expanded.has(order.id)" class="bg-canvas px-4 py-3">
            <ul class="space-y-1.5 text-sm">
              <li v-for="item in order.items" :key="item.id" class="flex items-center justify-between gap-3">
                <span class="text-ink-700">
                  <span class="font-semibold text-ink-900">{{ item.quantity }}×</span> {{ item.product_name }}
                  <span v-if="item.notes" class="italic text-brand-600"> · “{{ item.notes }}”</span>
                </span>
                <span class="flex items-center gap-3">
                  <span
                    v-if="item.status"
                    class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                    :class="itemStatusMeta(item.status).badge"
                  >
                    {{ itemStatusMeta(item.status).label }}
                  </span>
                  <span class="w-20 text-right tabular-nums text-ink-500">{{ formatPrice(item.total_price) }}</span>
                </span>
              </li>
            </ul>
            <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 border-t border-hairline pt-2 text-xs text-ink-500">
              <span v-if="order.customer_name" class="inline-flex items-center gap-1"><User :size="12" /> {{ order.customer_name }}</span>
              <span v-if="order.notes" class="inline-flex items-center gap-1"><StickyNote :size="12" /> {{ order.notes }}</span>
              <span v-if="order.service_charge">Service {{ formatPrice(order.service_charge) }}</span>
              <span v-if="order.tax">Tax {{ formatPrice(order.tax) }}</span>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-center gap-3">
      <button
        class="rounded-full border border-hairline px-4 py-1.5 text-sm font-medium text-ink-600 hover:bg-canvas disabled:opacity-40"
        :disabled="meta.current_page <= 1"
        @click="page = meta.current_page - 1"
      >
        ‹ Prev
      </button>
      <span class="text-sm text-ink-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button
        class="rounded-full border border-hairline px-4 py-1.5 text-sm font-medium text-ink-600 hover:bg-canvas disabled:opacity-40"
        :disabled="meta.current_page >= meta.last_page"
        @click="page = meta.current_page + 1"
      >
        Next ›
      </button>
    </div>
  </div>
</template>
