# Kitchen Flow

Kitchen staff have the same two permissions as waiters — `orders.view` and
`orders.update-status` — but a different home screen.

---

## Flow

```
Log in
   ↓
Redirected to /kitchen (kitchen staff never land on the owner dashboard)
   ↓
Kitchen display, polling every 4s
   ↓
New order appears (pending / accepted)
   ↓
Start cooking an item      →  item: preparing
   ↓
Dish done                  →  item: ready
   ↓
(waiter delivers)          →  item: served
   ↓
Order disappears from the board once nothing needs kitchen action
```

---

## Routing

`DashboardLayout.vue` redirects a kitchen-staff user to `/kitchen`, and the
router sends already-authenticated kitchen users there on login. `/profile`
sits outside `DashboardLayout` precisely so kitchen staff can reach it without
tripping that redirect.

`KitchenLayout.vue` is a minimal full-screen chrome intended for a wall-mounted
or pass-side display.

---

## What the board shows

`stores/orders.js` exposes a `kitchen` list — orders whose status still needs
kitchen action (`isKitchen()` in `utils/orderStatus.js`). Completed, cancelled
and fully-served orders drop off.

Each `OrderCard.vue` shows the table, order number, elapsed time, notes and the
line items with their individual statuses.

New arrivals are detected client-side: the store keeps a set of known order IDs
and flags anything unseen on the latest poll, so the board can highlight and
chime. Nothing is pushed from the server — see
[Notification Flow](07-notification-flow.md).

---

## Per-item cooking

The kitchen works item by item, not order by order. That is the point of
`order_items.status`:

```
pending ──► preparing ──► ready ──► served
    └──────────────► cancelled
```

`PATCH /api/dashboard/order-items/{orderItem}/status` updates one line, then
calls `Order::syncStatusFromItems()`, which derives the order's status:

| Items | Order becomes |
|---|---|
| all `served` | `served` |
| all `ready` or `served` | `ready` |
| any `preparing`/`ready`/`served` | `preparing` |
| otherwise | `pending` |

Cancelled items are ignored in that calculation. A `completed` or `cancelled`
order is never recomputed.

There is also a whole-order shortcut (`PATCH /orders/{order}/status`) for
"everything's up" — it cascades down to the items.

---

## Order intake

An order lands as `pending`, or as `accepted` when the restaurant has
`settings.auto_accept_orders` on. Both are visible to the kitchen. Nothing in
the current implementation forces an explicit accept step before cooking —
`accepted` is informational, and moving an item to `preparing` is what really
starts the clock.

---

## Not built

- Prep-time estimates, per-station routing, or course/fire timing.
- Kitchen-initiated "item unavailable" push back to the guest (the kitchen can
  cancel an item, but the guest is not notified).
- Printed tickets.
- Any audible/visual alert that survives the tab being closed.
