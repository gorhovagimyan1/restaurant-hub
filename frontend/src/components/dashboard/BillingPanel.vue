<script setup>
import { ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import { CreditCard, ArrowRight } from 'lucide-vue-next'
import { useBillingStore } from '@/stores/billing'
import { cancelSubscription } from '@/services/billing'
import { formatPrice } from '@/utils/format'

/**
 * Where an owner sees what they are paying and when it renews — and the only
 * place to cancel. The trial banner only appears near expiry, so without this
 * a paying restaurant would have nowhere to manage its subscription.
 */
const billing = useBillingStore()
const { subscription, loading } = storeToRefs(billing)

const cancelling = ref(false)
const notice = ref(null)
const error = ref(null)
const confirming = ref(false)

const renewLabel = computed(() =>
  subscription.value?.status === 'cancelled' ? 'Access ends' : 'Renews',
)

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

async function confirmCancel() {
  cancelling.value = true
  error.value = null
  try {
    await cancelSubscription()
    await billing.load({ force: true })
    notice.value = 'Subscription cancelled. You keep access until the end of the period you paid for.'
    confirming.value = false
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not cancel the subscription.'
  } finally {
    cancelling.value = false
  }
}

onMounted(() => billing.load())
</script>

<template>
  <section class="card p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-bold text-ink-900">Subscription</h2>
        <p class="mt-1 text-xs text-ink-400">Your plan and billing.</p>
      </div>
      <span
        v-if="subscription"
        class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
        :class="{
          'bg-brand-50 text-brand-700': subscription.status === 'active',
          'bg-sky-50 text-sky-700': subscription.status === 'trialing',
          'bg-amber-50 text-amber-800': ['cancelled', 'past_due'].includes(subscription.status),
          'bg-red-50 text-red-600': subscription.status === 'expired',
        }"
      >
        {{ subscription.status_label }}
      </span>
    </div>

    <p v-if="loading && !subscription" class="mt-4 text-sm text-ink-400">Loading…</p>

    <div v-else-if="subscription" class="mt-4">
      <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div>
          <dt class="eyebrow">Plan</dt>
          <dd class="mt-0.5 text-sm font-medium text-ink-800">
            {{ subscription.plan?.name || 'Free trial' }}
          </dd>
        </div>
        <div v-if="subscription.interval">
          <dt class="eyebrow">Billing</dt>
          <dd class="mt-0.5 text-sm font-medium capitalize text-ink-800">
            {{ subscription.interval }}
            <span v-if="subscription.plan" class="font-normal text-ink-500">
              ·
              {{
                formatPrice(
                  subscription.interval === 'yearly'
                    ? subscription.plan.yearly_price
                    : subscription.plan.monthly_price,
                  subscription.plan.currency,
                )
              }}
            </span>
          </dd>
        </div>
        <div>
          <dt class="eyebrow">{{ renewLabel }}</dt>
          <dd class="mt-0.5 text-sm font-medium text-ink-800">
            {{ formatDate(subscription.access_ends_at) }}
          </dd>
        </div>
      </dl>

      <p v-if="notice" class="mt-4 rounded-xl bg-brand-50 px-3 py-2 text-sm text-brand-700">
        {{ notice }}
      </p>
      <p v-if="error" class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600">
        {{ error }}
      </p>

      <div class="mt-5 flex flex-wrap items-center gap-3">
        <RouterLink
          :to="{ name: 'checkout' }"
          class="btn-brand flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold"
        >
          <CreditCard :size="15" />
          {{ subscription.plan ? 'Change plan' : 'Choose a plan' }}
          <ArrowRight :size="14" />
        </RouterLink>

        <!-- Only offered while something is actually running. -->
        <template v-if="subscription.plan && subscription.status !== 'cancelled'">
          <button
            v-if="!confirming"
            type="button"
            class="text-sm font-medium text-ink-500 transition hover:text-red-600"
            @click="confirming = true"
          >
            Cancel subscription
          </button>
          <span v-else class="flex items-center gap-2 text-sm">
            <span class="text-ink-600">Cancel? You keep access until {{ formatDate(subscription.access_ends_at) }}.</span>
            <button
              type="button"
              class="font-semibold text-red-600 hover:underline disabled:opacity-60"
              :disabled="cancelling"
              @click="confirmCancel"
            >
              {{ cancelling ? 'Cancelling…' : 'Yes, cancel' }}
            </button>
            <button
              type="button"
              class="font-medium text-ink-500 hover:text-ink-800"
              @click="confirming = false"
            >
              Keep it
            </button>
          </span>
        </template>
      </div>
    </div>
  </section>
</template>
