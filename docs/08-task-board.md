# Task Board

Project Status: 🟢 Core flows complete — not yet deployable

Every actor's main journey works end to end: a guest scans a table QR, orders,
tracks the food, and asks for the bill; the kitchen and waiters work the order;
owners run their menu, team, tables and design; super-admins manage the
platform. 167 backend feature tests cover it.

The gaps that matter most, in rough order:

1. **Deployment** (Sprint 15) — CI now runs the suite on every push, but there
   is still no Docker image, no deploy pipeline and no frontend tests.
2. **Reports** (Sprint 13) — one today-only overview; no sales history.
3. **Payments** — pay-at-table only; no online payment of any kind.
4. **Notifications** — no email, SMS or push anywhere in the product.

---

# Sprint 1 - Project Initialization ✅

## Repository & Documentation

* [x] Create repository
* [x] Create project structure
* [x] Create project documentation
* [x] Define project overview
* [x] Define business requirements
* [x] Define user roles
* [x] Design database architecture
* [x] Define system architecture
* [x] Define API architecture
* [x] Create development roadmap
* [x] Create task board

## Backend Setup

* [x] Install Laravel 12
* [x] Configure environment
* [x] Configure database connection
* [x] Install Laravel Sanctum
* [x] Install Spatie Permission

## Frontend Setup

* [x] Install Vue 3
* 
* [x] Configure Vite
* [x] Install Tailwind CSS
* [x] Install PrimeVue

---

# Sprint 2 - Foundation ✅

## Backend

* [x] Configure Authentication
* [x] Configure Role & Permission System
* [x] Configure API Responses
* [x] Configure Exception Handling
* [x] Configure Request Validation
* [x] Create Base Project Structure

## Database

* [x] Restaurants
* [x] Users
* [x] Restaurant Users
* [x] Restaurant Settings
* [x] Restaurant Tables
* [x] Table QR Codes
* [x] Menus
* [x] Categories
* [x] Products
* [x] Product Images
* [x] Orders
* [x] Order Items

## Models

* [x] Create Models
* [x] Create Relationships
* [x] Create Factories
* [x] Create Seeders
* [x] Create Enums

---

# Sprint 3 - UI Foundation 🟡

## Layouts

* [ ] Authentication Layout (shared AuthShell covers login & register; forgot/reset still roll their own)
* [x] Customer Layout
* [x] Restaurant Dashboard Layout
* [x] Kitchen Layout
* [x] Platform Admin Layout

## Frontend

* [x] Configure Vue Router
* [x] Configure Pinia
* [x] Configure Axios
* [x] Configure Route Guards
* [ ] Configure Permission Guards (enforced on backend; frontend guard is auth-only)
* [x] Configure Theme
* [x] Configure Navigation
* [x] Configure Sidebar
* [x] Configure Header
* [x] Configure Footer

---

# Sprint 4 - Authentication Module ✅

* [x] Login
* [x] Logout
* [x] Forgot Password
* [x] Reset Password
* [x] Change Password
* [x] User Profile
* [x] Current User API
* [x] Role Based Redirect


---

# Sprint 5 - Platform Administration 🟡

Super-admin area at `/admin` (role-gated). Phase 1 shipped; later phases pending.

* [x] Platform Dashboard (platform-wide overview: restaurants, users, orders, revenue)
* [x] Restaurant Management (list/search/filter all restaurants, change status, soft-delete)
* [x] User Management (list/search/filter, activate/deactivate, reassign roles)
* [x] Role Management (edit role→permission matrix; super-admin locked)
* [x] Permission Management (assign permissions to roles across all areas)
* [ ] Platform Settings (Phase 3)
* [ ] Activity Logs (Phase 3)
* [ ] System Monitoring (Phase 4)

---

# Sprint 6 - Restaurant Management 🟡

* [x] Restaurant Dashboard
* [x] Owner self-registration (provisions restaurant + settings on sign-up)
* [ ] Restaurant CRUD (platform-admin: manage all restaurants — Sprint 5)
* [x] Restaurant Profile
* [x] Logo & Cover Photo (upload/replace/remove from Menu Design)
* [x] Restaurant Settings
* [x] Business Hours
* [x] Employee Management
* [x] Invite Employees (email set-password link)
* [x] Assign Roles

---

# Sprint 7 - Table Management 🟡

* [x] Restaurant Tables (create / list / delete; no edit endpoint)
* [x] QR Code Generator (token on create; QR rendered client-side)
* [x] Download QR (download + print from TableQrCard)
* [ ] Regenerate QR
* [x] Table Capacity
* [x] Table Status

---

# Sprint 8 - Menu Management 🟡

