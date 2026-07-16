<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Owner-facing restaurant representation for the profile screen — includes the
 * full editable field set (the public resource exposes only a subset).
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
            // Read-only: the slug is baked into customer QR/portal URLs.
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->logo,
            'cover_image' => $this->cover_image,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'status' => $this->status->value,
            'is_active' => $this->is_active,
        ];
    }
}
