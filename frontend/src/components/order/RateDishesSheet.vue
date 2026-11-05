<script setup>
import { ref, reactive, computed } from 'vue'
import { storeToRefs } from 'pinia'
import { X, Check } from 'lucide-vue-next'
import { useOrderTrackingStore } from '@/stores/orderTracking'
import { useDiningStore } from '@/stores/dining'
import { submitReviews } from '@/services/orders'
import StarRating from '@/components/ui/StarRating.vue'

/**
 * A focused "rate your dishes" sheet: every served, not-yet-rated dish with
 * stars and an optional comment, sent together. Opened from the gentle prompt
 * under the header, so rating never means digging through order details.
 */
const emit = defineEmits(['close'])

const tracking = useOrderTrackingStore()
const dining = useDiningStore()
const { orders } = storeToRefs(tracking)

const items = computed(() =>
  orders.value.flatMap((order) => order.items.filter((item) => item.can_review)),
)

const drafts = reactive({}) // order item id -> { rating, comment }
const sending = ref(false)
const error = ref(null)
const done = ref(false)

function draft(id) {
  drafts[id] ??= { rating: 0, comment: '' }
  return drafts[id]
}

async function send() {
  const reviews = items.value
    .filter((item) => draft(item.id).rating > 0)
    .map((item) => ({
      order_item_id: item.id,
      rating: drafts[item.id].rating,
      comment: drafts[item.id].comment.trim() || null,
    }))

  if (!reviews.length) {
    error.value = 'Tap the stars on at least one dish.'
    return
  }

  sending.value = true
  error.value = null
  try {
    await submitReviews(dining.token, dining.sessionToken, reviews)
    await tracking.refresh()
    done.value = true
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not send your rating.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex flex-col justify-end sm:items-center sm:justify-center">
    <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

    <div
      class="m-panel relative flex max-h-[90vh] w-full flex-col overflow-hidden rounded-t-2xl shadow-xl sm:max-w-md sm:rounded-2xl"
    >
      <header class="m-card-border flex items-center justify-between border-b px-5 py-3.5">
        <h2 class="m-heading font-bold">Rate your dishes</h2>
        <button
          class="m-faint rounded-full p-1.5 transition hover:opacity-70"
          aria-label="Close"
          @click="emit('close')"
        >
          <X :size="20" />
        </button>
      </header>

      <!-- Thank you -->
      <div v-if="done" class="px-5 py-10 text-center">
        <div class="m-accent-soft mx-auto grid h-14 w-14 place-items-center rounded-full">
          <Check :size="28" :stroke-width="2.5" />
        </div>
        <p class="m-heading mt-3 text-lg font-bold">Thank you!</p>
        <p class="m-muted-card mt-1 text-sm">Your feedback helps us and other guests.</p>
        <button class="m-btn mt-5 px-6 py-2 text-sm font-semibold" @click="emit('close')">Done</button>
      </div>

      <template v-else>
        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-2">
          <p v-if="!items.length" class="m-faint py-10 text-center text-sm">
            Nothing left to rate right now.
          </p>

          <ul v-else class="m-divide">
            <li v-for="item in items" :key="item.id" class="py-3.5">
              <p class="m-muted-card text-sm">
                <span class="font-semibold">{{ item.quantity }}×</span>
                <span class="m-heading ml-1 font-semibold">{{ item.product_name }}</span>
              </p>
              <StarRating
                v-model="draft(item.id).rating"
                editable
                :size="30"
                class="m-accent mt-1"
              />
              <textarea
                v-if="draft(item.id).rating"
                v-model="draft(item.id).comment"
                rows="2"
                maxlength="500"
                placeholder="Tell us more (optional)"
                class="m-card-border m-panel mt-2 w-full rounded-lg border px-3 py-2 text-sm outline-none"
              />
            </li>
          </ul>
        </div>

        <footer v-if="items.length" class="m-card-border border-t px-5 py-3.5">
          <p v-if="error" class="mb-2 text-sm text-red-500">{{ error }}</p>
          <div class="flex items-center justify-between gap-3">
            <button class="m-faint text-sm font-medium hover:opacity-70" @click="emit('close')">
              Not now
            </button>
            <button
              class="m-btn px-6 py-2 text-sm font-semibold disabled:opacity-60"
              :disabled="sending"
              @click="send"
            >
              {{ sending ? 'Sending…' : 'Send' }}
            </button>
          </div>
        </footer>
      </template>
    </div>
  </div>
</template>
