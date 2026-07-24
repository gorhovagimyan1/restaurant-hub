<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { Bell, BellOff, CookingPot, Check } from 'lucide-vue-next'
import { useOrdersStore } from '@/stores/orders'
import ServiceCallsBanner from '@/components/dashboard/ServiceCallsBanner.vue'
import { nextItemStep } from '@/utils/orderStatus'

const store = useOrdersStore()
const { kitchen, loading, error, freshIds, lastUpdated } = storeToRefs(store)

const soundOn = ref(true)

// Oldest tickets first so nothing waits too long.
const tickets = computed(() =>
  [...kitchen.value].sort((a, b) => new Date(a.created_at) - new Date(b.created_at)),
)

function servedCount(order) {
  return order.items.filter((i) => i.status === 'served').length
}

function elapsed(order) {
  const created = order.ordered_at || order.created_at
  if (!created) return ''
  const mins = Math.max(0, Math.floor((Date.now() - new Date(created).getTime()) / 60000))
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m`
  return `${Math.floor(mins / 60)}h ${mins % 60}m`
}

function urgencyClass(order) {
  const created = order.ordered_at || order.created_at
  if (!created) return 'ring-1 ring-stone-700'
  const mins = (Date.now() - new Date(created).getTime()) / 60000
  if (mins >= 15) return 'ring-2 ring-red-500'
  if (mins >= 8) return 'ring-2 ring-brand-500'
  return 'ring-1 ring-stone-700'
}

function chime() {
  if (!soundOn.value) return
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext
    if (!Ctx) return
    const ctx = new Ctx()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.type = 'sine'
    osc.frequency.setValueAtTime(880, ctx.currentTime)
    osc.frequency.setValueAtTime(1174, ctx.currentTime + 0.12)
    gain.gain.setValueAtTime(0.001, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.45)
    osc.start()
    osc.stop(ctx.currentTime + 0.45)
  } catch {
    // silent fallback
  }
}

watch(freshIds, (ids) => {
  if (ids.size > 0) chime()
})

const lastUpdatedLabel = computed(() =>
  lastUpdated.value ? lastUpdated.value.toLocaleTimeString() : '—',
)

async function advanceItem(order, item, status) {
  try {
    await store.advanceItem(order, item, status)
    store.acknowledge(order.id)
  } catch {
    // resurfaces on the next poll
  }
}
const step = nextItemStep

// Whole-order shortcuts (cascade to every item server-side).
async function advanceOrder(order, status) {
  try {
    await store.advance(order, status)
    store.acknowledge(order.id)
  } catch {
    // resurfaces on the next poll
  }
}

function hasCooking(order) {
  return order.items.some((i) => i.status === 'pending' || i.status === 'preparing')
}

function hasReady(order) {
  return order.items.some((i) => i.status === 'ready')
}

onMounted(() => store.start())
onBeforeUnmount(() => store.stop())
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm text-stone-400">
        {{ tickets.length }} active tickets · updated {{ lastUpdatedLabel }}
        <span v-if="loading" class="text-stone-500">· refreshing…</span>
      </p>
      <button
        class="flex items-center gap-1.5 rounded-full border border-stone-600 px-3 py-1.5 text-sm font-medium text-stone-300 hover:bg-stone-800"
        @click="soundOn = !soundOn"
      >
        <component :is="soundOn ? Bell : BellOff" :size="15" />
        {{ soundOn ? 'Sound on' : 'Sound off' }}
      </button>
    </div>

    <p v-if="error" class="mb-4 rounded-xl bg-red-500/20 px-4 py-3 text-sm text-red-300">{{ error }}</p>

    <!-- Waiter / bill calls -->
    <ServiceCallsBanner dark />

    <div
      v-if="!loading && !tickets.length"
      class="mt-6 rounded-2xl border border-dashed border-stone-700 px-6 py-20 text-center"
    >
      <CookingPot :size="48" class="mx-auto text-stone-600" />
      <h3 class="mt-3 text-lg font-semibold text-stone-200">No tickets right now</h3>
      <p class="mt-1 text-sm text-stone-400">New orders appear here the moment a customer sends them.</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
      <article
        v-for="order in tickets"
        :key="order.id"
        class="rounded-2xl bg-stone-800 p-4"
        :class="[urgencyClass(order), { 'animate-pulse': freshIds.has(order.id) }]"
      >
        <header class="flex items-start justify-between gap-2 border-b border-stone-700 pb-3">
          <div>
            <p class="text-xl font-bold leading-tight text-white">{{ order.table?.name || 'Table' }}</p>
            <p class="text-xs text-stone-400">{{ order.order_number }}</p>
          </div>
          <div class="text-right">
            <span class="rounded-full bg-stone-700 px-2.5 py-1 text-sm font-semibold text-stone-100 tabular-nums">
              ⏱ {{ elapsed(order) }}
            </span>
            <p class="mt-1 text-xs text-stone-400">{{ servedCount(order) }}/{{ order.items.length }} delivered</p>
          </div>
        </header>

        <ul class="mt-3 space-y-2.5">
          <li
            v-for="item in order.items"
            :key="item.id"
            class="flex items-center justify-between gap-3"
            :class="{ 'opacity-45': item.status === 'served' }"
          >
            <div class="min-w-0">
              <p class="text-base font-semibold leading-snug text-white">
                <span class="text-brand-400">{{ item.quantity }}×</span> {{ item.product_name }}
              </p>
              <p v-if="item.notes" class="text-sm font-medium italic text-brand-300">“{{ item.notes }}”</p>
            </div>

            <button
              v-if="step(item.status)"
              class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-bold text-white transition"
              :class="step(item.status).btn"
              @click="advanceItem(order, item, step(item.status).next)"
            >
              {{ step(item.status).action }}
            </button>
            <span v-else class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-teal-400"><Check :size="13" /> Delivered</span>
          </li>
        </ul>

        <!-- Whole-order shortcuts -->
        <footer
          v-if="hasCooking(order) || hasReady(order)"
          class="mt-3 flex gap-2 border-t border-stone-700 pt-3"
        >
          <button
            v-if="hasCooking(order)"
            class="flex-1 rounded-lg bg-emerald-600 py-2 text-xs font-bold text-white transition hover:bg-emerald-500"
            @click="advanceOrder(order, 'ready')"
          >
            Mark all ready
          </button>
          <button
            v-if="hasReady(order)"
            class="flex-1 rounded-lg bg-sky-600 py-2 text-xs font-bold text-white transition hover:bg-sky-500"
            @click="advanceOrder(order, 'served')"
          >
            Deliver all
          </button>
        </footer>
      </article>
    </div>
  </div>
</template>
