# Customer Flow

The guest is anonymous. No account, no login, no app install — a QR code and a
browser.

---

## Happy path

```
Scan QR
   ↓
Open Menu
   ↓
Add Products
   ↓
Place Order
   ↓
Kitchen Receives Order
   ↓
Preparing
   ↓
Ready
   ↓
Customer Requests Bill
   ↓
Pay
   ↓
Table Closed
```

---

## Step by step

### 1. Scan QR

The QR encodes `https://<app>/t/{token}` where `token` is the row's
`table_qr_codes` token. The token is **permanent per table** — it is not
regenerated per visit.

`TableEntry.vue` handles the landing:

1. `GET /api/public/tables/{token}` → resolves the table, restaurant and
   settings. 404 if the restaurant is not `active`.
2. `POST /api/public/tables/{token}/session` → opens **or joins** the table's
   dining session and returns a `session_token`.
3. Stores the session in `stores/dining.js` (localStorage) and redirects to
   `/r/{slug}`.

Two guests at the same table get the **same** session — the second scan joins
the existing open session under a `lockForUpdate`, so they share one bill.

### 2. Open menu

`/r/{slug}` is guarded by `meta.requiresDining`. Without a stored dining
session the router sends the visitor to `/scan` (`ScanRequired.vue`) — the menu
is not browsable from a bare URL. Data comes from:

- `GET /api/public/restaurants/{slug}/menu` — categories + available products
- `GET /api/public/restaurants/{slug}/status` — open/closed right now, derived
  from `business_hours` + `special_hours` (`App\Support\OpeningHours`)

### 3. Add products

The cart lives entirely client-side (`stores/cart.js`, `CartSheet.vue`) —
quantity, per-item notes, an order-level note. Nothing is persisted server-side
until the order is placed.

### 4. Place order

`POST /api/public/tables/{token}/orders` with `session_token`, `items[]`,
optional `customer_name`, `customer_phone`, `notes`.

The server does not trust the client with anything that matters:

- The session must be **open** and the token must match, else `409` "scan again".
- `settings.allow_guest_orders = false` → `422`.
- Products are re-fetched **scoped to this restaurant**; unknown product → `422`.
- `is_available = false` → `422` "sold out".
- Name and price are **snapshotted** onto the order item, so later menu edits
  never rewrite history.
- Subtotal, `tax_percentage` and `service_charge` are computed server-side.
- Initial status is `accepted` if `settings.auto_accept_orders`, else `pending`.
- The table flips to `occupied`.

The guest gets back an order number, status and total.

### 5–7. Kitchen receives / preparing / ready

The guest does **not** get a live push. Progress is visible when they open the
bill sheet, which shows each order's current status. See
[Notification Flow](07-notification-flow.md).

The guest may place **more orders** in the same session — a second round of
drinks is a second `Order` on the same `dining_session_id`.

### 8. Request bill

`POST /api/public/tables/{token}/bill/request` stamps
`restaurant_tables.bill_requested_at`. Idempotent — pressing it twice does not
move the timestamp, so the waiter's queue keeps the original wait time.

The guest can review the running total any time via
`GET /api/public/tables/{token}/bill` (`BillSheet.vue`): every non-final order
this visit, with items, plus combined subtotal / tax / service charge / total.

**Call a waiter** is the same shape:
`POST /api/public/tables/{token}/call-waiter`, stamping `waiter_called_at`,
and gated by `settings.enable_waiter_call`.

### 9. Pay

Outside the app. See [Payment Flow](06-payment-flow.md).

### 10. Table closed

Staff press **Settle**. Every open order → `completed`, the dining session
closes, the table returns to `available`. The guest's stored `session_token` is
now dead: any further call returns `409` telling them to scan again.

---

## Failure & edge cases

| Situation | What the guest sees |
|---|---|
| Restaurant not `active` | `404` on scan |
| Restaurant closed (business hours) | Menu loads, status shows closed |
| `allow_guest_orders` off | `422` on place order |
| `enable_bill_request` / `enable_waiter_call` off | Button hidden; the endpoint also returns `422` |
| Item went sold-out while browsing | `422` naming the item |
| Table settled while guest still holds the tab | `409` "session ended, scan again" |
| Guest walked away | Idle job closes the session after `DINING_IDLE_TIMEOUT_HOURS` (default 3h, checked every 15 min) |
| Guest reopens the site later | Root `/` redirects into `/r/{slug}` if a session is stored, else `/scan` |

---

## Public API surface

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/public/restaurants/{slug}/menu` | Menu |
| GET | `/api/public/restaurants/{slug}/status` | Open now? |
| GET | `/api/public/tables/{token}` | Resolve scanned table |
| POST | `/api/public/tables/{token}/session` | Open/join dining session |
| POST | `/api/public/tables/{token}/orders` | Place order |
| GET | `/api/public/tables/{token}/bill` | Running bill |
| POST | `/api/public/tables/{token}/bill/request` | Ask for the bill |
| POST | `/api/public/tables/{token}/call-waiter` | Call a waiter |

All are unauthenticated; all mutating ones require a valid `session_token`.

---

## Not built

- Customer accounts, order history, reordering, loyalty (ADR-001 defers to v2).
- Guest-initiated cancellation or editing of a placed order.
- Live status push to the guest's screen.
- Tips, split bills, paying a single order.