* [ ] Menus (single menu auto-managed; no dedicated screen yet)
* [x] Categories
* [x] Products
* [x] Product Images
* [x] Product Availability
* [ ] Product Modifiers
* [x] Ingredients
* [ ] Allergens
* [ ] Product Search
* [x] Menu Design (per-restaurant theme: presets, colours, fonts, radius,
  list/grid layout, header style — stored on restaurant_settings and applied
  to the customer portal via CSS variables)

---

# Sprint 9 - Customer Portal 🟡

* [x] Restaurant Landing Page
* [x] Scan QR (token → dining session)
* [x] Browse Menu
* [x] Browse Categories
* [x] Product Details
* [x] Ingredients
* [ ] Allergens
* [x] Shopping Cart (per-table, persisted)
* [x] Checkout
* [x] Place Order
* [x] Order Confirmation
* [x] Live Order Status (four-step timeline + per-item progress; polls while
  the tab is visible and stops once nothing can change)
* [x] Call Waiter
* [x] Request Bill

---

# Sprint 10 - Waiter Dashboard ✅

Waiter functions are merged into the shared Orders board (no separate view),
gated by permissions.

* [x] Waiter Dashboard (via OrdersBoard)
* [x] Active Tables (open tables / running bills)
* [x] Active Orders
* [x] Customer Requests (service-calls banner)
* [x] Update Order Status
* [x] Bill Requests

---

# Sprint 11 - Kitchen Dashboard ✅

Dedicated `/kitchen` route + KitchenLayout with live polling and per-item advance.

* [x] Kitchen Dashboard
* [x] Pending Orders
* [x] Accepted Orders
* [x] Preparing Orders
* [x] Ready Orders
* [x] Completed Orders (Served / Completed states)
* [x] Product Availability (toggled from menu management)

---

# Sprint 12 - Order Management 🟡

* [x] Order List
* [x] Order Details
* [ ] Order Timeline (statuses + completed_at tracked; no per-status audit log)
* [x] Order Status Workflow (OrderStatus state machine)
* [x] Order History
* [x] Cancel Orders

---

# Sprint 13 - Reports 🟡

* [x] Dashboard Overview (today-only totals, live counts, top products)
* [ ] Daily Sales
* [ ] Weekly Sales
* [ ] Monthly Sales
* [ ] Order Reports
* [ ] Product Reports
* [ ] Customer Statistics
* [ ] Restaurant Statistics

---

# Sprint 14 - Settings 🟡

Largely absorbed by earlier sprints; only the last three are still open.

* [x] Restaurant Settings (Sprint 6 — profile, ordering & service toggles)
* [x] User Settings (Sprint 4 — profile & change password)
* [ ] Notification Settings (nothing to configure until notifications exist)
* [ ] Language Settings (`default_language` is stored but nothing reads it —
  the UI is English-only, no i18n layer)
* [x] Theme Settings (customer menu — see Menu Design in Sprint 8; the
  dashboard itself has no user-facing theme switch)

---

# Sprint 15 - Testing & Deployment 🟡

## Testing

* [ ] Unit Tests (backend Unit suite is placeholder only)
* [x] Feature Tests (18 files, 167 tests — auth, dashboard, admin, dining,
  order transitions, menu theme, image uploads, customer order tracking)
* [x] API Tests (covered by the Laravel feature suite)
* [ ] Browser Tests
* [ ] Frontend Tests (none yet)

## Deployment

* [ ] Docker
* [x] CI (GitHub Actions: backend suite on MySQL across PHP 8.2/8.3, plus
  frontend lint & build, on every push to main and every PR)
* [ ] CD (nothing deploys anywhere yet)
* [ ] Production Deployment
* [ ] Monitoring
* [ ] Logging
* [ ] Backup Strategy

---

# Future Features

## Payments

* [ ] Stripe
* [ ] PayPal
* [ ] Local Payment Gateway

## Reservations

* [ ] Table Reservations
* [ ] Reservation Calendar

## Loyalty

* [ ] Loyalty Program
* [ ] Reward Points
* [ ] Coupons
* [ ] Gift Cards

## Restaurant

* [ ] Inventory Management
* [ ] Multi-Branch Support
* [ ] Kitchen Display System
* [ ] Receipt Printing
* [ ] POS Integration

## Customer

* [ ] Customer Accounts
* [ ] Favorite Orders
* [ ] Reviews & Ratings
* [ ] Push Notifications

## Mobile

* [ ] Customer Mobile App
* [ ] Restaurant Mobile App
* [ ] Kitchen Mobile App

## Analytics

* [ ] Business Analytics
* [ ] AI Recommendations
* [ ] Sales Forecasting

## Integrations

* [ ] WhatsApp Notifications
* [ ] SMS Notifications
* [ ] Email Notifications
* [ ] Third-party API