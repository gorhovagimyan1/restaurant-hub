# System Architecture

Version: 1.0

---

# Overview

Restaurant Hub follows a client-server architecture.

The frontend is developed using Vue 3.

The backend is developed using Laravel 12.

Communication occurs through a REST API.

---

# Architecture

Customer Browser

↓

Vue 3 Application

↓

REST API

↓

Laravel Backend

↓

MySQL Database

---

# Backend Modules

* Authentication
* Restaurant Management
* Employee Management
* Table Management
* QR Code Management
* Menu Management
* Category Management
* Product Management
* Order Management
* Kitchen Dashboard
* Reporting
* Settings

---

# Frontend Modules

Customer

* Menu
* Cart
* Checkout
* Order Status

Restaurant Dashboard

* Dashboard
* Tables
* Products
* Categories
* Orders
* Employees
* Reports
* Settings

Administrator

* Restaurants
* Users
* Statistics

---

# Technologies

Backend

* Laravel 12
* PHP 8.4
* MySQL
* Sanctum
* Spatie Permission

Frontend

* Vue 3
* Vite
* Pinia
* Vue Router
* Axios
* Tailwind CSS
* PrimeVue
