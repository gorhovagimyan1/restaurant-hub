import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { fetchOrderProgress } from '@/services/orders'
import { useDiningStore } from '@/stores/dining'

/** How often to re-check while something can still change. */
const POLL_MS = 10000

/**
 * Live progress of the guest's own orders.
 *
 * This runs on a phone at a dinner table, so the polling is deliberately
 * conservative: it pauses while the tab is hidden, and stops altogether once
 * every order has reached a final state and nothing more can change.
 */
export const useOrderTrackingStore = defineStore('orderTracking', () => {
  const orders = ref([])
  const currency = ref('AMD')
  const hasActive = ref(false)
  const loading = ref(false)
  const error = ref(null)
  const loadedOnce = ref(false)

  let timer = null
  let visibilityBound = false

  const count = computed(() => orders.value.length)

  // The order a waiting guest actually cares about — their latest round.
  const latest = computed(() => orders.value[0] || null)

  /** Orders still in the kitchen, for the top-bar badge. */
  const activeCount = computed(() => orders.value.filter((o) => !o.is_final).length)

  async function refresh() {
    const dining = useDiningStore()
    if (!dining.token || !dining.sessionToken) return

    loading.value = true
    try {
      const data = await fetchOrderProgress(dining.token, dining.sessionToken)
      orders.value = data.orders || []
      currency.value = data.currency || 'AMD'
      hasActive.value = !!data.has_active
      error.value = null
      loadedOnce.value = true

      // Nothing can change again — no reason to keep waking the radio.
      if (!hasActive.value) stopPolling()
    } catch (err) {
      // A 409 means the session ended (staff settled the bill, or the table
      // was auto-closed). Polling on is pointless and would spam the API.
      if (err?.response?.status === 409) {
        stopPolling()
        error.value = 'Your dining session has ended.'
      } else {
        error.value = err?.response?.data?.message || 'Could not check your order.'
      }
    } finally {
      loading.value = false
    }
  }

  function onVisibility() {
    if (document.visibilityState === 'visible') {
      // Catch up immediately on return — the status likely moved while away.
      refresh()
      if (hasActive.value) schedule()
    } else {
      clearTimer()
    }
  }

  function clearTimer() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  function schedule() {
    clearTimer()
    timer = setInterval(refresh, POLL_MS)
  }

  function startPolling() {
    refresh()
    schedule()

    if (!visibilityBound && typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', onVisibility)
      visibilityBound = true
    }
  }

  function stopPolling() {
    clearTimer()
    if (visibilityBound && typeof document !== 'undefined') {
      document.removeEventListener('visibilitychange', onVisibility)
      visibilityBound = false
    }
  }

  /** Called after placing an order, so the new round shows up right away. */
  function trackNew() {
    hasActive.value = true
    startPolling()
  }

  function reset() {
    stopPolling()
    orders.value = []
    hasActive.value = false
    error.value = null
    loadedOnce.value = false
  }

  return {
    orders,
    currency,
    hasActive,
    loading,
    error,
    loadedOnce,
    count,
    latest,
    activeCount,
    refresh,
    startPolling,
    stopPolling,
    trackNew,
    reset,
  }
})
