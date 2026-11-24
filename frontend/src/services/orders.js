import http from './http'

/**
 * Resolve a scanned QR token into its table + restaurant context.
 * Returns the unwrapped payload: { token, table, restaurant, ordering }.
 */
export async function resolveTable(token) {
  const { data } = await http.get(`/public/tables/${token}`)
  return data.data
}

/**
 * Open (or re-join) the table's dining session for this visit. Returns the
 * session_token that authorizes ordering / bill / service calls until staff
 * settle the bill. Called right after resolveTable on scan.
 */
export async function openSession(token) {
  const { data } = await http.post(`/public/tables/${token}/session`)
  return data.data.session_token
}

/**
 * Place a guest order for a scanned table.
 * payload: { session_token, customer_name?, customer_phone?, notes?, items: [{product_id, quantity, notes?}] }
 */
export async function placeOrder(token, payload) {
  const { data } = await http.post(`/public/tables/${token}/orders`, payload)
  return data.data
}

/**
 * Fetch the table's running bill (all unsettled orders this visit).
 */
export async function fetchBill(token, sessionToken) {
  const { data } = await http.get(`/public/tables/${token}/bill`, {
    params: { session_token: sessionToken },
  })
  return data.data
}

/**
 * Live progress of the guest's own orders this visit. Polled by the tracking
 * screen; the payload's `has_active` says whether anything can still change.
 */
export async function fetchOrderProgress(token, sessionToken) {
  const { data } = await http.get(`/public/tables/${token}/orders`, {
    params: { session_token: sessionToken },
  })
  return data.data
}

/**
 * Dishes from this visit that were served and not rated yet. Still works for a
 * short while after the session has closed. Returns { items: [{ id, product_name, quantity }] }.
 */
export async function fetchPendingReviews(token, sessionToken) {
  const { data } = await http.get(`/public/tables/${token}/reviews/pending`, {
    params: { session_token: sessionToken },
  })
  return data.data
}

/**
 * Rate dishes that were served this visit.
 * reviews: [{ order_item_id, rating (1-5), comment? }]
 */
export async function submitReviews(token, sessionToken, reviews) {
  const { data } = await http.post(`/public/tables/${token}/reviews`, {
    session_token: sessionToken,
    reviews,
  })
  return data
}

/**
 * Signal staff that the guest is ready to pay and leave.
 */
export async function requestBill(token, sessionToken) {
  const { data } = await http.post(`/public/tables/${token}/bill/request`, {
    session_token: sessionToken,
  })
  return data.data
}

/**
 * Call a waiter over to the table.
 */
export async function callWaiter(token, sessionToken) {
  const { data } = await http.post(`/public/tables/${token}/call-waiter`, {
    session_token: sessionToken,
  })
  return data.data
}

/**
 * Fetch the live orders for the current restaurant (staff dashboard).
 */
export async function fetchOrders() {
  const { data } = await http.get('/dashboard/orders')
  return data.data
}

/**
 * Fetch the paginated order history. params: { status?, search?, page? }.
 * Returns { orders, meta }.
 */
export async function fetchOrderHistory(params = {}) {
  const { data } = await http.get('/dashboard/orders/history', { params })
  return data.data
}

/**
 * Advance an order to a new status.
 */
export async function updateOrderStatus(orderId, status) {
  const { data } = await http.patch(`/dashboard/orders/${orderId}/status`, { status })
  return data.data
}

/**
 * Advance a single order item (cook / ready / deliver). Returns the updated
 * parent order (its overall status is recomputed server-side).
 */
export async function updateOrderItemStatus(itemId, status) {
  const { data } = await http.patch(`/dashboard/order-items/${itemId}/status`, { status })
  return data.data
}
