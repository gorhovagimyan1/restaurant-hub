# Owner Flow

The restaurant owner is the tenant administrator. Self-registration provisions
the whole tenant in one step.

---

## Flow

```
Register (name + email + restaurant name)
   ↓
Tenant provisioned: user + restaurant + default settings + membership
   ↓
┌──────────────┬─────────────┬────────────┬──────────────┬───────────┐
│ Build menu   │ Create      │ Invite     │ Configure    │ Watch the │
│ (categories, │ tables,     │ staff      │ settings &   │ overview  │
│  products)   │ print QRs   │            │ hours        │           │
└──────────────┴─────────────┴────────────┴──────────────┴───────────┘
   ↓
Open for service — orders arrive on the board
```

---

## 1. Registration

`POST /api/auth/register` runs one transaction:

- creates the `User` and assigns the `restaurant-owner` role,
- creates the `Restaurant` with a unique slug derived from the name, defaults
  `currency = AMD`, `timezone = Asia/Yerevan`, `status = active`,
- creates the default `restaurant_settings` row,
- attaches the owner via `restaurant_user` (`is_active`, `joined_at`),
- returns a Sanctum token.

There is **no approval step** — `RestaurantStatus::Pending` exists in the enum
but self-registration goes straight to `active`. See
[Platform Admin Flow](05-platform-admin-flow.md).

## 2. Menu

`/dashboard/menu` (`MenuManager.vue`), behind `categories.manage` /
`products.manage`:

- Categories: create, rename, reorder, delete.
- Products: name, description, ingredients, price, availability, images
  (`POST /products/{product}/images`, `DELETE /product-images/{id}`).

`is_available = false` is the sold-out switch — the product stays on the menu
API but ordering it returns `422`.

## 3. Tables & QR codes

`/dashboard/tables` (`TablesManager.vue`), behind `tables.manage`:

- `POST /api/dashboard/tables` creates the table **and** its QR row in one step
  (the token auto-generates in `TableQrCode::booted`).
- Each `TableQrCard.vue` renders the printable QR pointing at `/t/{token}`.
- `DELETE /api/dashboard/tables/{table}` removes the table and cascades its QR.

QR tokens are permanent. Deleting and recreating a table is the only way to
rotate one, and that invalidates any printed code.

## 4. Staff

`/dashboard/team` (`EmployeesManager.vue`), behind `employees.manage`:

- Invite: creates the account with a random placeholder password, assigns the
  role, attaches them to the restaurant, and emails a password-set link.
- Assignable roles are limited to `Role::staffAssignable()` — manager, waiter,
  kitchen staff. Owner and super-admin are **not** grantable here.
- Update role / deactivate / remove.

## 5. Settings & hours

| Screen | Permission | Endpoints |
|---|---|---|
| Restaurant profile | `restaurant.manage` | `PUT /dashboard/restaurant` |
| Business hours | `restaurant.manage` | `GET/PUT /dashboard/business-hours` |
| Special hours (holidays) | `restaurant.manage` | `GET/PUT /dashboard/special-hours` |
| Operational settings | `settings.manage` | `GET/PUT /dashboard/settings` |

The operational settings are the levers that change guest behaviour:

| Setting | Effect |
|---|---|
| `allow_guest_orders` | Off → placing an order returns `422` |
| `auto_accept_orders` | New orders start `accepted` instead of `pending` |
| `enable_waiter_call` | Off → call-waiter returns `422` |
| `enable_bill_request` | Off → request-bill button hidden and the endpoint returns `422` (viewing the bill still works) |
| `tax_percentage` | Added to every order server-side |
| `service_charge` | Added to every order server-side |
| `currency`, `default_language` | Display |

Business + special hours feed `App\Support\OpeningHours`, which answers
`GET /api/public/restaurants/{slug}/status` for the guest menu.

## 6. Overview / reporting

`/dashboard` (`DashboardOverview.vue`), behind `reports.view`, from
`GET /api/dashboard/overview`:

- **Today** (in the restaurant's timezone): order count, completed count,
  revenue from completed orders, average order value.
- **Live**: current order load.
- **Tables**: occupancy.
- **Service**: outstanding waiter/bill calls.
- Five most recent orders, plus top products for the day.

Owners also have every operational permission, so they can work the orders
board and settle tables themselves.

---

## Permission matrix

| Permission | Owner | Manager | Waiter | Kitchen |
|---|:--:|:--:|:--:|:--:|
| `restaurant.manage` | ✅ | — | — | — |
| `settings.manage` | ✅ | — | — | — |
| `employees.manage` | ✅ | ✅ | — | — |
| `tables.manage` | ✅ | ✅ | — | — |
| `menus.manage` | ✅ | ✅ | — | — |
| `categories.manage` | ✅ | ✅ | — | — |
| `products.manage` | ✅ | ✅ | — | — |
| `orders.manage` | ✅ | ✅ | — | — |
| `orders.view` | ✅ | ✅ | ✅ | ✅ |
| `orders.update-status` | ✅ | ✅ | ✅ | ✅ |
| `reports.view` | ✅ | ✅ | — | — |

Source of truth: `database/seeders/RolesAndPermissionsSeeder.php`. Super-admin
is granted everything through a `Gate::before` hook instead of permission rows.

Note the manager gap: managers can hire staff and edit the menu but **cannot**
edit the restaurant profile, opening hours, or operational settings.

---

## Not built

- Multiple restaurants per owner (the schema's `restaurant_user` pivot allows
  it; `ResolvesRestaurant` picks one, and there is no tenant switcher UI).
- Menu versioning / scheduled menus (the `menus` table exists but is unused by
  the dashboard flows).
- Exportable reports, date-range analytics, or anything beyond "today".
- Ownership transfer.
