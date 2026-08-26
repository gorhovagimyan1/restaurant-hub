import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getSubscription, getPendingPayments } from '@/services/billing'

/**
 * The restaurant's subscription state, shared by the countdown banner, the
 * checkout screen and the billing panel in settings.
 */
export const useBillingStore = defineStore('billing', () => {
  const subscription = ref(null)
  const plans = ref([])
  const trialDays = ref(14)
  const loading = ref(false)
  const error = ref(null)
  const loaded = ref(false)

  const onTrial = computed(() => !!subscription.value?.on_trial)
  const hasAccess = computed(() => subscription.value?.has_access !== false)
  const daysRemaining = computed(() => subscription.value?.days_remaining ?? 0)

  /**
   * Whether to nag. During a trial we stay quiet until the end is in sight —
   * a banner on day one is noise, on day twelve it is a useful reminder.
   */
  const showBanner = computed(() => {
    const s = subscription.value
    if (!s) return false
    if (s.status === 'cancelled') return true
    if (!s.on_trial) return false
    return s.days_remaining <= 7
  })

  /** Turns urgent in the last three days. */
  const bannerUrgent = computed(() => daysRemaining.value <= 3)

  async function load({ force = false } = {}) {
    if (loaded.value && !force) return
    loading.value = true
    error.value = null
    try {
      const data = await getSubscription()
      subscription.value = data.subscription
      plans.value = data.plans || []
      trialDays.value = data.trial_days ?? 14
      loaded.value = true
    } catch (err) {
      // A 402 here would be circular — this endpoint is deliberately ungated.
      error.value = err?.response?.data?.message || 'Could not load your subscription.'
    } finally {
      loading.value = false
    }
  }

  /*
   * Platform-admin side: how many payments are awaiting confirmation.
   *
   * Kept here rather than in the admin layout so the nav badge and the queue
   * screen share one number — confirming a payment has to clear the badge
   * without a page change.
   */
  const pendingCount = ref(0)

  function setPendingCount(value) {
    pendingCount.value = value
  }

  async function refreshPendingCount() {
    try {
      pendingCount.value = (await getPendingPayments()).pending?.length || 0
    } catch {
      // Non-fatal: the badge is a convenience, not the source of truth.
    }
  }

  function reset() {
    subscription.value = null
    plans.value = []
    loaded.value = false
    error.value = null
    pendingCount.value = 0
  }

  return {
    subscription,
    plans,
    trialDays,
    loading,
    error,
    loaded,
    onTrial,
    hasAccess,
    daysRemaining,
    showBanner,
    bannerUrgent,
    load,
    pendingCount,
    setPendingCount,
    refreshPendingCount,
    reset,
  }
})
