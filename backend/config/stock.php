<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default low-stock level
    |--------------------------------------------------------------------------
    |
    | When a tracked product's stock falls to this many portions (or fewer),
    | owners and managers are emailed to check it. A product can override this
    | with its own `low_stock_threshold`; 0 means "only alert when it runs out".
    |
    */

    'low_threshold' => (int) env('STOCK_LOW_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Business-day rollover hour
    |--------------------------------------------------------------------------
    |
    | Products with a `daily_stock` are refilled when a new business day starts,
    | in the restaurant's own timezone. A restaurant that serves past midnight
    | shouldn't have its desserts refilled at 00:00 mid-service, so the day
    | rolls over at this hour (0-23) instead.
    |
    */

    'reset_hour' => (int) env('STOCK_RESET_HOUR', 5),

];
