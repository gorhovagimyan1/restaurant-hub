<?php

return [

    /*
    |--------------------------------------------------------------------------
    | "Best choices" section
    |--------------------------------------------------------------------------
    |
    | The guest menu shows a section of top-rated dishes. A dish qualifies once
    | it has `min_reviews` ratings averaging at least `min_rating` stars, so one
    | enthusiastic 5-star can't win. Dishes are ranked by a smoothed score (a
    | Bayesian average) that pulls dishes with few reviews toward the
    | restaurant-wide average, and the top `limit` are shown.
    |
    */

    'min_reviews' => (int) env('REVIEWS_MIN_COUNT', 3),

    'min_rating' => (float) env('REVIEWS_MIN_RATING', 4.0),

    'limit' => (int) env('REVIEWS_BEST_LIMIT', 6),

    /*
    |--------------------------------------------------------------------------
    | Rating window after the visit ends
    |--------------------------------------------------------------------------
    |
    | A guest usually pays and leaves, so their session closes before they think
    | to rate. For this many hours after a session closes (settled or idle),
    | its token can still be used to rate the dishes served — and only that:
    | ordering, the bill and service calls stay dead the moment it closes.
    |
    */

    'window_hours' => (float) env('REVIEWS_WINDOW_HOURS', 2),

];
