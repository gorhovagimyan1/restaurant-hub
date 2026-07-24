# Notification Flow

> **Status: there is no notification system.** There are no push
> notifications, no SMS, and exactly two transactional emails. Everything
> "live" is HTTP polling from staff screens.

This document describes what actually happens, so nobody builds on the
assumption that a broadcast layer exists.

---

## The one real notification channel: email

| Trigger | Notification | Recipient |
|---|---|---|
| `POST /api/auth/forgot-password` | `ResetPasswordNotification` | The user |
| Owner invites an employee | Laravel password-reset link ("set your password") | The new employee |

Both go through Laravel's mailer. Nothing else in the system sends mail.

---

## How staff actually learn about things: polling

`frontend/src/stores/orders.js` runs a `setInterval` every **4 seconds** while
a staff screen is mounted:

- `GET /api/dashboard/orders` — live board
- `GET /api/dashboard/service-calls` — tables wanting a waiter or the bill

The store keeps two sets — `knownIds` for orders and `knownCallKeys` for service
calls — and diffs each poll. Anything unseen is flagged `fresh`, which is what
lets the board highlight a new card and play a chime. **The "new order" alert is
computed client-side**; the server never announces anything.

Consequences worth stating plainly:

- Nothing reaches staff when the browser tab is closed. Close the kitchen
  display and orders pile up silently.
- Worst-case latency is one poll interval (~4s), plus request time.
- Every open staff screen polls independently — N screens × 2 requests per 4s.
- A missed poll (sleep, network blip) is self-healing: the next poll returns the
  full current state, not a delta.

---

## How the guest learns about things: they look

The guest gets **no** push at all. Order progress is visible only when they open
the bill sheet, which re-fetches `GET /api/public/tables/{token}/bill` and shows
each order's status. There is no polling loop on the customer side.

So the guest is not told when:

- their order is accepted, ready, or served,
- an item they ordered was cancelled by the kitchen,
- the restaurant stopped accepting orders,
- their session was closed (they find out via a `409` on the next action).

---

## Signal map — who needs to know what

| Event | Who should know | How they find out today |
|---|---|---|
| Order placed | Kitchen, waiters | 4s poll → new card + client-side chime |
| Item ready | Waiter | 4s poll |
| Order served | Guest | Not told; sees the plate |
| Guest calls waiter | Waiters | 4s poll → `ServiceCallsBanner` |
| Guest requests bill | Waiters | 4s poll → table sorts to top of open tables |
| Table settled | Guest | Not told; next request returns `409` |
| Session auto-closed (idle) | Guest, staff | Nobody is told |
| Employee invited | Employee | Email |
| Password reset | User | Email |

The three "not told" rows are the notable holes.

---

## If real-time is added

Laravel already ships the pieces (`broadcasting`, queue tables exist from
`0001_01_01_000002_create_jobs_table`). A sane first cut:

1. **Channels** — private per restaurant (`restaurant.{id}`) for staff, and per
   dining session (`session.{token}`) for the guest. The session token is
   already the guest's bearer of authority, so it authorizes the channel too.
2. **Events** — `OrderPlaced`, `OrderStatusChanged`, `OrderItemStatusChanged`,
   `ServiceCallRaised`, `TableSettled`, `SessionClosed`.
3. **Keep polling as the fallback**, at a longer interval. The current polling
   design is resilient precisely because each response is full state; do not
   throw that away for deltas.
4. **Guest-side subscription** so "Ready" and "your table was closed" arrive
   without the guest refreshing.
5. Only then consider Web Push / SMS — those need permission prompts and a
   device registry, which is a much larger surface.

---

## Scheduled background work

One scheduled command exists, and it is silent:

| Command | Schedule | Effect |
|---|---|---|
| `sessions:close-idle` | every 15 minutes | Closes dining sessions with no activity for `DINING_IDLE_TIMEOUT_HOURS` (default 3h) |

It closes the session only — orders and table occupancy are untouched, so staff
can still settle an unpaid bill. No one is notified that it ran.
