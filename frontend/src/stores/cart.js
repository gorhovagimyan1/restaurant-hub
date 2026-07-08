import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { placeOrder } from '@/services/orders'

/**
 * The customer's cart for a single scanned table. Lines are keyed by product id
 * with a quantity; the cart is persisted per-token so a page refresh keeps it.
 */
export const useCartStore = defineStore('cart', () => {
  const token = ref(null)
  const sessionToken = ref(null) // dining session that authorizes ordering
  const lines = ref([]) // [{ product, quantity }]

  const currency = ref('AMD')
  const serviceCharge = ref(0)
  const taxPercentage = ref(0)

  const count = computed(() => lines.value.reduce((total, line) => total + line.quantity, 0))
  const subtotal = computed(() =>
    lines.value.reduce((total, line) => total + line.product.price * line.quantity, 0),
  )
  const serviceAmount = computed(() => Math.round((subtotal.value * serviceCharge.value) / 100))
  const taxAmount = computed(() => Math.round((subtotal.value * taxPercentage.value) / 100))
  const total = computed(() => subtotal.value + serviceAmount.value + taxAmount.value)
  const isEmpty = computed(() => lines.value.length === 0)

  const storageKey = (t) => `rh_cart_${t}`

  function setContext(t, { currency: c, ordering, sessionToken: s } = {}) {
    // Switching to a different table starts a fresh cart.
    if (token.value && token.value !== t) {
      lines.value = []
    }
    token.value = t
    if (s !== undefined) sessionToken.value = s
    if (c) currency.value = c
    if (ordering) {
      serviceCharge.value = Number(ordering.service_charge) || 0
      taxPercentage.value = Number(ordering.tax_percentage) || 0
    }
    restore()
  }

  function lineFor(productId) {
    return lines.value.find((line) => line.product.id === productId) || null
  }

  function quantityOf(productId) {
    return lineFor(productId)?.quantity || 0
  }

  function add(product) {
    const line = lineFor(product.id)
    if (line) {
      line.quantity += 1
    } else {
      lines.value.push({ product, quantity: 1 })
    }
    persist()
  }

  function decrement(productId) {
    const line = lineFor(productId)
    if (!line) return
    line.quantity -= 1
    if (line.quantity <= 0) {
      lines.value = lines.value.filter((l) => l.product.id !== productId)
    }
    persist()
  }

  function remove(productId) {
    lines.value = lines.value.filter((l) => l.product.id !== productId)
    persist()
  }

  function clear() {
    lines.value = []
    persist()
  }

  function persist() {
    if (!token.value) return
    try {
      localStorage.setItem(
        storageKey(token.value),
        JSON.stringify(lines.value.map((l) => ({ product: l.product, quantity: l.quantity }))),
      )
    } catch {
      // storage may be unavailable (private mode) — cart just won't persist
    }
  }

  function restore() {
    if (!token.value) return
    try {
      const raw = localStorage.getItem(storageKey(token.value))
      lines.value = raw ? JSON.parse(raw) : []
    } catch {
      lines.value = []
    }
  }

  /**
   * Submit the cart as an order. Returns the created order summary.
   */
  async function submit({ customer_name, notes } = {}) {
    const payload = {
      session_token: sessionToken.value,
      customer_name: customer_name || null,
      notes: notes || null,
      items: lines.value.map((line) => ({
        product_id: line.product.id,
        quantity: line.quantity,
      })),
    }
    const result = await placeOrder(token.value, payload)
    clear()
    return result
  }

  return {
    token,
    lines,
    currency,
    count,
    subtotal,
    serviceAmount,
    taxAmount,
    total,
    isEmpty,
    setContext,
    quantityOf,
    add,
    decrement,
    remove,
    clear,
    submit,
  }
})
