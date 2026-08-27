# Restaurant Hub

[![CI](https://github.com/gorhovagimyan1/restaurant-hub/actions/workflows/ci.yml/badge.svg)](https://github.com/gorhovagimyan1/restaurant-hub/actions/workflows/ci.yml)

Restaurant Hub is a multi-tenant SaaS platform that enables restaurants to accept customer orders through QR codes.

Customers scan a QR code at their table, browse the menu, place orders, and track their order status in real time.

Restaurants manage menus, products, tables, employees, and orders through a web dashboard, and design how their own customer-facing menu looks.

## Features

**For guests** — scan a table QR to open a dining session, browse a themed
menu, order, follow the food through a live status timeline, call a waiter and
request the bill. No app or account needed.

**For restaurants** — menu and category management with images and ingredients,
tables with printable QR codes, staff accounts with role-based permissions,
a live orders board, a dedicated kitchen display, opening hours with holiday
overrides, and a Menu Design screen for styling the customer menu (presets,
colours, fonts, corner radius, list/grid layout, cover photo and logo).

**For the platform** — a super-admin area covering every restaurant and user
on the platform, an editable role/permission matrix, and subscription billing:
restaurants get a free trial on sign-up, then pay monthly or yearly to keep
dashboard access. Guests are never locked out — a billing lapse must not stop
service at the table.

## Tech Stack

### Backend
- Laravel 12
- PHP 8.2+
- MySQL
- Sanctum (API tokens) & spatie/laravel-permission (roles)

### Frontend
- Vue 3 + Vite
- Pinia
- Tailwind CSS 4
- Lucide icons

## Running locally

Requires PHP 8.2+, Composer, Node 22+ and MySQL.

```bash
# Backend — API on http://localhost:8000
cd backend
composer install
cp .env.example .env && php artisan key:generate
# point DB_* at your MySQL database, then:
php artisan migrate --seed
php artisan storage:link          # serves uploaded logos, covers & dish photos
php artisan serve

# Frontend (in a second terminal) — app on http://localhost:5173,
# proxying /api to :8000
cd frontend
npm install
npm run dev
```

Seeding creates two demo restaurants with menus, tables and staff. Every demo
account uses the password `password` — for example `owner@thegoldenfork.test`
(owner), `kitchen@thegoldenfork.test` (kitchen), and
`admin@restaurant-hub.test` (super-admin).

To reach the customer portal you need a dining session, which only a scanned
table QR creates: open **Tables & QR** in the dashboard and follow a table's
QR link. Signed-in staff can also open `/r/<slug>` directly as a read-only
preview.

### Billing

Restaurants get a free trial on sign-up (`BILLING_TRIAL_DAYS`, default 14),
then pay monthly or yearly. `BILLING_GATEWAY` decides how:

- **`manual`** (default) — the owner is shown payment instructions and a
  super-admin confirms the transfer under **Admin → Payments**. Set
  `BILLING_MANUAL_INSTRUCTIONS` to your bank details.
- **`stripe`** — choosing a plan redirects to Stripe's hosted card page. Set
  `STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET`, and register a webhook at
  `POST /api/webhooks/stripe` for `checkout.session.completed` and
  `checkout.session.expired`.

Payment is recognised by webhook, not by the browser returning — someone who
pays and closes the tab still gets their subscription, and nobody gets in by
visiting the success URL by hand.

To try Stripe locally with [test cards](https://docs.stripe.com/testing)
(`4242 4242 4242 4242`):

```bash
stripe listen --forward-to localhost:8000/api/webhooks/stripe
# paste the printed whsec_… into STRIPE_WEBHOOK_SECRET
```

Adding another provider means implementing `App\Services\Billing\PaymentGateway`
and registering it in `AppServiceProvider::GATEWAYS` — no caller names a
gateway directly.

### Tests

```bash
cd backend
php artisan test
```

`phpunit.xml` is configured for SQLite in memory. If your PHP lacks
`pdo_sqlite`, point the suite at a throwaway MySQL database instead —
`RefreshDatabase` migrates it fresh on every run:

```bash
DB_CONNECTION=mysql DB_DATABASE=restaurant_hub_test php artisan test
```

Frontend checks:

```bash
cd frontend
npm run lint:ci   # report-only; `npm run lint` auto-fixes instead
npm run build
```

CI runs all of the above on every push to `main` and on every pull request —
the backend suite against MySQL on PHP 8.2 and 8.3, plus frontend lint and
build. See [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

## Documentation

System design lives in [`docs/`](docs/). Start with
[`docs/flows/`](docs/flows/00-index.md) — one document per actor (customer,
waiter, kitchen, owner, platform admin) and per lifecycle (order, bill, payment,
notification), written against the current implementation.

## Status

🟢 **Core flows complete — not yet deployable.** Every actor's main journey
works end to end, covered by 167 backend feature tests. Still missing:
deployment tooling (no Docker or CI), sales reporting beyond today's figures,
online payments, and notifications of any kind.

See [`docs/08-task-board.md`](docs/08-task-board.md) for the sprint-by-sprint
breakdown.