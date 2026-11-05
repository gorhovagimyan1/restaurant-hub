<script setup>
import { ref, reactive, onMounted } from 'vue'
import { fetchPendingReviews, submitReviews } from '@/services/orders'
import { loadRatingTicket, clearRatingTicket } from '@/utils/ratingTicket'
import StarRating from '@/components/ui/StarRating.vue'

/**
 * "How were your dishes?" for a guest whose visit has already ended. Uses the
 * rating ticket saved while they were seated. Renders nothing at all unless
 * there is something left to rate — never a dead end, never a nag.
 */
const ticket = ref(null)
const items = ref([])
const drafts = reactive({}) // order item id -> { rating, comment }
const sending = ref(false)
const error = ref(null)
const thanks = ref(false)

onMounted(async () => {
  const saved = loadRatingTicket()
  if (!saved) return

  try {
    const data = await fetchPendingReviews(saved.token, saved.sessionToken)
    if (!data.items.length) return clearRatingTicket()

    ticket.value = saved
    items.value = data.items
    for (const item of data.items) drafts[item.id] = { rating: 0, comment: '' }
  } catch {
    // Too late to rate (or the visit is unknown): forget it quietly.
    clearRatingTicket()
  }
})

function dismiss() {
  clearRatingTicket()
  ticket.value = null
}

async function send() {
  const reviews = items.value
    .filter((item) => drafts[item.id].rating > 0)
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
    await submitReviews(ticket.value.token, ticket.value.sessionToken, reviews)
    clearRatingTicket()
    thanks.value = true
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not send your rating.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div
    v-if="thanks"
    class="mt-8 rounded-2xl bg-brand-50 px-5 py-4 text-sm font-medium text-brand-800"
  >
    Thank you for your feedback!
  </div>

  <section v-else-if="ticket" class="mt-8 rounded-2xl border border-hairline bg-white p-5 text-left shadow-sm">
    <h2 class="font-bold text-ink-900">How were your dishes?</h2>
    <p class="mt-0.5 text-sm text-ink-500">
      Thanks for dining with us{{ ticket.tableName ? ` at ${ticket.tableName}` : '' }}. Rate what you liked — it only takes a moment.
    </p>

    <ul class="mt-4 divide-y divide-hairline">
      <li v-for="item in items" :key="item.id" class="py-3">
        <p class="text-sm font-medium text-ink-800">
          <span class="font-semibold">{{ item.quantity }}×</span> {{ item.product_name }}
        </p>
        <StarRating v-model="drafts[item.id].rating" editable :size="26" class="mt-1 text-amber-500" />
        <textarea
          v-if="drafts[item.id].rating"
          v-model="drafts[item.id].comment"
          rows="2"
          maxlength="500"
          placeholder="Tell us more (optional)"
          class="mt-2 w-full rounded-lg border border-hairline-strong px-3 py-2 text-sm outline-none focus:border-brand-500"
        />
      </li>
    </ul>

    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>

    <div class="mt-4 flex items-center justify-between gap-3">
      <button class="text-sm font-medium text-ink-500 hover:text-ink-700" @click="dismiss">
        No thanks
      </button>
      <button
        class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-60"
        :disabled="sending"
        @click="send"
      >
        {{ sending ? 'Sending…' : 'Send' }}
      </button>
    </div>
  </section>
</template>
