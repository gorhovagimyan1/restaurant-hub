<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\Role;
use App\Models\Restaurant;
use Illuminate\Http\Request;

/**
 * Resolves the restaurant the authenticated user manages and guards against
 * cross-tenant access to child records.
 */
trait ResolvesRestaurant
{
    /**
     * The restaurant the current user manages.
     *
     * Owners/managers resolve to the restaurant they belong to. A platform
     * super-admin (who belongs to none) falls back to the first restaurant so
     * they can manage it. If neither yields a restaurant, respond with a clear
     * message rather than a bare 404.
     */
    protected function currentRestaurant(Request $request): Restaurant
    {
        $user = $request->user();

        $restaurant = $user->restaurants()->first();

        if ($restaurant === null && $user->hasRole(Role::SuperAdmin->value)) {
            $restaurant = Restaurant::query()->orderBy('id')->first();
        }

        abort_if(
            $restaurant === null,
            422,
            'No restaurant is associated with your account.',
        );

        return $restaurant;
    }

    /**
     * Ensure a model belongs to the given restaurant, otherwise 404.
     */
    protected function guardTenant(Restaurant $restaurant, mixed $model): void
    {
        abort_unless($model->restaurant_id === $restaurant->id, 404);
    }
}
