# Database Design

Version: 1.0

---

# 1. Overview

Restaurant Hub uses a relational database based on a multi-tenant architecture.

Each restaurant owns its own data and cannot access data belonging to other restaurants.

The database is designed to be scalable, maintainable, and optimized for future features such as reservations, online payments, loyalty programs, inventory management, and delivery services.

---

# 2. Design Principles

The database follows these principles:

- Multi-tenant architecture
- Normalized database design
- Soft deletes where appropriate
- UUID support (future)
- Auditability
- Performance optimization
- Security through tenant isolation

---

# 3. Main Entities

The initial version of Restaurant Hub includes the following entities:

Authentication

- Users
- Roles
- Permissions

Restaurant

- Restaurants
- Restaurant Users
- Restaurant Settings

Dining

- Restaurant Tables
- QR Codes

Menu

- Menus
- Categories
- Products
- Product Images
- Product Modifiers

Ordering

- Orders
- Order Items

System

- Notifications
- Activity Logs (future)

---

# 4. Entity Relationships

High-level relationships:

Restaurant
│
├── Restaurant Users
├── Restaurant Tables
├── Menus
│   └── Categories
│       └── Products
│           ├── Product Images
│           └── Product Modifiers
│
└── Orders
└── Order Items
---

# 5. Multi-Tenant Strategy

Every business-related table belongs to a restaurant.

Examples:

- categories
- products
- menus
- restaurant_tables
- orders

Each record references its owner using:

restaurant_id

This guarantees complete isolation between restaurants.

---

# 6. Database Modules

Module 1
Authentication

Tables:

- users
- roles
- permissions
- model_has_roles

Module 2
Restaurant

Tables:

- restaurants
- restaurant_users
- restaurant_settings

Module 3
Dining

Tables:

- restaurant_tables
- qr_codes

Module 4
Menu

Tables:

- menus
- categories
- products
- product_images
- product_modifiers

Module 5
Ordering

Tables:

- orders
- order_items