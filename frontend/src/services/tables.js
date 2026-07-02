import http from './http'

/**
 * Fetch all tables (with QR tokens) for the current restaurant.
 */
export async function fetchTables() {
  const { data } = await http.get('/dashboard/tables')
  return data.data
}

/**
 * Create a table (a QR code is generated for it server-side).
 */
export async function createTable(payload) {
  const { data } = await http.post('/dashboard/tables', payload)
  return data.data
}

/**
 * Delete a table.
 */
export async function deleteTable(id) {
  await http.delete(`/dashboard/tables/${id}`)
}

/**
 * Settle a table's bill: mark all its orders paid & completed, and free it.
 */
export async function settleTable(id) {
  await http.post(`/dashboard/tables/${id}/settle`)
}

/**
 * Tables currently asking for a waiter or the bill.
 */
export async function fetchServiceCalls() {
  const { data } = await http.get('/dashboard/service-calls')
  return data.data
}

/**
 * Acknowledge (clear) a table's waiter call.
 */
export async function acknowledgeCall(id) {
  await http.post(`/dashboard/tables/${id}/ack-call`)
}
