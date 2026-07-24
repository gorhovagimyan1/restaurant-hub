/**
 * Order status metadata shared by the dashboard board:
 * label, badge classes, and the natural "next step" a staff member advances to.
 */
export const ORDER_STATUS = {
  pending: { label: 'New', badge: 'bg-slate-200 text-slate-700', dot: 'bg-slate-500' },
  accepted: { label: 'Accepted', badge: 'bg-sky-100 text-sky-700', dot: 'bg-sky-500' },
  preparing: { label: 'Preparing', badge: 'bg-indigo-100 text-indigo-700', dot: 'bg-indigo-500' },
  ready: { label: 'Ready', badge: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
  served: { label: 'Served', badge: 'bg-teal-100 text-teal-700', dot: 'bg-teal-500' },
  completed: { label: 'Paid', badge: 'bg-stone-200 text-stone-600', dot: 'bg-stone-400' },
  cancelled: { label: 'Cancelled', badge: 'bg-red-100 text-red-600', dot: 'bg-red-400' },
}

// The kitchen workflow. Ends at "served" (food delivered) — payment is handled
// separately at the table level when the customer leaves.
const FLOW = {
  pending: { next: 'accepted', action: 'Accept' },
  accepted: { next: 'preparing', action: 'Start preparing' },
  preparing: { next: 'ready', action: 'Mark ready' },
  ready: { next: 'served', action: 'Mark served' },
}

export function statusMeta(status) {
  return ORDER_STATUS[status] || ORDER_STATUS.pending
}

export function nextStep(status) {
  return FLOW[status] || null
}

// Orders still needing kitchen action (shown as cards on the board).
export const KITCHEN_STATUSES = ['pending', 'accepted', 'preparing', 'ready']

// Unpaid orders that make up a table's open bill (not yet settled/cancelled).
export const OPEN_STATUSES = ['pending', 'accepted', 'preparing', 'ready', 'served']

export function isKitchen(status) {
  return KITCHEN_STATUSES.includes(status)
}

export function isOpen(status) {
  return OPEN_STATUSES.includes(status)
}

/* ---- Per-item status (kitchen cooks & waiter delivers each dish) ---- */

export const ITEM_STATUS = {
  pending: { label: 'Queued', badge: 'bg-stone-200 text-stone-600' },
  preparing: { label: 'Cooking', badge: 'bg-indigo-100 text-indigo-700' },
  ready: { label: 'Ready', badge: 'bg-emerald-100 text-emerald-700' },
  served: { label: 'Delivered', badge: 'bg-teal-100 text-teal-700' },
  cancelled: { label: 'Cancelled', badge: 'bg-red-100 text-red-600' },
}

// The single action that moves an item forward, with a button colour.
const ITEM_FLOW = {
  pending: { next: 'preparing', action: 'Start', btn: 'bg-stone-800 hover:bg-stone-900' },
  preparing: { next: 'ready', action: 'Ready', btn: 'bg-emerald-500 hover:bg-emerald-600' },
  ready: { next: 'served', action: 'Deliver', btn: 'bg-sky-500 hover:bg-sky-600' },
}

export function itemStatusMeta(status) {
  return ITEM_STATUS[status] || ITEM_STATUS.pending
}

export function nextItemStep(status) {
  return ITEM_FLOW[status] || null
}
