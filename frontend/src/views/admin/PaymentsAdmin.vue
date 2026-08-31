<script setup>
import { ref, computed, onMounted } from 'vue'
import { RefreshCw, Check, CircleAlert, Clock, ExternalLink } from 'lucide-vue-next'
import { getPendingPayments, confirmPayment } from '@/services/billing'
import { useBillingStore } from '@/stores/billing'
import { formatPrice } from '@/utils/format'

// This screen owns the freshest count, so it keeps the nav badge in step.
const billing = useBillingStore()

/**
 * The billing work queue.
 *
 * With payment taken out of band, this screen is where money actually gets
 * recognised: a transfer lands, an admin confirms the matching row, and that
 * restaurant's access is extended.
 */
const pending = ref([])
const recent = ref([])
const loading = ref(true)
const error = ref(null)
const busyId = ref(null)
const notice = ref(null)

// Two-step confirm: extending someone's paid access is not undoable here.
const confirmingId = ref(null)

const total = computed(() =>
  pending.value.reduce((sum, p) => sum + p.amount, 0),
)

// Only meaningful when everything queued is in one currency, which it is today.
const currency = computed(() => pending.value[0]?.currency || 'AMD')

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await getPendingPayments()
    pending.value = data.pending || []
    recent.value = data.recently_confirmed || []
    billing.setPendingCount(pending.value.length)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load payments.'
  } finally {
    loading.value = false
  }
}

async function confirm(payment) {
  busyId.value = payment.id
  error.value = null
  try {
    const result = await confirmPayment(payment.id)
    notice.value = `${payment.restaurant.name} is now active until ${formatDate(result.current_period_end)}.`
    confirmingId.value = null
    await load()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not confirm the payment.'
  } finally {
    busyId.value = null
  }
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function waitingFor(iso) {
  if (!iso) return ''
  const hours = Math.floor((Date.now() - new Date(iso).getTime()) / 3600000)
  if (hours < 1) return 'just now'
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days} day${days === 1 ? '' : 's'} ago`
}

/** Anything sitting unconfirmed for over two days deserves a nudge. */
function isStale(iso) {
  return iso && Date.now() - new Date(iso).getTime() > 2 * 86400000
}

onMounted(load)
</script>

<template>
  <div>
    <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">
          Payments
        </h1>
        <p class="text-sm text-ink-500">
          Confirm subscription payments to extend a restaurant's access.
        </p>
      </div>
      <button
        class="btn-ghost flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium disabled:opacity-60"
        :disabled="loading"
        @click="load"
      >
        <RefreshCw :size="16" :class="loading && 'animate-spin'" />
        Refresh
      </button>
    </header>

    <p v-if="notice" class="mb-4 flex items-center gap-2 rounded-xl bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700">
      <Check :size="16" /> {{ notice }}
    </p>
    <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

    <p v-if="loading && !pending.length && !recent.length" class="text-sm text-ink-400">Loading…</p>

    <template v-else>
      <!-- Awaiting confirmation -->
      <section class="mb-6">
        <div class="mb-3 flex items-baseline justify-between gap-3">
          <h2 class="font-semibold text-ink-900">
            Awaiting confirmation
            <span v-if="pending.length" class="ml-1 text-sm font-normal text-ink-500">
              ({{ pending.length }})
            </span>
          </h2>
          <span v-if="pending.length" class="text-sm text-ink-500">
            {{ formatPrice(total, currency) }} outstanding
          </span>
        </div>

        <p v-if="!pending.length" class="card p-8 text-center text-sm text-ink-400">
          Nothing waiting. Payments appear here when a restaurant chooses a plan.
        </p>

        <div v-else class="card overflow-hidden">
          <ul class="divide-y divide-hairline">
            <li
              v-for="p in pending"
              :key="p.id"
              class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3.5"
              :class="busyId === p.id && 'opacity-50'"
            >
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <p class="truncate font-medium text-ink-900">{{ p.restaurant.name }}</p>
                  <a
                    :href="`/r/${p.restaurant.slug}`"
                    target="_blank"
                    class="shrink-0 text-ink-300 transition hover:text-brand-600"
                    :title="`Open /r/${p.restaurant.slug}`"
                  >
                    <ExternalLink :size="13" />
                  </a>
                </div>
                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-400">
                  <Clock :size="12" />
                  requested {{ waitingFor(p.requested_at) }}
                  <span
                    v-if="isStale(p.requested_at)"
                    class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-1.5 py-0.5 font-semibold text-amber-700"
                  >
                    <CircleAlert :size="11" /> waiting a while
                  </span>
                </p>
              </div>

              <div class="text-sm">
                <p class="font-medium text-ink-800">{{ p.plan }}</p>
                <p class="text-xs capitalize text-ink-400">{{ p.interval }}</p>
              </div>

              <div class="w-28 text-right text-sm font-semibold tabular-nums text-ink-900">
                {{ formatPrice(p.amount, p.currency) }}
              </div>

              <div class="flex shrink-0 items-center gap-2">
                <template v-if="confirmingId === p.id">
                  <span class="text-xs text-ink-500">Confirm payment received?</span>
                  <button
                    class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-600 disabled:opacity-60"
                    :disabled="busyId === p.id"
                    @click="confirm(p)"
                  >
                    {{ busyId === p.id ? 'Confirming…' : 'Yes, confirm' }}
                  </button>
                  <button
                    class="rounded-lg px-2 py-1.5 text-xs font-medium text-ink-500 transition hover:text-ink-800"
                    @click="confirmingId = null"
                  >
                    Cancel
                  </button>
                </template>
                <button
                  v-else
                  class="btn-ghost flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold"
                  @click="confirmingId = p.id"
                >
                  <Check :size="14" /> Mark paid
                </button>
              </div>
            </li>
          </ul>
        </div>
      </section>

      <!-- Recently confirmed, so an admin can see their action landed -->
      <section v-if="recent.length">
        <h2 class="mb-3 font-semibold text-ink-900">Recently confirmed</h2>
        <div class="card overflow-hidden">
          <ul class="divide-y divide-hairline">
            <li
              v-for="p in recent"
              :key="p.id"
              class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 text-sm"
            >
              <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                <Check :size="13" :stroke-width="2.5" />
              </span>
              <span class="min-w-0 flex-1 truncate font-medium text-ink-800">
                {{ p.restaurant.name }}
              </span>
              <span class="text-xs capitalize text-ink-400">{{ p.plan }} · {{ p.interval }}</span>
              <span class="w-24 text-right tabular-nums text-ink-600">
                {{ formatPrice(p.amount, p.currency) }}
              </span>
              <span class="w-40 text-right text-xs text-ink-400">
                active until {{ formatDate(p.period_end) }}
              </span>
            </li>
          </ul>
        </div>
      </section>
    </template>
  </div>
</template>
