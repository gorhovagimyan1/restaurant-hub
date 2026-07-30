<?php

namespace App\Http\Resources\Public;

use App\Http\Resources\Dashboard\BusinessHourResource;
use App\Http\Resources\Dashboard\SpecialHourResource;
use App\Support\MenuTheme;
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
            'business_hours' => BusinessHourResource::collection(
                $this->whenLoaded('businessHours'),
            ),
            'special_hours' => SpecialHourResource::collection(
                $this->whenLoaded('specialHours'),
            ),
            'open_status' => $this->openStatus(),
            // The restaurant's own menu design; always complete, so the portal
            // can render straight from it without defaults of its own.
            'menu_theme' => MenuTheme::normalize($this->settings?->menu_theme),
        ];
    }
}
