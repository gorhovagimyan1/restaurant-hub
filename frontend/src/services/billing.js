import http from './http'

/**
 * Current billing state plus the plans on sale.
 * Returns { subscription, plans, trial_days }.
 */
export async function getSubscription() {
  const { data } = await http.get('/dashboard/subscription')
  return data.data
}

/**
 * Start paying for a plan.
 *
 * The response says what to do next — `action: 'redirect'` with a URL for a
 * hosted checkout, or `action: 'instructions'` for payment by arrangement —
 * so the UI never needs to know which gateway is configured.
 */
export async function startCheckout(planId, interval) {
  const { data } = await http.post('/dashboard/subscription/checkout', {
    plan_id: planId,
    interval,
  })
  return data.data
}

export async function cancelSubscription() {
  const { data } = await http.post('/dashboard/subscription/cancel')
  return data.data
}

/**
 * Super-admin: the billing work queue.
 * Returns { pending, recently_confirmed }.
 */
export async function getPendingPayments() {
  const { data } = await http.get('/admin/subscription-payments')
  return data.data
}

/** Super-admin: confirm a payment and extend the restaurant's access. */
export async function confirmPayment(paymentId) {
  const { data } = await http.post(`/admin/subscription-payments/${paymentId}/confirm`)
  return data.data
}
