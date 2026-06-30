<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provides the restaurant() relationship for business tables and keeps the
 * denormalized restaurant_id in sync by deriving it from a parent record
 * on creation (so a child can never point to a different tenant than its
 * parent). Models whose restaurant_id is set directly (top-level under a
 * restaurant) simply don't override restaurantParent().
 */
trait BelongsToRestaurant
{
    protected static function bootBelongsToRestaurant(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->restaurant_id)) {
                $parent = $model->restaurantParent();

                if ($parent !== null) {
                    $model->restaurant_id = $parent->restaurant_id;
                }
            }
        });
    }

    /**
     * The parent record this model derives its restaurant_id from.
     * Override in models whose restaurant_id is denormalized from a parent.
     */
    protected function restaurantParent(): ?Model
    {
        return null;
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
