# User Roles

## Overview

Restaurant Hub uses a role-based access control (RBAC) system. Each user has a specific role with permissions that determine which features they can access.

---

# Customer (Guest)

## Description

A customer who visits a restaurant and scans a QR code. Registration is not required.

### Permissions

- View restaurant information
- View menus
- View categories
- View products
- View product details
- Add products to cart
- Update cart quantity
- Remove products from cart
- Add notes to an order
- Place an order
- View own order status

### Restrictions

- Cannot access any dashboard
- Cannot modify restaurant information
- Cannot view other customers' orders

---

# Restaurant Owner

## Description

The owner of a restaurant. Has full access to all restaurant resources.

### Permissions

- Manage restaurant profile
- Manage employees
- Manage tables
- Manage menus
- Manage categories
- Manage products
- Manage product modifiers
- Manage QR codes
- Manage orders
- View reports
- Configure restaurant settings

### Restrictions

- Cannot access other restaurants' data

---

# Manager

## Description

Responsible for daily restaurant operations.

### Permissions

- View dashboard
- Manage employees
- Manage tables
- Manage menus
- Manage products
- Manage orders
- View reports

### Restrictions

- Cannot delete the restaurant
- Cannot manage subscription or billing

---

# Kitchen Staff

## Description

Responsible for preparing food.

### Permissions

- View incoming orders
- Accept orders
- Change order status
- Mark orders as ready

### Restrictions

- Cannot edit menus
- Cannot edit products
- Cannot manage employees

---

# Waiter

## Description

Responsible for serving customers.

### Permissions

- View active tables
- View active orders
- Mark orders as served

### Restrictions

- Cannot modify menu
- Cannot modify products
- Cannot manage employees

---

# Platform Administrator

## Description

Administrator of the Restaurant Hub platform.

### Permissions

- Manage restaurants
- Manage subscriptions
- Manage platform users
- View platform analytics
- Suspend restaurants
- Configure global settings

### Restrictions

- None