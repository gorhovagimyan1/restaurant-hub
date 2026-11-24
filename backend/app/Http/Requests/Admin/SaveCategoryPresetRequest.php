<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A catalog option needs a name in every language, so every restaurant that
 * picks it is fully translated; descriptions are optional.
 */
class SaveCategoryPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is already gated to super-admins.
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'translations' => ['required', 'array:'.implode(',', SetLocale::SUPPORTED)],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];

        foreach (SetLocale::SUPPORTED as $code) {
            $rules["translations.{$code}"] = ['array:name,description'];
            $rules["translations.{$code}.name"] = ['required', 'string', 'max:255'];
            $rules["translations.{$code}.description"] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = ['en' => 'English', 'ru' => 'Русский', 'hy' => 'Հայերեն'];
        $attributes = [];
        foreach (SetLocale::SUPPORTED as $code) {
            $attributes["translations.{$code}.name"] = __('Name').' ('.$labels[$code].')';
            $attributes["translations.{$code}.description"] = __('Description').' ('.$labels[$code].')';
        }

        return $attributes;
    }
}
