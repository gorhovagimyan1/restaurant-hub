# Payment Flow

> **Status: no online payment.** The system records *that* a bill was settled.
> It does not take money, and it does not record how the money was taken.

---

## How it works today

```
Guest finishes eating
   ↓
Guest taps "Request bill"           → restaurant_tables.bill_requested_at = now()
   ↓
Table surfaces on the staff board (bill-requested tables sort to the top)
   ↓
Staff brings the bill, takes payment OFF-SYSTEM (cash, card terminal)
   ↓
Staff presses "Settle"              → POST /api/dashboard/tables/{table}/settle
   ↓
One transaction:
   • every non-final order on the table → completed (completed_at stamped)
   • the open dining session            → closed
   • bill_requested_at, waiter_called_at → cleared
   • table status                        → available
   ↓
Guest's session_token is now dead; the table is ready for the next guest
```

`settle` requires `orders.update-status`, so owners, managers, waiters and
kitchen staff can all perform it.

---

## What "paid" means in the data model

There is no payment entity. `orders` has no `payment_method`, `paid_at`,
`amount_paid` or transaction reference — only `status` and `completed_at`.

So:

- **`status = completed` is the only record that a bill was paid.**
- Revenue reporting sums `total` over completed orders in the day's window
  (`OverviewController::today`), using `completed_at` in the restaurant's
  timezone.
- An order completed for a non-payment reason (comped meal, staff closing out a
  mistake) is indistinguishable from a paid one.

That is a real limitation, and it is the first thing to fix if payments matter.

---

## Money calculation

All amounts are computed **server-side** when the order is placed
(`Public\OrderController::store`), never trusted from the client:

```
line.total_price = product.price × quantity      (price snapshotted at order time)
subtotal         = Σ line.total_price
tax              = subtotal × settings.tax_percentage / 100
service_charge   = subtotal × settings.service_charge / 100
total            = subtotal + tax + service_charge
```

Each value is rounded to 2 decimals at each step. The order's `total` is frozen
once written — later changes to the menu price or to tax settings do **not**
retroactively alter placed orders.

The **bill** is the sum across every non-final order in the session:

```
bill.subtotal       = Σ order.subtotal
bill.tax            = Σ order.tax
bill.service_charge = Σ order.service_charge
bill.total          = Σ order.total
```

Currency comes from `restaurants.currency` (default `AMD`). There is no
multi-currency handling and no minor-unit integer storage — amounts are
`decimal(10,2)`.

---

## Known gaps

| Gap | Consequence |
|---|---|
| No payment records | Cannot reconcile against a till or terminal; no payment-method breakdown |
| No online/card payment | Guest cannot pay in the app despite "Pay" being in the flow |
| No split bill | One table = one total, always |
| No tips | No way to add or track gratuity |
| No discounts / comps / vouchers | Any adjustment has to happen off-system |
| No refunds or voids after settle | `completed` is terminal; a mistake needs DB intervention |
| Cancelled orders excluded silently | `cancelled` orders drop out of the bill with no trace on the receipt |
| No receipt | Nothing printable or emailable is generated |

---

## If online payment is added

The minimum shape:

1. A `payments` table: `dining_session_id`, `amount`, `currency`, `method`
   (`cash` / `card` / `online`), `provider`, `provider_reference`, `status`,
   `paid_at`.
2. Settle becomes: record a payment → then complete orders and close the
   session. Cash simply records a `cash` payment, keeping one code path.
3. A provider webhook marks the payment captured; the session only closes on a
   confirmed capture, otherwise the guest could close their own tab by
   abandoning a redirect.
4. Reporting switches from "sum of completed orders" to "sum of captured
   payments" — which is what a restaurant actually wants to reconcile.
5. Idempotency keys on capture, since the guest may retry.

Until then, treat **Settle** as a bookkeeping action performed by a human who
has already taken the money.
