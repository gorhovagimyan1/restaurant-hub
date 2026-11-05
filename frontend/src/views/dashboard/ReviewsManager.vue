<script setup>
import { ref, onMounted, watch } from 'vue'
import { Trash2 } from 'lucide-vue-next'
import { getReviews, deleteReview } from '@/services/dashboard'
import StarRating from '@/components/ui/StarRating.vue'

const reviews = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const error = ref(null)
const rating = ref('')
const page = ref(1)

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await getReviews({ rating: rating.value || undefined, page: page.value })
    reviews.value = data.reviews
    meta.value = data.meta
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not load reviews.'
  } finally {
    loading.value = false
  }
}

async function remove(review) {
  if (!confirm(`Delete this review of "${review.product_name}"? Its rating will be removed.`)) return
  try {
    await deleteReview(review.id)
    await load()
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not delete the review.'
  }
}

function when(iso) {
  return iso ? new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : ''
}

watch(rating, () => {
  page.value = 1
  load()
})
onMounted(load)
</script>

<template>
  <div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-[1.375rem] font-semibold tracking-tight text-ink-900 sm:text-2xl">Reviews</h1>
        <p class="text-sm text-ink-500">
          What guests said about the dishes they were served. Top-rated dishes appear in “Best choices” on your menu.
        </p>
      </div>
      <select
        v-model="rating"
        class="field-select rounded-lg border border-hairline-strong py-2 pl-3 text-sm outline-none focus:border-brand-500"
      >
        <option value="">All ratings</option>
        <option v-for="n in [5, 4, 3, 2, 1]" :key="n" :value="n">{{ n }} star{{ n > 1 ? 's' : '' }}</option>
      </select>
    </div>

    <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

    <section class="card divide-y divide-hairline">
      <p v-if="loading && !reviews.length" class="py-10 text-center text-sm text-ink-400">Loading…</p>
      <p v-else-if="!reviews.length" class="py-10 text-center text-sm text-ink-400">No reviews yet.</p>

      <article v-for="review in reviews" :key="review.id" class="flex items-start gap-3 p-4">
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-medium text-ink-800">{{ review.product_name }}</span>
            <StarRating :model-value="review.rating" :size="14" class="text-amber-500" />
          </div>
          <p v-if="review.comment" class="mt-1 whitespace-pre-line text-sm text-ink-700">{{ review.comment }}</p>
          <p class="mt-1 text-xs text-ink-400">
            <span v-if="review.table">{{ review.table }} · </span>{{ when(review.created_at) }}
          </p>
        </div>
        <button
          class="shrink-0 rounded-md p-1.5 text-ink-500 hover:bg-red-100 hover:text-red-500"
          title="Delete review"
          @click="remove(review)"
        >
          <Trash2 :size="15" />
        </button>
      </article>
    </section>

    <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-center gap-3 text-sm">
      <button
        class="rounded-lg border border-hairline-strong px-3 py-1.5 disabled:opacity-40"
        :disabled="page <= 1"
        @click="page--; load()"
      >
        Previous
      </button>
      <span class="text-ink-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button
        class="rounded-lg border border-hairline-strong px-3 py-1.5 disabled:opacity-40"
        :disabled="page >= meta.last_page"
        @click="page++; load()"
      >
        Next
      </button>
    </div>
  </div>
</template>
