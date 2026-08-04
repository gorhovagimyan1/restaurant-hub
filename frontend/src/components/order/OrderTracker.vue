<script setup>
import { onMounted, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
import { X, RefreshCw, CookingPot, Check, XCircle } from 'lucide-vue-next'
import { useOrderTrackingStore } from '@/stores/orderTracking'
import { formatPrice } from '@/utils/format'
import { CUSTOMER_STEPS, progressIndex, customerLine, itemStatusMeta } from '@/utils/orderStatus'

defineProps({
  tableName: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const tracking = useOrderTrackingStore()
const { orders, currency, loading, error, loadedOnce } = storeToRefs(tracking)

function timeAgo(iso) {
  if (!iso) return ''
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins} min ago`
  return `${Math.round(mins / 60)}h ago`
}

// Opening the sheet is an explicit "is it coming?" — always re-check.
onMounted(() => tracking.startPolling())

// Polling continues in the background while orders are live: the top-bar
// badge depends on it. Only the visibility listener is scoped to the sheet.
onBeforeUnmount(() => {
  if (!tracking.hasActive) tracking.stopPolling()
})
</script>

<template>
  <div class="fixed inset-0 z-50 flex flex-col justify-end sm:items-center sm:justify-center">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

    <!-- Sheet -->
    <div
      class="m-panel relative flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl shadow-xl sm:max-w-md sm:rounded-2xl"
    >
      <header class="m-card-border flex items-center justify-between border-b px-5 py-3.5">
        <div class="min-w-0">
          <h2 class="m-heading font-bold">Your order</h2>
          <p v-if="tableName" class="m-muted-card text-xs">{{ tableName }}</p>
        </div>
        <div class="flex items-center gap-1">
          <button
            class="m-faint rounded-full p-1.5 transition hover:opacity-70 disabled:opacity-40"
            :disabled="loading"
            aria-label="Refresh"
            @click="tracking.refresh()"
          >
            <RefreshCw :size="16" :class="loading && 'animate-spin'" />
          </button>
          <button
            class="m-faint rounded-full p-1.5 transition hover:opacity-70"
            aria-label="Close"
            @click="emit('close')"
          >
            <X :size="20" />
          </button>
        </div>
      </header>

      <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
        <p v-if="!loadedOnce && loading" class="m-faint py-10 text-center text-sm">Checking…</p>
        <p v-else-if="error" class="py-6 text-center text-sm text-red-500">{{ error }}</p>
        <p v-else-if="!orders.length" class="m-faint py-10 text-center text-sm">
          You haven't ordered anything yet.
        </p>

        <div v-else class="space-y-6">
          <article v-for="order in orders" :key="order.order_number">
            <div class="flex items-baseline justify-between gap-2">
              <p class="m-heading text-sm font-bold">{{ order.order_number }}</p>
              <span class="m-faint text-xs">{{ timeAgo(order.created_at) }}</span>
            </div>

            <!-- Cancelled orders get a plain statement, not a dead timeline. -->
            <div
              v-if="order.status === 'cancelled'"
              class="mt-2 flex items-center gap-2 rounded-xl bg-red-500/10 px-3 py-2.5 text-sm font-medium text-red-600"
            >
              <XCircle :size="16" class="shrink-0" />
              {{ customerLine(order.status) }}
            </div>

            <template v-else>
              <!-- Progress timeline -->
              <div class="mt-3">
                <div class="flex items-center">
                  <template v-for="(step, i) in CUSTOMER_STEPS" :key="step.key">
                    <!-- Connector between dots -->
                    <span
                      v-if="i > 0"
                      class="h-0.5 flex-1 rounded-full transition-colors"
                      :style="{
                        backgroundColor:
                          progressIndex(order.status) >= i
                            ? 'var(--m-primary)'
                            : 'var(--m-border)',
                      }"
                    />
                    <span
                      class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-[10px] font-bold transition-colors"
                      :style="
                        progressIndex(order.status) >= i
                          ? { backgroundColor: 'var(--m-primary)', color: 'var(--m-primary-contrast)' }
                          : { backgroundColor: 'var(--m-elevated)', color: 'var(--m-faint)' }
                      "
                    >
                      <Check v-if="progressIndex(order.status) > i" :size="12" :stroke-width="3" />
                      <CookingPot
                        v-else-if="progressIndex(order.status) === i"
                        :size="12"
                        :stroke-width="2.5"
                      />
                      <span v-else>{{ i + 1 }}</span>
                    </span>
                  </template>
                </div>

                <div class="mt-1.5 flex justify-between">
                  <span
                    v-for="(step, i) in CUSTOMER_STEPS"
                    :key="step.key"
                    class="text-[10px] font-medium"
                    :class="progressIndex(order.status) >= i ? 'm-accent' : 'm-faint'"
                  >
                    {{ step.label }}
                  </span>
                </div>

                <p class="m-muted-card mt-2.5 text-sm font-medium">
                  {{ customerLine(order.status) }}
                </p>
              </div>

              <!-- Items, each with its own kitchen progress -->
              <ul class="m-divide mt-3">
                <li
                  v-for="(item, i) in order.items"
                  :key="i"
                  class="flex items-center gap-2 py-2 text-sm"
                >
                  <span class="m-muted-card min-w-0 flex-1 truncate">
                    <span class="font-semibold">{{ item.quantity }}×</span>
                    {{ item.product_name }}
                  </span>
                  <span
                    v-if="item.status !== 'cancelled'"
                    class="m-elevated m-muted-card shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                  >
                    {{ itemStatusMeta(item.status).label }}
                  </span>
                  <span class="m-muted-card w-20 shrink-0 text-right tabular-nums">
                    {{ formatPrice(item.total_price, currency) }}
                  </span>
                </li>
              </ul>

              <div class="m-border mt-1 flex justify-between border-t pt-2 text-sm font-bold">
                <span>Total</span>
                <span class="tabular-nums">{{ formatPrice(order.total, currency) }}</span>
              </div>
            </template>
          </article>
        </div>
      </div>

      <footer class="m-card-border m-faint border-t px-5 py-3 text-center text-[11px]">
        Updates automatically while your order is being prepared.
      </footer>
    </div>
  </div>
</template>
