import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { fetchOrders, updateOrderStatus, updateOrderItemStatus } from '@/services/orders'
import { settleTable, fetchServiceCalls, acknowledgeCall } from '@/services/tables'
import { isKitchen, isOpen } from '@/utils/orderStatus'

const POLL_INTERVAL = 4000

/**
 * Live orders for the staff dashboard. Polls the backend every few seconds and
 * flags newly-arrived orders so the board can highlight / chime them.
 */
export const useOrdersStore = defineStore('orders', () => {
  const orders = ref([])
  const loading = ref(false)
  const error = ref(null)
  const lastUpdated = ref(null)

  const knownIds = ref(new Set())
  const freshIds = ref(new Set()) // orders seen for the first time on the latest poll

  // Tables asking for a waiter / bill.
  const serviceCalls = ref([])
  const knownCallKeys = ref(new Set())
  const hasFreshCall = ref(false) // a new waiter call arrived on the latest poll

  let timer = null

  // Orders still needing kitchen action (shown as cards).
  const kitchen = computed(() => orders.value.filter((order) => isKitchen(order.status)))

  // Tables with an open (unpaid) bill — grouped for the payment view.
  const openTables = computed(() => {
    const groups = new Map()
    for (const order of orders.value) {
      if (!isOpen(order.status)) continue
      const id = order.table?.id
      if (id == null) continue
      if (!groups.has(id)) {
        groups.set(id, {
          id,
          name: order.table?.name || `Table ${id}`,
          billRequested: !!order.table?.bill_requested,
          orders: [],
          total: 0,
        })
      }
      const group = groups.get(id)
      group.orders.push(order)
      group.total += Number(order.total) || 0
      if (order.table?.bill_requested) group.billRequested = true
    }
    // Tables that asked for the bill float to the top.
    return [...groups.values()].sort(
      (a, b) => Number(b.billRequested) - Number(a.billRequested) || a.name.localeCompare(b.name),
    )
  })

  const done = computed(() => orders.value.filter((order) => !isOpen(order.status)))
  const activeCount = computed(() => kitchen.value.length)

  async function refresh({ silent = false } = {}) {
    if (!silent) loading.value = true
    try {
      const data = await fetchOrders()
      const incomingFresh = new Set()
      for (const order of data) {
        if (!knownIds.value.has(order.id)) {
          incomingFresh.add(order.id)
          knownIds.value.add(order.id)
        }
      }
      // Only treat as "fresh" (chime) after the initial load has populated ids.
      freshIds.value = lastUpdated.value ? incomingFresh : new Set()
      orders.value = data

      // Service calls (waiter/bill) polled alongside orders.
      const calls = await fetchServiceCalls().catch(() => null)
      if (calls) {
        let fresh = false
        for (const call of calls) {
          const key = `${call.id}:${call.waiter_called ? 'w' : ''}:${call.since}`
          if (call.waiter_called && !knownCallKeys.value.has(key)) {
            fresh = true
            knownCallKeys.value.add(key)
          }
        }
        hasFreshCall.value = lastUpdated.value ? fresh : false
        serviceCalls.value = calls
      }

      error.value = null
      lastUpdated.value = new Date()
    } catch (err) {
      error.value = err?.response?.data?.message || 'Could not load orders.'
    } finally {
      loading.value = false
    }
  }

  function acknowledge(id) {
    freshIds.value.delete(id)
    freshIds.value = new Set(freshIds.value)
  }

  function clearFresh() {
    freshIds.value = new Set()
  }

  async function advance(order, status) {
    const updated = await updateOrderStatus(order.id, status)
    const index = orders.value.findIndex((o) => o.id === order.id)
    if (index !== -1) orders.value.splice(index, 1, updated)
    return updated
  }

  // Advance a single item; the API returns the refreshed parent order.
  async function advanceItem(order, item, status) {
    const updated = await updateOrderItemStatus(item.id, status)
    const index = orders.value.findIndex((o) => o.id === order.id)
    if (index !== -1) orders.value.splice(index, 1, updated)
    return updated
  }

  async function settle(tableId) {
    await settleTable(tableId)
    await refresh({ silent: true })
  }

  async function ackCall(tableId) {
    await acknowledgeCall(tableId)
    serviceCalls.value = serviceCalls.value.filter((c) => c.id !== tableId || c.bill_requested)
    await refresh({ silent: true })
  }

  function start() {
    stop()
    refresh()
    timer = setInterval(() => refresh({ silent: true }), POLL_INTERVAL)
  }

  function stop() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  return {
    orders,
    loading,
    error,
    lastUpdated,
    freshIds,
    serviceCalls,
    hasFreshCall,
    kitchen,
    openTables,
    done,
    activeCount,
    refresh,
    advance,
    advanceItem,
    settle,
    ackCall,
    acknowledge,
    clearFresh,
    start,
    stop,
  }
})
