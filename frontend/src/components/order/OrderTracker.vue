<script setup>
import { useI18n } from 'vue-i18n'
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
import { X, RefreshCw, CookingPot, Check, XCircle } from 'lucide-vue-next'
import { useOrderTrackingStore } from '@/stores/orderTracking'
import { formatPrice } from '@/utils/format'
import { submitReviews } from '@/services/orders'
import { useDiningStore } from '@/stores/dining'
import StarRating from '@/components/ui/StarRating.vue'
import { CUSTOMER_STEPS, progressIndex, customerLine, itemStatusMeta } from '@/utils/orderStatus'

defineProps({
  tableName: { type: String, default: '' },
})

const emit = defineEmits(['close'])
const { t } = useI18n()

const tracking = useOrderTrackingStore()
const dining = useDiningStore()
const { orders, currency, loading, error, loadedOnce } = storeToRefs(tracking)

// Rating drafts, keyed by order item id: { rating, comment, sending, error }.
const drafts = reactive({})
const rating = ref(null) // id of the item whose rating form is open

function openRating(item) {
  drafts[item.id] ??= { rating: 0, comment: '', sending: false, error: null }
  rating.value = rating.value === item.id ? null : item.id
}

async function sendRating(item) {
  const draft = drafts[item.id]
  if (!draft.rating) {
    draft.error = t('orders.tapStar')
    return
  }
  draft.sending = true
  draft.error = null
  try {
    await submitReviews(dining.token, dining.sessionToken, [
      { order_item_id: item.id, rating: draft.rating, comment: draft.comment.trim() || null },
    ])
    rating.value = null
    await tracking.refresh()
  } catch (err) {
    draft.error = err?.response?.data?.message || t('orders.ratingError')
  } finally {
    draft.sending = false
  }
}

function timeAgo(iso) {
  if (!iso) return ''
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (mins < 1) return t('orders.justNow')
  if (mins < 60) return t('orders.minAgo', { n: mins })
  return t('orders.hAgo', { n: Math.round(mins / 60) })
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
          <h2 class="m-heading font-bold">{{ t('orders.yourOrder') }}</h2>
          <p v-if="tableName" class="m-muted-card text-xs">{{ tableName }}</p>
        </div>
        <div class="flex items-center gap-1">
          <button
            class="m-faint rounded-full p-1.5 transition hover:opacity-70 disabled:opacity-40"
            :disabled="loading"
            :aria-label="t('orders.refreshAria')"
            @click="tracking.refresh()"
          >
            <RefreshCw :size="16" :class="loading && 'animate-spin'" />
          </button>
          <button
            class="m-faint rounded-full p-1.5 transition hover:opacity-70"
            :aria-label="t('common.close')"
            @click="emit('close')"
          >
            <X :size="20" />
          </button>
        </div>
      </header>

      <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
        <p v-if="!loadedOnce && loading" class="m-faint py-10 text-center text-sm">{{ t('orders.checking') }}</p>
        <p v-else-if="error" class="py-6 text-center text-sm text-red-500">{{ error }}</p>
        <p v-else-if="!orders.length" class="m-faint py-10 text-center text-sm">
          {{ t('orders.nothingOrdered') }}
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
                    class="min-w-0 break-words text-center text-[10px] font-medium leading-tight"
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
                <li v-for="(item, i) in order.items" :key="i" class="py-2 text-sm">
                  <div class="flex items-center gap-2">
                    <span class="m-muted-card min-w-0 flex-1 truncate">
                      <span class="font-semibold">{{ item.quantity }}×</span>
                      {{ item.product_name }}
                    </span>
                    <span
                      v-if="item.status !== 'cancelled'"
                      class="m-elevated m-muted-card max-w-[40%] shrink-0 truncate rounded-full px-2 py-0.5 text-[10px] font-semibold"
                    >
                      {{ itemStatusMeta(item.status).label }}
                    </span>
                    <span class="m-muted-card w-20 shrink-0 text-right tabular-nums">
                      {{ formatPrice(item.total_price, currency) }}
                    </span>
                  </div>

                  <!-- Rate a dish once it has been served -->
                  <div v-if="item.can_review" class="mt-1.5">
                    <button
                      class="m-accent text-xs font-semibold underline-offset-2 hover:underline"
                      @click="openRating(item)"
                    >
                      {{ rating === item.id ? t('orders.cancel') : t('orders.rateDish') }}
                    </button>

                    <div v-if="rating === item.id" class="m-elevated mt-2 rounded-xl p-3">
                      <StarRating v-model="drafts[item.id].rating" editable :size="26" class="m-accent" />
                      <textarea
                        v-model="drafts[item.id].comment"
                        rows="2"
                        maxlength="500"
                        :placeholder="t('orders.commentPlaceholder')"
                        class="m-card-border m-panel mt-2 w-full rounded-lg border px-3 py-2 text-sm outline-none"
                      />
                      <p v-if="drafts[item.id].error" class="mt-1 text-xs text-red-500">
                        {{ drafts[item.id].error }}
                      </p>
                      <button
                        class="m-btn mt-2 px-4 py-1.5 text-sm font-semibold disabled:opacity-60"
                        :disabled="drafts[item.id].sending"
                        @click="sendRating(item)"
                      >
                        {{ drafts[item.id].sending ? t('orders.sending') : t('orders.send') }}
                      </button>
                    </div>
                  </div>
                  <p v-else-if="item.rating" class="m-accent mt-1 flex items-center gap-1 text-xs">
                    <StarRating :model-value="item.rating" :size="12" /> {{ t('orders.thanksRating') }}
                  </p>
                </li>
              </ul>

              <div class="m-border mt-1 flex justify-between border-t pt-2 text-sm font-bold">
                <span>{{ t('orders.total') }}</span>
                <span class="tabular-nums">{{ formatPrice(order.total, currency) }}</span>
              </div>
            </template>
          </article>
        </div>
      </div>

      <footer class="m-card-border m-faint border-t px-5 py-3 text-center text-[11px]">
        {{ t('orders.autoUpdates') }}
      </footer>
    </div>
  </div>
</template>
