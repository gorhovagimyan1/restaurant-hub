import i18n from '@/i18n'

const tr = (key) => i18n.global.t(key)

/**
 * Order status metadata shared by the dashboard board:
 * label, badge classes, and the natural "next step" a staff member advances to.
 */
export const ORDER_STATUS = {
  pending: { get label() { return tr('orders.status.pending') }, badge: 'bg-slate-200 text-slate-700', dot: 'bg-slate-500' },
  accepted: { get label() { return tr('orders.status.accepted') }, badge: 'bg-sky-100 text-sky-700', dot: 'bg-sky-500' },
  preparing: { get label() { return tr('orders.status.preparing') }, badge: 'bg-indigo-100 text-indigo-700', dot: 'bg-indigo-500' },
  ready: { get label() { return tr('orders.status.ready') }, badge: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
  served: { get label() { return tr('orders.status.served') }, badge: 'bg-teal-100 text-teal-700', dot: 'bg-teal-500' },
  completed: { get label() { return tr('orders.status.completed') }, badge: 'bg-stone-200 text-stone-600', dot: 'bg-stone-400' },
  cancelled: { get label() { return tr('orders.status.cancelled') }, badge: 'bg-red-100 text-red-600', dot: 'bg-red-400' },
}

// The kitchen workflow. Ends at "served" (food delivered) — payment is handled
// separately at the table level when the customer leaves.
const FLOW = {
  pending: { next: 'accepted', get action() { return tr('orders.flow.accept') } },
  accepted: { next: 'preparing', get action() { return tr('orders.flow.startPreparing') } },
  preparing: { next: 'ready', get action() { return tr('orders.flow.markReady') } },
  ready: { next: 'served', get action() { return tr('orders.flow.markServed') } },
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

/* ---- Customer-facing progress ---- */

/**
 * The four steps a guest is shown. Staff have finer states (accepted vs
 * preparing); to someone waiting for food those are both "being cooked", so
 * they collapse into one step rather than a timeline that barely moves.
 */
export const CUSTOMER_STEPS = [
  { key: 'sent', get label() { return tr('orders.steps.sent') } },
  { key: 'cooking', get label() { return tr('orders.steps.cooking') } },
  { key: 'ready', get label() { return tr('orders.steps.ready') } },
  { key: 'served', get label() { return tr('orders.steps.served') } },
]

const STEP_INDEX = {
  pending: 0,
  accepted: 1,
  preparing: 1,
  ready: 2,
  served: 3,
  completed: 3,
}

/** How far along the timeline an order is, or -1 when cancelled. */
export function progressIndex(status) {
  if (status === 'cancelled') return -1
  return STEP_INDEX[status] ?? 0
}

// What the guest is told, in plain language rather than workflow jargon.
const CUSTOMER_LINES = ['pending', 'accepted', 'preparing', 'ready', 'served', 'completed', 'cancelled']

export function customerLine(status) {
  return tr(`orders.customerLine.${CUSTOMER_LINES.includes(status) ? status : 'pending'}`)
}

/* ---- Per-item status (kitchen cooks & waiter delivers each dish) ---- */

export const ITEM_STATUS = {
  pending: { get label() { return tr('orders.itemStatus.pending') }, badge: 'bg-stone-200 text-stone-600' },
  preparing: { get label() { return tr('orders.itemStatus.preparing') }, badge: 'bg-indigo-100 text-indigo-700' },
  ready: { get label() { return tr('orders.itemStatus.ready') }, badge: 'bg-emerald-100 text-emerald-700' },
  served: { get label() { return tr('orders.itemStatus.served') }, badge: 'bg-teal-100 text-teal-700' },
  cancelled: { get label() { return tr('orders.itemStatus.cancelled') }, badge: 'bg-red-100 text-red-600' },
}

// The single action that moves an item forward, with a button colour.
const ITEM_FLOW = {
  pending: { next: 'preparing', get action() { return tr('orders.itemFlow.start') }, btn: 'bg-stone-800 hover:bg-stone-900' },
  preparing: { next: 'ready', get action() { return tr('orders.itemFlow.ready') }, btn: 'bg-emerald-500 hover:bg-emerald-600' },
  ready: { next: 'served', get action() { return tr('orders.itemFlow.deliver') }, btn: 'bg-sky-500 hover:bg-sky-600' },
}

export function itemStatusMeta(status) {
  return ITEM_STATUS[status] || ITEM_STATUS.pending
}

// Which item stations each staff board shows (waiters run the bar board, and
// pick up ready-to-serve items there).
export const BOARD_STATIONS = {
  kitchen: ['kitchen'],
  bar: ['bar', 'ready'],
}

export function onBoard(item, board) {
  return (BOARD_STATIONS[board] || []).includes(item.prep_station || 'kitchen')
}

export function nextItemStep(status) {
  return ITEM_FLOW[status] || null
}
