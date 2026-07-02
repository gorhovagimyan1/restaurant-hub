import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const KEY = 'rh_dining'

/**
 * The active "dining session" established by scanning a table QR code.
 * Persisted in sessionStorage so it survives navigation/refresh within the
 * browser session, but is ONLY ever created by scanning a QR (see TableEntry).
 * The customer portal (/r/:slug) is gated on this session existing.
 */
export const useDiningStore = defineStore('dining', () => {
  const slug = ref(null)
  const token = ref(null)
  const tableName = ref(null)
  const ordering = ref(null) // { allow_guest_orders, tax_percentage, service_charge }
  const currency = ref('AMD')

  restore()

  const active = computed(() => !!token.value && !!slug.value)
  const allowOrders = computed(() => !!ordering.value?.allow_guest_orders)

  function isActiveFor(targetSlug) {
    return active.value && slug.value === targetSlug
  }

  function start(context) {
    slug.value = context.restaurant.slug
    token.value = context.token
    tableName.value = context.table?.name || null
    ordering.value = context.ordering || null
    currency.value = context.restaurant.currency || 'AMD'
    persist()
  }

  function end() {
    slug.value = null
    token.value = null
    tableName.value = null
    ordering.value = null
    try {
      sessionStorage.removeItem(KEY)
    } catch {
      // ignore
    }
  }

  function persist() {
    try {
      sessionStorage.setItem(
        KEY,
        JSON.stringify({
          slug: slug.value,
          token: token.value,
          tableName: tableName.value,
          ordering: ordering.value,
          currency: currency.value,
        }),
      )
    } catch {
      // storage unavailable — session just won't survive a refresh
    }
  }

  function restore() {
    try {
      const raw = sessionStorage.getItem(KEY)
      if (!raw) return
      const data = JSON.parse(raw)
      slug.value = data.slug
      token.value = data.token
      tableName.value = data.tableName
      ordering.value = data.ordering
      currency.value = data.currency || 'AMD'
    } catch {
      // ignore malformed session
    }
  }

  return {
    slug,
    token,
    tableName,
    ordering,
    currency,
    active,
    allowOrders,
    isActiveFor,
    start,
    end,
  }
})

/**
 * Read the active dining session's slug straight from storage — used by the
 * router guard, which runs outside a component/Pinia setup context.
 */
export function activeDiningSlug() {
  try {
    return JSON.parse(sessionStorage.getItem(KEY) || 'null')?.slug ?? null
  } catch {
    return null
  }
}
