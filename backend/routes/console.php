<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Safety net for abandoned tables: close dining sessions idle past the
// configured timeout so their QR links stop working (see config/dining.php).
Schedule::command('sessions:close-idle')->everyFifteenMinutes();

// Refill "made fresh daily" products (desserts) when each restaurant's business
// day rolls over. Hourly because restaurants sit in different timezones; the
// command only touches a product once per day (see config/stock.php).
Schedule::command('stock:reset-daily')->hourly();
