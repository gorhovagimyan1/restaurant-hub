<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing category representation for the public menu.
 *
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
            // In the visitor's language, falling back to the original wording.
            'name' => $this->translated('name'),
            'description' => $this->translated('description'),
            'image' => $this->image,
            'products_count' => $this->whenLoaded('products', fn () => $this->products->count()),
            'products' => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
