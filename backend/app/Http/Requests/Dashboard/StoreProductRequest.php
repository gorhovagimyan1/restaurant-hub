<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

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
            'category_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*' => ['string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'preparation_time' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_available' => ['boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'daily_stock' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
