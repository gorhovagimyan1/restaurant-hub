<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Clock, ArrowRight } from 'lucide-vue-next'
import { useBillingStore } from '@/stores/billing'

/**
 * Countdown shown in the dashboard as a trial nears its end, or once a
 * subscription has been cancelled. Stays hidden for the first week of a trial:
 * an owner who has just signed up does not need chasing yet.
 */
const billing = useBillingStore()
const { subscription, showBanner, bannerUrgent, daysRemaining } = storeToRefs(billing)

const message = computed(() => {
  const s = subscription.value
  if (!s) return ''
  const days = daysRemaining.value
  const unit = `day${days === 1 ? '' : 's'}`

  if (s.status === 'cancelled') {
    return `Your subscription is cancelled — access ends in ${days} ${unit}.`
  }
  return days === 0
    ? 'Your free trial ends today.'
    : `${days} ${unit} left in your free trial.`
})
</script>

<template>
  <RouterLink
    v-if="showBanner"
    :to="{ name: 'checkout' }"
    class="mb-5 flex items-center gap-3 rounded-2xl border px-4 py-3 transition"
    :class="
      bannerUrgent
        ? 'border-amber-200 bg-amber-50 hover:bg-amber-100/70'
        : 'border-hairline bg-surface hover:bg-surface-muted'
    "
  >
    <span
      class="grid h-9 w-9 shrink-0 place-items-center rounded-full"
      :class="bannerUrgent ? 'bg-amber-500/15 text-amber-700' : 'bg-canvas text-ink-500'"
    >
      <Clock :size="17" />
    </span>
    <span
      class="min-w-0 flex-1 text-sm font-medium"
      :class="bannerUrgent ? 'text-amber-900' : 'text-ink-700'"
    >
      {{ message }}
    </span>
    <span
      class="hidden shrink-0 items-center gap-1 text-sm font-semibold sm:flex"
      :class="bannerUrgent ? 'text-amber-800' : 'text-brand-600'"
    >
      Choose a plan <ArrowRight :size="14" />
    </span>
  </RouterLink>
</template>
