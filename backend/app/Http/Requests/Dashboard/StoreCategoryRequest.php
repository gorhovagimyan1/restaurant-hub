<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            'category_preset_id' => ['nullable', 'integer', Rule::exists('category_presets', 'id')->where('is_active', true)],
            // …or a custom category the owner names.
            'name' => ['required_without:category_preset_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
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
