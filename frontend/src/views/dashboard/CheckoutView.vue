<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import {
  UtensilsCrossed,
  Check,
  ArrowLeft,
  ArrowRight,
  LogOut,
  Clock,
  CircleAlert,
  BadgeCheck,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useBillingStore } from '@/stores/billing'
import { startCheckout } from '@/services/billing'
import { formatPrice } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const billing = useBillingStore()
const { subscription, plans, loading, error, forbidden } = storeToRefs(billing)

// Arrived straight from registration: greet them rather than warn them, and
// offer the trial as the way past this screen.
const welcome = computed(() => route.query.welcome === '1' && !!subscription.value?.on_trial)

const interval = ref('yearly')
const submitting = ref(false)
const checkoutError = ref(null)
// Set once payment has been arranged; replaces the plan picker.
const arranged = ref(null)

// One plan today, but the screen renders however many are on sale.
const selectedPlanId = ref(null)
const plan = computed(
  () => plans.value.find((p) => p.id === selectedPlanId.value) || plans.value[0] || null,
)

const price = computed(() => {
  if (!plan.value) return 0
  return interval.value === 'yearly' ? plan.value.yearly_price : plan.value.monthly_price
})

const status = computed(() => {
  const s = subscription.value
  if (!s) return null
  if (s.has_access && s.on_trial) {
    return {
      tone: 'info',
      icon: Clock,
      text: `${s.days_remaining} day${s.days_remaining === 1 ? '' : 's'} left in your free trial.`,
    }
  }
  if (s.has_access && s.status === 'cancelled') {
    return {
      tone: 'info',
      icon: Clock,
      text: `Your subscription is cancelled but stays active for ${s.days_remaining} more day${s.days_remaining === 1 ? '' : 's'}.`,
    }
  }
  if (s.has_access && s.status === 'active') {
    return { tone: 'good', icon: BadgeCheck, text: 'Your subscription is active.' }
  }
  // No plan has ever been paid for, so what ran out was the trial — say so
  // rather than talking about a subscription they never had.
  return {
    tone: 'warn',
    icon: CircleAlert,
    text: s.plan
      ? 'Your subscription has ended. Choose a plan to continue.'
      : 'Your free trial has ended. Choose a plan to get back in.',
  }
})

async function pay() {
  if (!plan.value || submitting.value) return
  submitting.value = true
  checkoutError.value = null
  try {
    const result = await startCheckout(plan.value.id, interval.value)

    // A hosted gateway sends the owner away to pay; the manual one returns
    // instructions to follow. The screen handles either without knowing which.
    if (result.action === 'redirect' && result.redirect_url) {
      window.location.href = result.redirect_url
      return
    }
    arranged.value = result.instructions || 'We have received your request.'
  } catch (err) {
    checkoutError.value =
      err?.response?.data?.message || 'Could not start checkout. Please try again.'
  } finally {
    submitting.value = false
  }
}

async function signOut() {
  await auth.logout()
  router.push({ name: 'login' })
}

onMounted(() => billing.load({ force: true }))
</script>

