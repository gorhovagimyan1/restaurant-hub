<?php

use Illuminate\Support\Facades\Route;

// The production image bundles the built Vue SPA at resources/spa/index.html
// (see the root Dockerfile). Every non-API path returns it so client-side
// routes survive a reload; in local dev the SPA runs on Vite instead.
Route::get('/{path?}', function () {
    $spa = resource_path('spa/index.html');

    if (! is_file($spa)) {
        return view('welcome');
    }

    return response()->file($spa, ['Cache-Control' => 'no-cache']);
})
    ->where('path', '^(?!api(/|$)|storage/|up$).*$')
    // Static shell: no session, cookies or CSRF, so a page load never hits the DB.
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
