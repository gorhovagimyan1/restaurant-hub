<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
            // Picked from the platform catalog (name and description come from it)…
            'category_preset_id' => ['nullable', 'integer', Rule::exists('category_presets', 'id')],
            // …or a custom category the owner names.
            'name' => ['sometimes', 'required_without:category_preset_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Defaults to the catalog option's (or kitchen) when left out.
            'prep_station' => ['sometimes', 'string', Rule::in(\App\Enums\PrepStation::values())],
            'is_alcoholic' => ['sometimes', 'boolean'],
            'is_active' => ['boolean'],
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
        return \App\Models\Category::translationRules([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required_without' => 'Choose a category or enter your own name.',
        ];
    }
}
