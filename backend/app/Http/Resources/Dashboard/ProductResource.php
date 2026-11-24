<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
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
            'category_id' => $this->category_id,
            // Shown in the dashboard user's language; forms edit `original`
            // and `translations`.
            'name' => $this->translated('name'),
            'description' => $this->translated('description'),
            'ingredients' => $this->translated('ingredients') ?? [],
            'original' => [
                'name' => $this->name,
                'description' => $this->description,
                'ingredients' => $this->ingredients ?? [],
            ],
            'translations' => (object) ($this->translations ?? []),
            'price' => (float) $this->price,
            'preparation_time' => $this->preparation_time,
            // The product's own settings; null = same as its category.
            'prep_station' => $this->prep_station?->value,
            'is_alcoholic' => $this->is_alcoholic,
            'is_available' => $this->is_available,
            'rating' => ($this->reviews_count ?? 0) > 0 ? round((float) $this->reviews_avg_rating, 1) : null,
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'daily_stock' => $this->daily_stock,
            'stock_status' => $this->stockStatus(),
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'image' => $primary?->image,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'image' => $image->image,
                'is_primary' => $image->is_primary,
            ])),
        ];
    }
}
