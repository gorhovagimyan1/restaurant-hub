<script setup>
import { ref, computed, onMounted, watch } from 'vue'
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
  set.has(id) ? set.delete(id) : set.add(id)
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
        <h1 class="text-2xl font-bold text-stone-900">All orders</h1>
        <p class="text-sm text-stone-500">{{ rangeLabel }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="search"
          type="search"
          placeholder="Search order #, table, name…"
          class="w-56 rounded-full border border-stone-200 px-4 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
        />
        <select
          v-model="status"
          class="rounded-full border border-stone-200 px-3 py-2 text-sm outline-none focus:border-brand-400"
        >
          <option value="">All statuses</option>
          <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
    </header>

    <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5">
      <!-- Header row -->
      <div class="hidden grid-cols-12 gap-2 border-b border-stone-100 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-stone-400 sm:grid">
        <span class="col-span-3">Order</span>
        <span class="col-span-2">Table</span>
        <span class="col-span-3">When</span>
        <span class="col-span-2 text-right">Total</span>
        <span class="col-span-2 text-right">Status</span>
      </div>

      <div v-if="loading && !orders.length" class="px-4 py-16 text-center text-sm text-stone-400">Loading…</div>
      <div v-else-if="!orders.length" class="px-4 py-16 text-center text-sm text-stone-400">No orders found.</div>

      <ul v-else class="divide-y divide-stone-100">
        <li v-for="order in orders" :key="order.id">
          <button
            class="grid w-full grid-cols-2 items-center gap-2 px-4 py-3 text-left hover:bg-stone-50 sm:grid-cols-12"
            @click="toggle(order.id)"
          >
            <span class="col-span-1 font-semibold text-stone-800 sm:col-span-3">
              {{ order.order_number }}
              <span class="ml-1 text-xs text-stone-400">{{ expanded.has(order.id) ? '▾' : '▸' }}</span>
            </span>
            <span class="col-span-1 text-sm text-stone-600 sm:col-span-2">{{ order.table?.name || '—' }}</span>
            <span class="col-span-1 text-sm text-stone-500 sm:col-span-3">{{ formatWhen(order.created_at) }}</span>
            <span class="col-span-1 text-right font-semibold text-stone-800 sm:col-span-2">{{ formatPrice(order.total) }}</span>
            <span class="col-span-1 flex justify-end sm:col-span-2">
              <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusMeta(order.status).badge">
                {{ statusMeta(order.status).label }}
              </span>
            </span>
          </button>

          <!-- Expanded item detail -->
          <div v-if="expanded.has(order.id)" class="bg-stone-50 px-4 py-3">
            <ul class="space-y-1.5 text-sm">
              <li v-for="item in order.items" :key="item.id" class="flex items-center justify-between gap-3">
                <span class="text-stone-700">
                  <span class="font-semibold text-stone-900">{{ item.quantity }}×</span> {{ item.product_name }}
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
                  <span class="w-20 text-right tabular-nums text-stone-500">{{ formatPrice(item.total_price) }}</span>
                </span>
              </li>
            </ul>
            <div class="mt-2 flex flex-wrap gap-x-6 gap-y-1 border-t border-stone-200 pt-2 text-xs text-stone-500">
              <span v-if="order.customer_name">👤 {{ order.customer_name }}</span>
              <span v-if="order.notes">📝 {{ order.notes }}</span>
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
        class="rounded-full border border-stone-200 px-4 py-1.5 text-sm font-medium text-stone-600 hover:bg-stone-100 disabled:opacity-40"
        :disabled="meta.current_page <= 1"
        @click="page = meta.current_page - 1"
      >
        ‹ Prev
      </button>
      <span class="text-sm text-stone-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button
        class="rounded-full border border-stone-200 px-4 py-1.5 text-sm font-medium text-stone-600 hover:bg-stone-100 disabled:opacity-40"
        :disabled="meta.current_page >= meta.last_page"
        @click="page = meta.current_page + 1"
      >
        Next ›
      </button>
    </div>
  </div>
</template>
