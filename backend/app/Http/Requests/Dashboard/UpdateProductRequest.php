<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'category_id' => ['sometimes', 'required', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*' => ['string', 'max:100'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'preparation_time' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_available' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
