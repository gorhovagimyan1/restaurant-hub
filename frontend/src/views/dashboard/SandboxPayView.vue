<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Lock, CreditCard, TriangleAlert } from 'lucide-vue-next'
import http from '@/services/http'
import { formatPrice } from '@/utils/format'

/**
 * A stand-in for a payment provider's hosted card page.
 *
 * Deliberately looks and behaves like the real thing — same redirect in, same
 * activation out — so the paid flow can be built and demonstrated before a
 * provider account exists. No money moves and any card is accepted, so it is
 * labelled unmistakably and the server refuses it outside local development.
 */
const route = useRoute()
const router = useRouter()

const payment = ref(null)
const loading = ref(true)
const error = ref(null)
const paying = ref(false)

const paymentId = computed(() => route.query.payment)

const form = ref({ number: '4242 4242 4242 4242', month: '12', year: '', cvc: '123', name: '' })

const MONTHS = Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0'))

// Ten years ahead, like a real card form — an expiry in the past is not a
// choice worth offering.
const YEARS = (() => {
  const first = new Date().getFullYear()
  return Array.from({ length: 11 }, (_, i) => String(first + i))
})()

form.value.year = YEARS[0]

/**
 * Digits only, and never more than a CVC actually has.
 *
 * The cleaned value is written straight back to the element as well as to the
 * model: when stripping leaves the value unchanged from what Vue last
 * rendered, it skips the DOM patch and the rejected characters stay on screen.
 */
function onCvcInput(event) {
  const cleaned = event.target.value.replace(/\D/g, '').slice(0, 3)
  event.target.value = cleaned
  form.value.cvc = cleaned
}

async function load() {
  // Reached without a payment in the URL — a refresh, a back-button, or the
  // page opened directly. There is nothing to pay for, so say so plainly
  // rather than asking the API about payment "undefined".
  if (!paymentId.value) {
    error.value = 'There is no payment in progress. Choose a plan to start one.'
    loading.value = false
    return
  }

  try {
    const { data } = await http.get(`/dashboard/sandbox-payments/${paymentId.value}`)
    payment.value = data.data
  } catch (err) {
    error.value =
      err?.response?.status === 404
        ? 'This payment link has expired or was already used. Choose a plan to start again.'
        : err?.response?.data?.message || 'Could not load this payment.'
  } finally {
    loading.value = false
  }
}

async function pay() {
  paying.value = true
  error.value = null
  try {
    await http.post(`/dashboard/sandbox-payments/${paymentId.value}/pay`)
    // Return the way a real gateway would, so the checkout screen shows the
    // same confirmation it will show for Stripe.
    router.push({ name: 'checkout', query: { paid: '1' } })
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not complete the payment.'
    paying.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen bg-canvas px-4 py-10">
    <div class="mx-auto w-full max-w-md">
      <!-- Unmistakable: nobody should mistake this for a real card page. -->
      <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
        <TriangleAlert :size="18" class="mt-0.5 shrink-0 text-amber-700" />
        <p class="text-sm text-amber-900">
          <span class="font-semibold">Test payment page.</span>
          No card is charged and any number is accepted. Set
          <code class="rounded bg-amber-100 px-1 text-[12px]">BILLING_GATEWAY=stripe</code>
          to use the real one.
        </p>
      </div>

      <p v-if="loading" class="text-sm text-ink-400">Loading…</p>

      <!-- No payment to show. Always offer the way back, or this is a dead end. -->
      <div v-else-if="!payment" class="card p-8 text-center">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-canvas text-ink-400">
          <CreditCard :size="22" />
        </div>
        <p class="mt-4 text-sm text-ink-600">{{ error }}</p>
        <button
          class="btn-brand mt-5 rounded-xl px-5 py-2.5 text-sm font-semibold"
          @click="router.push({ name: 'checkout' })"
        >
          Choose a plan
        </button>
      </div>

      <div v-else class="card p-6">
        <div class="flex items-baseline justify-between gap-3 border-b border-hairline pb-4">
          <div>
            <p class="font-semibold text-ink-900">{{ payment.plan }}</p>
            <p class="text-xs capitalize text-ink-400">{{ payment.interval }} subscription</p>
          </div>
          <p class="text-xl font-bold tracking-tight text-ink-900">
            {{ formatPrice(payment.amount, payment.currency) }}
          </p>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="pay">
          <div>
            <label class="block text-xs font-medium text-ink-600">Card number</label>
            <div class="relative mt-1">
              <CreditCard :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
              <input
                v-model="form.number"
                class="field py-2.5 pl-9 font-mono tracking-[0.08em]"
                inputmode="numeric"
                aria-label="Card number"
              />
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
              <label class="block text-xs font-medium text-ink-600">Expiry</label>
              <div class="mt-1 flex gap-2">
                <select
                  v-model="form.month"
                  class="field field-select w-[4.75rem] shrink-0 py-2.5 font-mono"
                  aria-label="Expiry month"
                >
                  <option v-for="m in MONTHS" :key="m" :value="m">{{ m }}</option>
                </select>
                <select
                  v-model="form.year"
                  class="field field-select min-w-0 flex-1 py-2.5 font-mono sm:max-w-[6.5rem]"
                  aria-label="Expiry year"
                >
                  <option v-for="y in YEARS" :key="y" :value="y">{{ y }}</option>
                </select>
              </div>
            </div>
            <div class="w-[5.5rem] shrink-0">
              <label class="block text-xs font-medium text-ink-600">CVC</label>
              <input
                :value="form.cvc"
                class="field mt-1 py-2.5 text-center font-mono tracking-[0.2em]"
                inputmode="numeric"
                maxlength="3"
                placeholder="123"
                aria-label="Card security code"
                @input="onCvcInput"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-ink-600">Name on card</label>
            <input v-model="form.name" class="field mt-1 py-2.5" placeholder="Optional" />
          </div>

          <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

          <button
            type="submit"
            class="btn-brand w-full rounded-xl py-3 text-sm font-semibold disabled:opacity-60"
            :disabled="paying"
          >
            {{ paying ? 'Processing…' : `Pay ${formatPrice(payment.amount, payment.currency)}` }}
          </button>

          <p class="flex items-center justify-center gap-1.5 text-[11px] text-ink-400">
            <Lock :size="12" /> This is a simulated page — nothing is sent anywhere.
          </p>
        </form>
      </div>
    </div>
  </div>
</template>
