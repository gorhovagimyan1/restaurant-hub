# Bill Lifecycle

There is no `bills` table. **The bill is the dining session** — one visit at one
table, and the sum of every order placed during it.

---

## The rule

> One open dining session per table. Everyone at the table shares it. Every
> order in the session belongs to one running bill. The bill is paid once, when
> the guests leave.

Orders are never paid individually.

---

## Lifecycle

```
        first guest scans the QR
                  │
                  ▼
        ┌──────────────────┐
        │  session OPEN    │◄── second guest scans → joins the SAME session
        │  session_token   │
        └────────┬─────────┘
                 │  orders accumulate (order 1, order 2, …)
                 │  bill = Σ non-final orders
                 │
     ┌───────────┴────────────┐
     │                        │
guest requests bill      3h of no activity
     │                        │
     ▼                        ▼
staff take payment     sessions:close-idle
     │                        │
     ▼                        ▼
  SETTLE                session CLOSED
     │                  (orders untouched — staff
     │                   can still settle later)
     ▼
orders → completed
session → CLOSED
table → available
guest's token → dead (409 on any further call)
```

---

## Opening

`POST /api/public/tables/{token}/session` (`Public\TableController::openSession`)
runs inside a transaction:

```php
$existing = $table->openSession()->lockForUpdate()->first();
if ($existing) { $existing->touchActivity(); return $existing; }
return $table->diningSessions()->create(['status' => Open]);
```

The `lockForUpdate` plus a **unique index on `open_table_lock`** enforce
one-open-session-per-table at the database level. `DiningSession::booted()` sets
`open_table_lock = restaurant_table_id` while open; `close()` nulls it, which
frees the table for the next visit's session.

`session_token` is a UUID generated on create. It is the guest's bearer of
authority for the whole visit.

## Authorization during the visit

Every mutating public call passes through `ResolvesDiningSession::requireOpenSession()`:

1. The table must have an open session — otherwise `409` "scan the QR again".
2. The supplied `session_token` must `hash_equals` the open session's token —
   otherwise `409` "your session has ended".
3. `touchActivity()` stamps `last_activity_at`, keeping the idle job away.

This is why a photographed QR from a previous visit is harmless: it resolves the
table, but its session is closed and its token no longer matches.

## Accumulating

`GET /api/public/tables/{token}/bill` returns every order in the session that is
**not** `completed` or `cancelled`, oldest first, with:

- per order: number, status, total, timestamp, and its line items
- combined `subtotal`, `tax`, `service_charge`, `total`
- `bill_requested` flag and the currency

Because cancelled orders are filtered out, a voided order silently disappears
from the bill rather than showing as a zeroed line.

## Requesting

`POST /api/public/tables/{token}/bill/request` stamps
`restaurant_tables.bill_requested_at`, once — repeat presses do not reset it, so
the staff board keeps showing the true wait. The flag is cleared **only** by
settling.

## Closing

Two ways a session ends:

| Path | Trigger | Effect |
|---|---|---|
| **Settle** | Staff press Settle | Orders → `completed`; session closed; flags cleared; table → `available` |
| **Idle timeout** | `sessions:close-idle`, every 15 min, default 3h idle | Session closed **only**. Orders, table status and flags untouched |

The idle path is a safety net for guests who walked out. It deliberately does
not complete orders — an unpaid bill must stay visible to staff, and marking it
`completed` would silently inflate revenue.

---

## Data model

| Table | Role |
|---|---|
| `dining_sessions` | The bill. `session_token`, `status`, `open_table_lock`, `opened_at`, `last_activity_at`, `closed_at` |
| `orders.dining_session_id` | Links each order to the visit |
| `restaurant_tables.bill_requested_at` | Guest asked to pay |
| `restaurant_tables.waiter_called_at` | Guest asked for a waiter |
| `restaurant_tables.status` | `available` / `occupied` |

The guest side stores the session in `localStorage` via `stores/dining.js`; the
router uses it to gate `/r/{slug}` and to redirect `/` back into an active
visit.

---

## Consequences of this design (know these before changing it)

- **Anyone at the table can order and see the whole bill.** There is no notion
  of "my items" — the session token is shared by whoever scanned.
- **A leaked session token = ordering rights** until the table is settled. The
  mitigations are the short session lifetime and the idle close, not secrecy.
- **Table turnover depends on staff pressing Settle.** If they forget, the next
  guests scanning that table join the *previous* party's open session and bill.
  The idle job is the only automatic backstop, and it is 3 hours by default.
- **Settling is all-or-nothing** — every open order on the table completes
  together.

---

## Not built

- Split bills, per-guest tabs, or moving an order between tables.
- Reopening a settled session (a mistaken settle needs DB intervention).
- Merging tables into one bill.
- Any receipt artifact — the bill exists only as a computed API response.
- A staff-side warning that a table's session has been open unusually long.
