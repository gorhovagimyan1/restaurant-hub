<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CategoryPreset
 */
class CategoryPresetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // In the viewer's language, for lists and pickers.
            'name' => $this->text('name'),
            'description' => $this->text('description'),
            'translations' => $this->translations,
            // Defaults a restaurant category picked from it starts with.
            'prep_station' => $this->prep_station->value,
            'is_alcoholic' => $this->is_alcoholic,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            // How many restaurant categories use it (admin list only).
            'categories_count' => $this->whenCounted('categories'),
        ];
    }
}
