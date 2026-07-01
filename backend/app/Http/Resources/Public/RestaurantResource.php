<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing restaurant representation for the public menu.
 *
 * @mixin \App\Models\Restaurant
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
            'description' => $this->description,
            'logo' => $this->logo,
            'cover_image' => $this->cover_image,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'currency' => $this->currency,
        ];
    }
}
