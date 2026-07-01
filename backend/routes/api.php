<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Dashboard\CategoryController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dashboard\ProductController;
use App\Http\Controllers\Api\Dashboard\ProductImageController;
use App\Http\Controllers\Api\Public\MenuController;
use Illuminate\Support\Facades\Route;

/*
 * Public, guest-accessible customer endpoints (no authentication).
 * Restaurant is bound by its slug rather than the default uuid route key.
 */
Route::prefix('public')->group(function () {
    Route::get('restaurants/{restaurant:slug}/menu', [MenuController::class, 'show']);
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

/*
 * Authenticated restaurant-owner dashboard: menu management scoped to the
 * user's own restaurant.
 */
Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('restaurant', [DashboardController::class, 'restaurant']);

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
});
