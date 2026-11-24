<?php

namespace App\Services\Reviews;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Picks the dishes for the guest menu's "Best choices" section.
 */
class BestChoices
{
    /**
     * @param  Collection<int, Product>  $products  loaded withAvg('reviews','rating') + withCount('reviews')
     * @return Collection<int, Product>
     */
    public function pick(Collection $products): Collection
    {
        $rated = $products->filter(fn (Product $p) => ($p->reviews_count ?? 0) > 0);

        $totalReviews = $rated->sum('reviews_count');

        if ($totalReviews === 0) {
            return collect();
        }

        $min = max(1, (int) config('reviews.min_reviews'));

        // The restaurant-wide mean rating, which thinly-reviewed dishes are
        // pulled toward so a couple of 5-stars can't outrank a proven favourite.
        $globalMean = $rated->sum(fn (Product $p) => $p->reviews_avg_rating * $p->reviews_count) / $totalReviews;

        return $rated
            ->filter(fn (Product $p) => $p->reviews_count >= $min
                && $p->reviews_avg_rating >= (float) config('reviews.min_rating')
                && $p->isOrderable())
            ->sortByDesc(fn (Product $p) => ($p->reviews_count * $p->reviews_avg_rating + $min * $globalMean)
                / ($p->reviews_count + $min))
            ->take((int) config('reviews.limit'))
            ->values();
    }
}
