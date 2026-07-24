# Platform Admin Flow

> **Status: largely NOT BUILT.** The role and its authorization hook exist. The
> endpoints and UI do not. This document is the design target plus an honest
> statement of what is there today.

---

## What exists today

- `Role::SuperAdmin` (`super-admin`) is defined and seeded.
- `AppServiceProvider` registers a `Gate::before` hook granting a super-admin
  **every** ability, so they pass any `can:` middleware without permission rows.
- `RestaurantStatus` has `pending`, `active`, `suspended`, `closed`, and
  `isOperational()` returns true only for `active`. Public endpoints already
  check `restaurant->is_active` and 404 otherwise — so suspension *would* work
  the moment something can set it.
- `Role::staffAssignable()` deliberately excludes `super-admin`, so the role
  cannot be granted through the employee UI. It has to be assigned directly in
  the database or via a seeder.

## What does not exist

- No `/api/admin/*` route group. A super-admin logging in today lands on the
  ordinary owner dashboard, scoped to whichever restaurant they are attached to
  — or, if attached to none, to the first restaurant by id
  (`ResolvesRestaurant::currentRestaurant`). There is no cross-tenant view.
- No way to list, approve, suspend or close restaurants through the API.
- No platform-level metrics, billing, plans or audit log.

---

## Intended flow

```
Platform admin logs in
   ↓
Platform console (cross-tenant)
   ↓
┌───────────────┬─────────────────┬───────────────┬──────────────┐
│ Restaurants   │ Approve pending │ Suspend /     │ Platform     │
│ directory     │ signups         │ reinstate     │ metrics      │
└───────────────┴─────────────────┴───────────────┴──────────────┘
   ↓
Suspended restaurant's public endpoints 404 → guests can no longer scan/order
```

### Restaurant lifecycle (target)

```
pending ──approve──► active ──suspend──► suspended ──reinstate──► active
   │                    │                                            │
   └──reject────────────┴──────────────────close────────────────────►closed
```

Today registration jumps straight to `active`; `pending`, `suspended` and
`closed` are never set by any code path.

### Endpoints to add (proposal)

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/admin/restaurants` | Directory, filter by status |
| GET | `/api/admin/restaurants/{id}` | Detail + owner + usage |
| POST | `/api/admin/restaurants/{id}/approve` | `pending → active` |
| POST | `/api/admin/restaurants/{id}/suspend` | `active → suspended`, `is_active = false` |
| POST | `/api/admin/restaurants/{id}/reinstate` | back to `active` |
| GET | `/api/admin/metrics` | Tenants, orders, revenue across the platform |
| GET | `/api/admin/users` | Cross-tenant user lookup for support |

These must sit behind `auth:sanctum` **plus** an explicit super-admin check —
not behind an ordinary permission, and not inside the `dashboard` prefix, since
every controller there resolves and scopes to a single restaurant.

---

## Decisions to make before building this

1. **Does self-registration stay open?** If approval becomes required, change
   `AuthController::register` to create `pending` restaurants — and the owner
   dashboard needs a "awaiting approval" state.
2. **Should suspension be visible to guests?** Right now a suspended restaurant
   would 404 on scan, which reads as "broken QR". A dedicated 403 with a message
   would be kinder.
3. **What does a super-admin see when they are not attached to any restaurant?**
   `ResolvesRestaurant` currently falls back to *the first restaurant by id* —
   an arbitrary tenant. That is a stopgap, not a design; a real console needs an
   explicit tenant selector.
4. **Audit trail.** Cross-tenant actions (suspend, impersonate, edit) should be
   logged; nothing logs today.

---

## Security note

Because `Gate::before` grants super-admin every ability, a super-admin account
that is attached to a restaurant can already do everything inside that tenant.
Treat the role as break-glass: assign it sparingly, never through the app UI.
