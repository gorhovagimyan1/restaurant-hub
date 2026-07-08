<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idle session timeout
    |--------------------------------------------------------------------------
    |
    | A dining session opened by scanning a table's QR code stays open until
    | staff settle the bill. The `sessions:close-idle` command (scheduled to
    | run regularly) closes any session with no guest activity — no scan,
    | order, bill view or service call — for this many hours, so an abandoned
    | table doesn't leave its session (and QR link) open indefinitely.
    |
    */

    'idle_timeout_hours' => (float) env('DINING_IDLE_TIMEOUT_HOURS', 3),

];
