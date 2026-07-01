<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing product representation for the public menu.
 *
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primary = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_primary', true) ?? $this->images->first())
            : $this->primaryImage;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'ingredients' => $this->ingredients ?? [],
            'price' => (float) $this->price,
            'preparation_time' => $this->preparation_time,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'image' => $primary?->image,
            'images' => $this->whenLoaded('images', fn () => $this->images->pluck('image')),
        ];
    }
}
