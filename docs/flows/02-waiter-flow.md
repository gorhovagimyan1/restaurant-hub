# Waiter Flow

A waiter is a staff account belonging to one restaurant, with exactly two
permissions: `orders.view` and `orders.update-status`.

---

## Flow

```
Log in
   ↓
Orders board (auto-refreshes every 4s)
   ↓
┌──────────────┬───────────────────┬──────────────────┐
│ New order    │ Service call      │ Table wants bill │
│ arrives      │ (waiter called)   │                  │
▼              ▼                   ▼
Watch it cook  Go to table         Take payment offline
   ↓           Acknowledge call         ↓
Item "ready"        ↓                Settle table
   ↓           call cleared             ↓
Deliver → mark Served              Table freed, session closed
```

---

## What a waiter can do

| Action | Endpoint |
|---|---|
| See live orders | `GET /api/dashboard/orders` |
| See order history | `GET /api/dashboard/orders/history` |
| See tables asking for attention | `GET /api/dashboard/service-calls` |
| Advance a whole order | `PATCH /api/dashboard/orders/{order}/status` |
| Advance a single item | `PATCH /api/dashboard/order-items/{item}/status` |
| Clear a waiter call | `POST /api/dashboard/tables/{table}/ack-call` |
| Settle a table | `POST /api/dashboard/tables/{table}/settle` |

A waiter **cannot** touch the menu, tables, staff, settings or reports — those
routes are behind `restaurant.manage`, `products.manage`, `tables.manage`,
`employees.manage`, `settings.manage`, `reports.view`.

---

## Screens

- **`/dashboard/orders` (`OrdersBoard.vue`)** — the main board. Orders needing
  action are cards; a `ServiceCallsBanner` sits on top; open (unpaid) tables are
  grouped for settling, with bill-requested tables sorted first.
- **`/dashboard/orders/history` (`OrdersHistory.vue`)** — paginated, filterable
  by status, searchable by order number / table / customer name.
- **`/kitchen` (`KitchenBoard.vue`)** — also reachable by waiters; useful on a
  pass screen.

The board is driven by `stores/orders.js`, which polls every **4 seconds** and
tracks which order IDs and service calls are new since the last poll so the UI
can highlight and chime them. There is no server push.

---

## Delivering food

Two granularities, both allowed:

- **Per item** — `PATCH /order-items/{id}/status` → `served`. After each item
  update the parent order's status is recomputed by
  `Order::syncStatusFromItems()`.
- **Whole order** — `PATCH /orders/{id}/status` → `served`. This *cascades* to
  every item that is behind that point (`OrderController::cascadeToItems`);
  items already further along are left alone.

See [Order Lifecycle](08-order-lifecycle.md) for the exact state machine.

---

## Service calls

Guests raise two signals, both stored as timestamps on `restaurant_tables`:

| Signal | Column | Cleared by |
|---|---|---|
| Call a waiter | `waiter_called_at` | `POST /tables/{table}/ack-call`, or settling |
| Request the bill | `bill_requested_at` | **Settling only** |

`GET /api/dashboard/service-calls` returns tables with either flag set, oldest
first, with a `since` timestamp so the UI can show the wait.

Note the asymmetry: a waiter call can be acknowledged and dismissed; a bill
request stays on the board until the table is actually settled. That is
deliberate — the request is not "seen", it is "done".

---

## Settling

`POST /api/dashboard/tables/{table}/settle` is one transaction that:

1. completes every non-final order on the table,
2. closes the open dining session (guest's link dies here),
3. clears `bill_requested_at` and `waiter_called_at`,
4. sets the table back to `available`.

Payment itself happens outside the app — see [Payment Flow](06-payment-flow.md).

---

## Tenancy guard

Every dashboard controller resolves the restaurant from the authenticated user
(`ResolvesRestaurant::currentRestaurant`) and calls `guardTenant()` on the bound
model. A waiter cannot settle, acknowledge or advance anything belonging to
another restaurant even with a valid ID.

---

## Not built

- Waiter-to-table assignment (sections). Any waiter sees every order.
- Per-waiter performance stats or tips.
- A dedicated slimmed-down waiter UI — waiters use the dashboard board.
