import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getSubscription } from '@/services/billing'

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

  function reset() {
    subscription.value = null
    plans.value = []
    loaded.value = false
    error.value = null
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
    reset,
  }
})
