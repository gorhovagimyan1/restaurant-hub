<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useOrdersStore } from '@/stores/orders'
import OrderCard from '@/components/dashboard/OrderCard.vue'
import ServiceCallsBanner from '@/components/dashboard/ServiceCallsBanner.vue'
import { formatPrice } from '@/utils/format'

const store = useOrdersStore()
const { kitchen, openTables, done, loading, error, freshIds, lastUpdated } = storeToRefs(store)

const soundOn = ref(true)
const settlingId = ref(null)

// A tiny WebAudio chime so staff notice new orders without an asset file.
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
    gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4)
    osc.start()
    osc.stop(ctx.currentTime + 0.4)
  } catch {
    // audio not available — silent fallback
  }
}

watch(freshIds, (ids) => {
  if (ids.size > 0) chime()
})

const lastUpdatedLabel = computed(() =>
  lastUpdated.value ? lastUpdated.value.toLocaleTimeString() : '—',
)

async function advance(order, status) {
  try {
    await store.advance(order, status)
    store.acknowledge(order.id)
  } catch {
    // errors surface on the next poll; keep the card in place
  }
}

async function cancel(order) {
  if (!window.confirm(`Cancel order ${order.order_number}?`)) return
  try {
    await store.advance(order, 'cancelled')
  } catch {
    // ignore
  }
}

async function settle(table) {
  if (
    !window.confirm(
      `Close ${table.name} and mark ${formatPrice(table.total)} as paid? This settles all its orders.`,
    )
  )
    return
  settlingId.value = table.id
  try {
    await store.settle(table.id)
  } catch {
    // ignore
  } finally {
    settlingId.value = null
  }
}

onMounted(() => store.start())
onBeforeUnmount(() => store.stop())
</script>

<template>
  <div>
    <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-stone-900">Live orders</h1>
        <p class="text-sm text-stone-500">
          {{ kitchen.length }} in kitchen · {{ openTables.length }} open tables · updated
          {{ lastUpdatedLabel }}
          <span v-if="loading" class="text-stone-400">· refreshing…</span>
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          class="flex items-center gap-1.5 rounded-full border border-stone-200 px-3 py-1.5 text-sm font-medium text-stone-600 hover:bg-stone-100"
          @click="soundOn = !soundOn"
        >
          {{ soundOn ? '🔔 Sound on' : '🔕 Sound off' }}
        </button>
        <button
          class="rounded-full border border-stone-200 px-3 py-1.5 text-sm font-medium text-stone-600 hover:bg-stone-100"
          @click="store.refresh()"
        >
          Refresh
        </button>
      </div>
    </header>

    <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

    <!-- Waiter / bill calls -->
    <ServiceCallsBanner />

    <!-- Open tables / bills -->
    <section v-if="openTables.length" class="mb-8">
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-400">
        Open tables ({{ openTables.length }})
      </h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="table in openTables"
          :key="table.id"
          class="rounded-2xl bg-white p-4 shadow-sm ring-1 transition"
          :class="table.billRequested ? 'ring-2 ring-amber-400' : 'ring-black/5'"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-lg font-bold text-stone-900">{{ table.name }}</p>
              <p class="text-xs text-stone-400">
                {{ table.orders.length }} order{{ table.orders.length === 1 ? '' : 's' }}
              </p>
            </div>
            <span
              v-if="table.billRequested"
              class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700"
            >
              💰 Bill requested
            </span>
          </div>

          <ul class="mt-3 space-y-1 text-sm text-stone-600">
            <li v-for="order in table.orders" :key="order.id" class="flex justify-between gap-2">
              <span class="truncate">
                {{ order.order_number }}
                <span class="text-xs text-stone-400">· {{ order.items?.length || 0 }} items</span>
              </span>
              <span class="shrink-0 tabular-nums">{{ formatPrice(order.total) }}</span>
            </li>
          </ul>

          <div class="mt-3 flex items-center justify-between border-t border-stone-100 pt-3">
            <div>
              <p class="text-xs text-stone-400">Total due</p>
              <p class="text-lg font-bold text-stone-900">{{ formatPrice(table.total) }}</p>
            </div>
            <button
              class="rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600 disabled:opacity-60"
              :disabled="settlingId === table.id"
              @click="settle(table)"
            >
              {{ settlingId === table.id ? 'Closing…' : 'Mark paid & close' }}
            </button>
          </div>
        </article>
      </div>
    </section>

    <!-- Kitchen -->
    <section>
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-400">Kitchen</h2>
      <div
        v-if="!loading && kitchen.length === 0"
        class="rounded-2xl border border-dashed border-stone-200 bg-white/50 px-6 py-16 text-center"
      >
        <p class="text-4xl">🍳</p>
        <h3 class="mt-3 font-semibold text-stone-700">Nothing cooking</h3>
        <p class="mt-1 text-sm text-stone-500">New orders from scanned tables appear here instantly.</p>
      </div>

      <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <OrderCard
          v-for="order in kitchen"
          :key="order.id"
          :order="order"
          :fresh="freshIds.has(order.id)"
          @advance="advance"
          @cancel="cancel"
        />
      </div>
    </section>

    <!-- Settled / cancelled today -->
    <section v-if="done.length" class="mt-10">
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-400">
        Closed today ({{ done.length }})
      </h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <OrderCard v-for="order in done" :key="order.id" :order="order" />
      </div>
    </section>
  </div>
</template>
