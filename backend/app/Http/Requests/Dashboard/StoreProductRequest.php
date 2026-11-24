<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->translationRules(),
            'category_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*' => ['string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'preparation_time' => ['nullable', 'integer', 'min:0', 'max:600'],
            // Null = same as the category.
            'prep_station' => ['nullable', 'string', Rule::in(\App\Enums\PrepStation::values())],
            'is_alcoholic' => ['nullable', 'boolean'],
            'is_available' => ['boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'daily_stock' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Optional wording in other languages; blanks fall back to the original.
     *
     * @return array<string, array<int, mixed>>
     */
    private function translationRules(): array
    {
        return \App\Models\Product::translationRules([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
