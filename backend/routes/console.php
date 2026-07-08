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
