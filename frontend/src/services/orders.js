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
 * Place a guest order for a scanned table.
 * payload: { customer_name?, customer_phone?, notes?, items: [{product_id, quantity, notes?}] }
 */
export async function placeOrder(token, payload) {
  const { data } = await http.post(`/public/tables/${token}/orders`, payload)
  return data.data
}

/**
 * Fetch the table's running bill (all unsettled orders this visit).
 */
export async function fetchBill(token) {
  const { data } = await http.get(`/public/tables/${token}/bill`)
  return data.data
}

/**
 * Signal staff that the guest is ready to pay and leave.
 */
export async function requestBill(token) {
  const { data } = await http.post(`/public/tables/${token}/bill/request`)
  return data.data
}

/**
 * Call a waiter over to the table.
 */
export async function callWaiter(token) {
  const { data } = await http.post(`/public/tables/${token}/call-waiter`)
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
