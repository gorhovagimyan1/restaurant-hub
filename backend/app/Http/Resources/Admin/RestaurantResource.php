<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A restaurant as seen by a platform super-admin. Counts are exposed when the
 * relevant aggregates have been loaded via withCount().
 *
 * @mixin Restaurant
 */
class RestaurantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'country' => $this->country,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'is_active' => $this->is_active,
            'owner' => $this->whenLoaded('users', fn () => optional($this->users->first(), fn ($u) => [
                'id' => $u->uuid,
                'full_name' => $u->full_name,
                'email' => $u->email,
            ])),
            'users_count' => $this->whenCounted('users'),
            'orders_count' => $this->whenCounted('orders'),
            'tables_count' => $this->whenCounted('tables'),
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at,
        ];
    }
}