<template>
  <div class="min-h-screen bg-canvas px-4 py-10">
    <div class="mx-auto w-full max-w-2xl">
      <!-- Brand -->
      <div class="mb-8 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow-[0_6px_16px_-6px_rgba(5,150,105,0.8)]"
          >
            <UtensilsCrossed :size="20" :stroke-width="2.25" />
          </span>
          <span class="font-semibold tracking-tight text-ink-900">Restaurant Hub</span>
        </div>
        <button
          class="flex items-center gap-1.5 text-sm text-ink-500 transition hover:text-ink-800"
          @click="signOut"
        >
          <LogOut :size="15" /> Sign out
        </button>
      </div>

      <p v-if="loading && !plans.length && !forbidden" class="text-sm text-ink-400">
        Loading plans…
      </p>

      <!--
        Staff land here too when a lapsed subscription locks the dashboard, but
        billing is the owner's to settle. Tell them what happened rather than
        showing a pricing page they cannot act on.
      -->
      <section v-else-if="forbidden" class="card p-8 text-center">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-50 text-amber-700">
          <CircleAlert :size="28" />
        </div>
        <h1 class="mt-4 text-xl font-semibold tracking-tight text-ink-900">
          This restaurant's subscription has ended
        </h1>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-ink-600">
          Ask the restaurant owner to renew it — they can do that from
          Settings. Your customer menu and table QR codes keep working in the
          meantime.
        </p>
        <button class="btn-ghost mt-6 rounded-xl px-4 py-2 text-sm font-medium" @click="signOut">
          Sign out
        </button>
      </section>

      <p v-else-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">
        {{ error }}
      </p>

      <template v-else>
        <!-- Payment arranged: the plan picker has done its job -->
        <section v-if="arranged" class="card p-8 text-center">
          <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-600">
            <Check :size="28" :stroke-width="2.5" />
          </div>
          <h1 class="mt-4 text-xl font-semibold tracking-tight text-ink-900">
            Almost there
          </h1>
          <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-ink-600">
            {{ arranged }}
          </p>
          <p class="mt-4 text-xs text-ink-400">
            Your access opens as soon as the payment is confirmed.
          </p>
          <button
            v-if="subscription?.has_access"
            class="btn-ghost mt-6 rounded-xl px-4 py-2 text-sm font-medium"
            @click="router.push({ name: 'dashboard-overview' })"
          >
            Back to the dashboard
          </button>
        </section>

        <template v-else>
          <header class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900">
              {{ welcome ? 'Welcome to Restaurant Hub' : 'Choose your plan' }}
            </h1>
            <p v-if="welcome" class="mt-1 text-sm text-ink-500">
              Here's what it costs. You can start with a free trial and decide later.
            </p>
            <p
              v-if="status"
              class="mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium"
              :class="{
                'bg-brand-50 text-brand-700': status.tone === 'good',
                'bg-sky-50 text-sky-700': status.tone === 'info',
                'bg-amber-50 text-amber-800': status.tone === 'warn',
              }"
            >
              <component :is="status.icon" :size="15" />
              {{ status.text }}
            </p>
          </header>

          <!-- Billing interval -->
          <div class="mb-5 inline-flex rounded-xl border border-hairline bg-surface p-1">
            <button
              v-for="option in ['monthly', 'yearly']"
              :key="option"
              type="button"
              class="rounded-lg px-4 py-1.5 text-sm font-medium capitalize transition"
              :class="
                interval === option
                  ? 'bg-brand-500 text-white shadow-sm'
                  : 'text-ink-600 hover:text-ink-900'
              "
              @click="interval = option"
            >
              {{ option }}
              <span
                v-if="option === 'yearly' && plan?.yearly_saving_percent > 0"
                class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                :class="interval === 'yearly' ? 'bg-white/25' : 'bg-brand-50 text-brand-700'"
              >
                −{{ plan.yearly_saving_percent }}%
              </span>
            </button>
          </div>

          <!-- Plans -->
          <div class="grid gap-4" :class="plans.length > 1 && 'sm:grid-cols-2'">
            <button
              v-for="p in plans"
              :key="p.id"
              type="button"
              class="card card-link p-6 text-left"
              :class="plan?.id === p.id && plans.length > 1 && 'ring-2 ring-brand-500'"
              @click="selectedPlanId = p.id"
            >
              <h2 class="font-semibold text-ink-900">{{ p.name }}</h2>
              <p v-if="p.description" class="mt-0.5 text-sm text-ink-500">{{ p.description }}</p>

              <div class="mt-4 flex items-baseline gap-1.5">
                <span class="text-3xl font-bold tracking-tight text-ink-900">
                  {{ formatPrice(interval === 'yearly' ? p.yearly_price : p.monthly_price, p.currency) }}
                </span>
                <span class="text-sm text-ink-500">/ {{ interval === 'yearly' ? 'year' : 'month' }}</span>
              </div>
              <p v-if="interval === 'yearly'" class="mt-1 text-xs text-ink-400">
                {{ formatPrice(p.yearly_monthly_equivalent, p.currency) }} per month, billed yearly
              </p>

              <ul class="mt-5 space-y-2">
                <li
                  v-for="feature in p.features"
                  :key="feature"
                  class="flex items-start gap-2 text-sm text-ink-600"
                >
                  <Check :size="15" class="mt-0.5 shrink-0 text-brand-500" />
                  {{ feature }}
                </li>
              </ul>
            </button>
          </div>

          <p v-if="checkoutError" class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">
            {{ checkoutError }}
          </p>

          <button
            class="btn-brand mt-6 w-full rounded-xl py-3 text-sm font-semibold disabled:opacity-60"
            :disabled="submitting || !plan"
            @click="pay"
          >
            {{ submitting ? 'Please wait…' : `Continue · ${formatPrice(price, plan?.currency)}` }}
          </button>

          <!-- Only offered while they still have access to go back to. -->
          <button
            v-if="subscription?.has_access"
            class="mt-3 flex w-full items-center justify-center gap-1.5 text-sm font-medium text-ink-500 transition hover:text-ink-800"
            @click="router.push({ name: 'dashboard-overview' })"
          >
            <template v-if="welcome">
              Start my {{ subscription.days_remaining }}-day free trial
              <ArrowRight :size="15" />
            </template>
            <template v-else>
              <ArrowLeft :size="15" /> Back to the dashboard
            </template>
          </button>
        </template>
      </template>
    </div>
  </div>
</template>
