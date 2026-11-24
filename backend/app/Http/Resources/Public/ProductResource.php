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
            // In the visitor's language, falling back to the original wording.
            'name' => $this->translated('name'),
            'description' => $this->translated('description'),
            'ingredients' => $this->translated('ingredients') ?? [],
            'price' => (float) $this->price,
            'preparation_time' => $this->preparation_time,
            // Effective availability: the manual toggle AND stock. The stock count
            // itself is never exposed to guests.
            'is_available' => $this->isOrderable(),
            // Average stars (1 decimal) and how many ratings; null until rated.
            'rating' => ($this->reviews_count ?? 0) > 0 ? round((float) $this->reviews_avg_rating, 1) : null,
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'is_featured' => $this->is_featured,
            // Contains alcohol: the menu shows an 18+ badge.
            'is_alcoholic' => $this->effectiveIsAlcoholic(),
            'image' => $primary?->image,
            'images' => $this->whenLoaded('images', fn () => $this->images->pluck('image')),
        ];
    }
}
