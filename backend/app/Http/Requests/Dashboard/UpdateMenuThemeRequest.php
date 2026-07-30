<?php

namespace App\Http\Requests\Dashboard;

use App\Support\MenuTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the design a restaurant applies to its public menu.
 */
class UpdateMenuThemeRequest extends FormRequest
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
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            // `custom` is what the editor sends once a preset has been tweaked.
            'preset' => ['required', 'string', Rule::in([...array_keys(MenuTheme::PRESETS), 'custom'])],
            'primary_color' => $hex,
            'surface_color' => $hex,
            'card_color' => $hex,
            'heading_font' => ['required', 'string', Rule::in(MenuTheme::FONTS)],
            'body_font' => ['required', 'string', Rule::in(MenuTheme::FONTS)],
            'radius' => ['required', 'integer', 'min:'.MenuTheme::MIN_RADIUS, 'max:'.MenuTheme::MAX_RADIUS],
            'layout' => ['required', 'string', Rule::in(MenuTheme::LAYOUTS)],
            'show_images' => ['required', 'boolean'],
            'hero_style' => ['required', 'string', Rule::in(MenuTheme::HERO_STYLES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Pick a colour in #rrggbb form.',
            'surface_color.regex' => 'Pick a colour in #rrggbb form.',
            'card_color.regex' => 'Pick a colour in #rrggbb form.',
        ];
    }
}
