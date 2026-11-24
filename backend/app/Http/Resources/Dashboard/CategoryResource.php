<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            // Picked from the platform catalog (null = the owner's own category).
            'category_preset_id' => $this->category_preset_id,
            // Shown in the dashboard user's language; forms edit `original`
            // and `translations`.
            'name' => $this->translated('name'),
            'description' => $this->translated('description'),
            'original' => [
                'name' => $this->name,
                'description' => $this->description,
            ],
            'translations' => (object) ($this->translations ?? []),
            'image' => $this->image,
            // Where its products are prepared, and whether they are 18+
            // (each product may override both).
            'prep_station' => $this->prep_station->value,
            'is_alcoholic' => $this->is_alcoholic,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'products_count' => $this->when(
                $this->products_count !== null,
                fn () => (int) $this->products_count,
            ),
        ];
    }
}
