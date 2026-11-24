<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { intlLocale } from '@/i18n'
import { Bell, BellOff, ReceiptText, CookingPot } from 'lucide-vue-next'
import { useOrdersStore } from '@/stores/orders'
import OrderCard from '@/components/dashboard/OrderCard.vue'
import ServiceCallsBanner from '@/components/dashboard/ServiceCallsBanner.vue'
import { formatPrice } from '@/utils/format'

const { t } = useI18n()
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
  lastUpdated.value ? lastUpdated.value.toLocaleTimeString(intlLocale()) : '—',
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
  if (!window.confirm(t('orders.cancelConfirm', { number: order.order_number }))) return
  try {
    await store.advance(order, 'cancelled')
  } catch {
    // ignore
  }
}

async function settle(table) {
  if (
    !window.confirm(
      t('orders.settleConfirm', { table: table.name, amount: formatPrice(table.total) }),
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
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">{{ t('orders.liveTitle') }}</h1>
        <p class="text-sm text-ink-500">
          {{ t('orders.liveSummary', { kitchen: kitchen.length, tables: openTables.length, time: lastUpdatedLabel }) }}
          <span v-if="loading" class="text-ink-400">· {{ t('orders.refreshing') }}</span>
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <button
          class="flex items-center gap-1.5 rounded-full border border-hairline px-3 py-1.5 text-sm font-medium text-ink-600 hover:bg-canvas"
          @click="soundOn = !soundOn"
        >
          <component :is="soundOn ? Bell : BellOff" :size="15" />
          {{ soundOn ? t('orders.soundOn') : t('orders.soundOff') }}
        </button>
        <button
          class="rounded-full border border-hairline px-3 py-1.5 text-sm font-medium text-ink-600 hover:bg-canvas"
          @click="store.refresh()"
        >
          {{ t('orders.refresh') }}
        </button>
      </div>
    </header>

    <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

    <!-- Waiter / bill calls -->
    <ServiceCallsBanner />

    <!-- Open tables / bills -->
    <section v-if="openTables.length" class="mb-8">
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-400">
        {{ t('orders.openTables', { n: openTables.length }) }}
      </h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="table in openTables"
          :key="table.id"
          class="card flex flex-col p-4 transition"
          :class="table.billRequested && 'ring-2 ring-brand-400'"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="truncate text-lg font-semibold tracking-tight text-ink-900">{{ table.name }}</p>
              <p class="text-xs text-ink-400">
                {{ t('orders.orderCount', table.orders.length) }}
              </p>
            </div>
            <span
              v-if="table.billRequested"
              class="inline-flex shrink-0 items-center gap-1 rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700"
            >
              <ReceiptText :size="12" class="shrink-0" /> {{ t('orders.billRequested') }}
            </span>
          </div>

          <!-- Order number shrinks first; the item count and amount never get cut. -->
          <ul class="mt-3 flex-1 space-y-1.5">
            <li
              v-for="order in table.orders"
              :key="order.id"
              class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-2 text-sm"
            >
              <span class="truncate font-mono text-xs text-ink-600" :title="order.order_number">
                {{ order.order_number }}
              </span>
              <span class="whitespace-nowrap rounded-full bg-canvas px-2 py-0.5 text-[11px] font-medium text-ink-500">
                {{ t('orders.itemCount', order.items?.length || 0) }}
              </span>
              <span class="whitespace-nowrap text-right font-medium tabular-nums text-ink-800">
                {{ formatPrice(order.total) }}
              </span>
            </li>
          </ul>

          <div class="mt-3 border-t border-hairline pt-3">
            <div class="flex items-baseline justify-between gap-3">
              <p class="text-xs font-medium text-ink-500">{{ t('orders.totalDue') }}</p>
              <p class="whitespace-nowrap text-lg font-bold tabular-nums text-ink-900">{{ formatPrice(table.total) }}</p>
            </div>
            <button
              class="btn-brand mt-3 w-full rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-60"
              :disabled="settlingId === table.id"
              @click="settle(table)"
            >
              {{ settlingId === table.id ? t('orders.closing') : t('orders.markPaid') }}
            </button>
          </div>
        </article>
      </div>
    </section>

    <!-- Kitchen -->
    <section>
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-400">{{ t('orders.kitchen') }}</h2>
      <div
        v-if="!loading && kitchen.length === 0"
        class="rounded-2xl border border-dashed border-hairline bg-white/50 px-6 py-16 text-center"
      >
        <CookingPot :size="40" class="mx-auto text-ink-300" />
        <h3 class="mt-3 font-semibold text-ink-700">{{ t('orders.nothingCooking') }}</h3>
        <p class="mt-1 text-sm text-ink-500">{{ t('orders.nothingCookingHint') }}</p>
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
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-400">
        {{ t('orders.closedToday', { n: done.length }) }}
      </h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <OrderCard v-for="order in done" :key="order.id" :order="order" />
      </div>
    </section>
  </div>
</template>
