# System Flows

Version: 1.0
Last reviewed against code: `feature/project-foundation`

This folder documents **how Restaurant Hub actually behaves end to end** — one
document per actor and per lifecycle. It is the design reference to read before
writing new code.

Each document is written against the current implementation. Where a flow is
designed but **not yet built**, it is marked explicitly so nobody assumes
behaviour that does not exist.

---

## Documents

| # | Document | Covers |
|---|----------|--------|
| 01 | [Customer Flow](01-customer-flow.md) | Scan → menu → cart → order → bill → leave |
| 02 | [Waiter Flow](02-waiter-flow.md) | Orders board, service calls, delivering, settling |
| 03 | [Kitchen Flow](03-kitchen-flow.md) | Kitchen display, per-item cooking |
| 04 | [Owner Flow](04-owner-flow.md) | Register, menu, tables/QR, staff, settings, reports |
| 05 | [Platform Admin Flow](05-platform-admin-flow.md) | Super-admin / multi-tenant governance (**mostly not built**) |
| 06 | [Payment Flow](06-payment-flow.md) | Settling a table (**offline payment only today**) |
| 07 | [Notification Flow](07-notification-flow.md) | How each actor learns something happened (**polling today**) |
| 08 | [Order Lifecycle](08-order-lifecycle.md) | Order + order-item state machine |
| 09 | [Bill Lifecycle](09-bill-lifecycle.md) | Dining session = the bill, from scan to settle |

---

## The one-paragraph version

A guest scans a table's QR code. That opens a **dining session** — one open
session per table, shared by everyone sitting there. Every order placed during
the visit is attached to that session, and together they form **one running
bill**. Orders are never paid individually. When the guest is done they tap
*Request bill*; a staff member takes payment however the restaurant normally
does (cash, terminal), then presses **Settle** in the dashboard. Settling
completes every open order, closes the dining session (which invalidates the
guest's link), and frees the table.

```
Scan QR ──► Open dining session ──► Browse menu ──► Cart ──► Place order
                                                                 │
                                                                 ▼
                                                        Kitchen prepares
                                                                 │
                          (guest may order again, same session)  ▼
                          ◄──────────────────────────────  Waiter serves
                                                                 │
                                                                 ▼
                                             Request bill ──► Pay offline
                                                                 │
                                                                 ▼
                                            Staff "Settle" ──► Table freed
                                                                 │
                                                                 ▼
                                                      Dining session closed
```

---

## Core concepts

**Restaurant** — the tenant. Everything else is scoped to one restaurant, and
every dashboard controller re-derives the caller's restaurant server-side
(`ResolvesRestaurant`), so a token can never read another tenant's data.

**Table** — a physical table with exactly one **QR code** (`table_qr_codes`).
The QR token is permanent; it is not rotated per visit.

**Dining session** — one *visit*. Opened by a scan, closed by settling (or by
the idle job). Holds a `session_token` the guest's browser keeps. This is the
security boundary of the public API: a photographed QR from last week resolves
the table but cannot order, because its session is closed.

**Order** — one basket sent to the kitchen. A visit usually has several.

**Order item** — one line. Items advance independently so the kitchen can cook
and a waiter can deliver dish by dish; the parent order's status is recomputed
from its items.

---

## What is deliberately *not* in the system yet

- No customer accounts (guest ordering only — ADR-001).
- No online/card payment. See [Payment Flow](06-payment-flow.md).
- No push, email or SMS to staff or guests. See [Notification Flow](07-notification-flow.md).
- No WebSockets — staff screens poll. Same document.
- No platform-admin UI or API. See [Platform Admin Flow](05-platform-admin-flow.md).
- No reservations (`TableStatus::Reserved` exists but nothing sets it).
- No split bills, tips, discounts or refunds.
