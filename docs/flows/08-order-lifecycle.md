# Order Lifecycle

Two state machines run in parallel: the **order** and its **items**. Items are
the source of truth; the order's status is largely derived from them.

---

## Order states (`App\Enums\OrderStatus`)

```
                    ┌──────────────────────────────────────┐
                    │                                      │
  [placed]          ▼                                      │
     ├──► pending ──► accepted ──► preparing ──► ready ──► served ──► completed
     │      (auto_accept_orders skips straight to accepted)              ▲
     │                                                                   │
     └───────────────────────── cancelled                    settle table┘
```

| Status | Meaning | Set by |
|---|---|---|
| `pending` | Placed, not yet acknowledged | Guest places order |
| `accepted` | Acknowledged by the restaurant | `settings.auto_accept_orders`, or staff |
| `preparing` | At least one item is being cooked | Derived from items, or staff |
| `ready` | Every item is `ready` (or already `served`) | Derived from items, or staff |
| `served` | Every item delivered | Derived from items, or staff |
| `completed` | Paid & closed out | **Settling the table**, or staff |
| `cancelled` | Voided | Staff |

`completed` and `cancelled` are terminal — `OrderStatus::isFinal()`. A final
order is never recomputed from its items and never appears on the running bill.

---

## Item states (`App\Enums\OrderItemStatus`)

```
pending ──► preparing ──► ready ──► served
   └──────────────────────────────► cancelled
```

Items exist so the kitchen can cook and a waiter can deliver dish by dish
instead of the whole ticket at once.

---

## Derivation: items → order

`Order::syncStatusFromItems()` runs after every single-item update. It ignores
cancelled items and short-circuits if the order is already final:

| Item states | Order becomes |
|---|---|
| all `served` | `served` |
| all `ready` or `served` | `ready` |
| any `preparing` / `ready` / `served` | `preparing` |
| otherwise | `pending` |

The derived status is applied **only if it is a legal forward transition**, so
the derivation obeys the same machine staff do. Cancelling the one active item
on an `accepted` order derives `pending`, which is refused — the order stays
`accepted` instead of resurfacing on the board as new work.

If every item is cancelled, the collection is empty and the order's status is
left untouched — it does **not** auto-cancel.

## Cascade: order → items

`PATCH /api/dashboard/orders/{order}/status` is the "everything at once"
shortcut. `OrderController::cascadeToItems()` pushes items forward, never
backward:

| Order set to | Items moved from | Items moved to |
|---|---|---|
| `preparing` | `pending` | `preparing` |
| `ready` | `pending`, `preparing` | `ready` |
| `served` | `pending`, `preparing`, `ready` | `served` |
| `cancelled` | any non-cancelled | `cancelled` |
| `pending`, `accepted`, `completed` | — | no cascade |

Then, if the new status is final, `releaseTableIfIdle()` sets the table back to
`available` provided no other active order remains on it.

---

## Who can drive it

Both endpoints require `orders.update-status` — owner, manager, waiter, kitchen
staff. Both `guardTenant()` the model against the caller's restaurant.

| Endpoint | Scope |
|---|---|
| `PATCH /api/dashboard/orders/{order}/status` | Whole order (cascades down) |
| `PATCH /api/dashboard/order-items/{orderItem}/status` | One line (syncs up) |

### Transition rules

The form requests validate that the value is a member of the enum; the
controllers then enforce the state machine (`OrderStatus::canTransitionTo()`,
`OrderItemStatus::canTransitionTo()`) **after** the tenancy guard, so a
cross-tenant probe still gets a plain `404` rather than a status-revealing
`422`.

Orders:

| From | May move to |
|---|---|
| `pending` | `accepted`, `preparing`, `ready`, `served`, `completed`, `cancelled` |
| `accepted` | `preparing`, `ready`, `served`, `completed`, `cancelled` |
| `preparing` | `ready`, `served`, `completed`, `cancelled` |
| `ready` | `served`, `completed`, `cancelled` |
| `served` | `completed`, `cancelled` |
| `completed`, `cancelled` | *nothing — terminal* |

Items:

| From | May move to |
|---|---|
| `pending` | `preparing`, `ready`, `served`, `cancelled` |
| `preparing` | `ready`, `served`, `cancelled` |
| `ready` | `served`, `cancelled` |
| `served` | `cancelled` (comping a delivered dish) |
| `cancelled` | *nothing — terminal* |

Three properties hold:

- **Forward only.** Skipping ahead is allowed (that is the "mark everything
  ready" shortcut); stepping back is not.
- **Terminal is terminal.** A `completed` order cannot be reopened — it would
  silently distort the day's takings, which are summed from completed orders.
- **Re-applying the current status is a no-op**, returning `200` without
  re-cascading or re-stamping `completed_at`, so a double-tap on the board is
  harmless.

Anything else returns `422` naming both statuses, e.g.
*"An order that is served cannot be marked pending."*

---

## Timestamps

| Column | Written when |
|---|---|
| `orders.ordered_at` | At creation |
| `orders.completed_at` | First time the order reaches `completed` (settle, or direct status change); never overwritten |
| `orders.created_at` | Row insert — what the live board and history sort by |

Revenue reporting keys off `completed_at`, order listing off `created_at`.

---

## Table status interaction

```
Order placed        → table.status = occupied
Order goes final    → releaseTableIfIdle(): if no other active order, table = available
Table settled       → all open orders completed, table = available
```

`TableStatus::Reserved` and `Disabled` exist in the enum but nothing sets them.

---

## Visibility

| Surface | Includes |
|---|---|
| `GET /api/dashboard/orders` (live board) | All non-final orders **plus** anything created today |
| `GET /api/dashboard/orders/history` | Everything, paginated 20/page, filter by status, search by order number / customer / table |
| `GET /api/public/tables/{token}/bill` | Non-final orders of the current session only |
| Kitchen board | Client-filtered to statuses still needing kitchen action |

---

## Not built

- Guest-side or staff-side edit of a placed order (add item, change quantity).
- Cancellation reasons or an audit trail of who changed what, when.
- Auto-cancel when every item is cancelled.
