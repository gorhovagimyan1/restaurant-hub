<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Dashboard\BusinessHoursController;
use App\Http\Controllers\Api\Dashboard\CategoryController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dashboard\EmployeeController;
use App\Http\Controllers\Api\Dashboard\OrderController;
use App\Http\Controllers\Api\Dashboard\OrderItemController;
use App\Http\Controllers\Api\Dashboard\ProductController;
use App\Http\Controllers\Api\Dashboard\ProductImageController;
use App\Http\Controllers\Api\Dashboard\SettingsController;
use App\Http\Controllers\Api\Dashboard\SpecialHoursController;
use App\Http\Controllers\Api\Dashboard\TableController;
use App\Http\Controllers\Api\Public\MenuController;
use App\Http\Controllers\Api\Public\OrderController as PublicOrderController;
use App\Http\Controllers\Api\Public\TableController as PublicTableController;
use Illuminate\Support\Facades\Route;

/*
 * Public, guest-accessible customer endpoints (no authentication).
 * Restaurant is bound by its slug rather than the default uuid route key.
 */
Route::prefix('public')->group(function () {
    Route::get('restaurants/{restaurant:slug}/menu', [MenuController::class, 'show']);
    Route::get('restaurants/{restaurant:slug}/status', [MenuController::class, 'status']);

    // QR flow: resolve a scanned table token, open the visit's dining session,
    // then place a guest order for it.
    Route::get('tables/{tableQrCode}', [PublicTableController::class, 'show']);
    Route::post('tables/{tableQrCode}/session', [PublicTableController::class, 'openSession']);
    Route::post('tables/{tableQrCode}/orders', [PublicOrderController::class, 'store']);

    // The table's running bill (all orders this visit) + "request bill" signal.
    Route::get('tables/{tableQrCode}/bill', [PublicTableController::class, 'bill']);
    Route::post('tables/{tableQrCode}/bill/request', [PublicTableController::class, 'requestBill']);

    // Call a waiter over to the table.
    Route::post('tables/{tableQrCode}/call-waiter', [PublicTableController::class, 'callWaiter']);
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Guest password recovery.
    Route::post('forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('reset-password', [PasswordResetController::class, 'reset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Authenticated profile management.
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('password', [ProfileController::class, 'changePassword']);
    });
});

/*
 *
 * Authenticated restaurant-owner dashboard: menu management scoped to the
 * user's own restaurant.
 */
Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('restaurant', [DashboardController::class, 'restaurant']);

    // Restaurant profile & opening hours (owners/managers).
    Route::middleware('can:restaurant.manage')->group(function () {
        Route::put('restaurant', [DashboardController::class, 'updateRestaurant']);

        Route::get('business-hours', [BusinessHoursController::class, 'index']);
        Route::put('business-hours', [BusinessHoursController::class, 'update']);

        Route::get('special-hours', [SpecialHoursController::class, 'index']);
        Route::put('special-hours', [SpecialHoursController::class, 'update']);
    });

    // Operational settings.
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', [SettingsController::class, 'show']);
        Route::put('settings', [SettingsController::class, 'update']);
    });

    // Staff management (owners/managers).
    Route::middleware('can:employees.manage')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::post('employees', [EmployeeController::class, 'store']);
        Route::put('employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
    });

    Route::middleware('can:categories.manage')->group(function () {
        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    });

    Route::middleware('can:products.manage')->group(function () {
        Route::get('products', [ProductController::class, 'index']);
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        Route::post('products/{product}/images', [ProductImageController::class, 'store']);
        Route::delete('product-images/{productImage}', [ProductImageController::class, 'destroy']);
    });

    // Live orders board — visible to owners, managers, waiters and kitchen.
    Route::middleware('can:orders.view')->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/history', [OrderController::class, 'history']);
        // Tables asking for a waiter / bill.
        Route::get('service-calls', [TableController::class, 'serviceCalls']);
    });

    Route::middleware('can:orders.update-status')->group(function () {
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
        // Advance a single item (cook / ready / deliver) independently.
        Route::patch('order-items/{orderItem}/status', [OrderItemController::class, 'updateStatus']);
        // Settle a table's whole bill and free it (payment on leaving).
        Route::post('tables/{table}/settle', [TableController::class, 'settle']);
        // Acknowledge (clear) a table's waiter call.
        Route::post('tables/{table}/ack-call', [TableController::class, 'acknowledgeCall']);
    });


    // Table + QR management (owners/managers).
    Route::middleware('can:tables.manage')->group(function () {
        Route::get('tables', [TableController::class, 'index']);
        Route::post('tables', [TableController::class, 'store']);
        Route::delete('tables/{table}', [TableController::class, 'destroy']);
    });
});
