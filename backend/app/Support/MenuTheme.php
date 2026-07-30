<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The look a restaurant gives its public menu.
 *
 * A theme is a small, closed set of design decisions — palette, typography,
 * corner radius and how dishes are laid out — stored as JSON on the
 * restaurant's settings row. Everything else the customer portal needs
 * (borders, muted text, hover tints) is derived from these values in CSS, so
 * the stored payload stays small and can never drift into an unreadable state.
 *
 * Presets are complete themes rather than partial patches: picking one and then
 * nudging a colour leaves you with that preset's other choices intact, and the
 * preset key flips to `custom` so the UI stops claiming you are still on it.
 */
final class MenuTheme
{
    /** Google-hosted families the customer portal knows how to load. */
    public const FONTS = ['inter', 'poppins', 'dm-sans', 'space-grotesk', 'playfair', 'lora'];

    /** How dishes are arranged inside a category. */
    public const LAYOUTS = ['list', 'grid'];

    /** The treatment of the restaurant's cover image on the menu home page. */
    public const HERO_STYLES = ['cover', 'gradient', 'compact'];

    public const MIN_RADIUS = 0;

    public const MAX_RADIUS = 32;

    /**
     * Ready-made themes. `custom` is not listed: it is what a theme becomes
     * once it stops matching the preset it started from.
     *
     * @var array<string, array<string, mixed>>
     */
    public const PRESETS = [
        'classic' => [
            'label' => 'Classic',
            'primary_color' => '#10b981',
            'surface_color' => '#f6f8f7',
            'card_color' => '#ffffff',
            'heading_font' => 'inter',
            'body_font' => 'inter',
            'radius' => 16,
            'layout' => 'list',
            'show_images' => true,
            'hero_style' => 'cover',
        ],
        'bold' => [
            'label' => 'Bold',
            'primary_color' => '#f97316',
            'surface_color' => '#fff8f1',
            'card_color' => '#ffffff',
            'heading_font' => 'poppins',
            'body_font' => 'dm-sans',
            'radius' => 26,
            'layout' => 'grid',
            'show_images' => true,
            'hero_style' => 'cover',
        ],
        'minimal' => [
            'label' => 'Minimal',
            'primary_color' => '#171717',
            'surface_color' => '#ffffff',
            'card_color' => '#fafafa',
            'heading_font' => 'space-grotesk',
            'body_font' => 'inter',
            'radius' => 2,
            'layout' => 'list',
            'show_images' => false,
            'hero_style' => 'compact',
        ],
        'elegant' => [
            'label' => 'Elegant',
            'primary_color' => '#8c1c3a',
            'surface_color' => '#faf6f0',
            'card_color' => '#ffffff',
            'heading_font' => 'playfair',
            'body_font' => 'lora',
            'radius' => 8,
            'layout' => 'list',
            'show_images' => true,
            'hero_style' => 'gradient',
        ],
        'dark' => [
            'label' => 'Dark',
            'primary_color' => '#34d399',
            'surface_color' => '#101418',
            'card_color' => '#1a1f26',
            'heading_font' => 'poppins',
            'body_font' => 'inter',
            'radius' => 14,
            'layout' => 'grid',
            'show_images' => true,
            'hero_style' => 'gradient',
        ],
    ];

    /**
     * The theme a restaurant gets before it ever visits the design editor.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return ['preset' => 'classic'] + self::presetValues('classic');
    }

    /**
     * A preset's design values, without the human-facing label.
     *
     * @return array<string, mixed>
     */
    public static function presetValues(string $preset): array
    {
        $values = self::PRESETS[$preset] ?? self::PRESETS['classic'];
        unset($values['label']);

        return $values;
    }

    /**
     * Coerce stored (or submitted) JSON into a complete, renderable theme.
     *
     * Unknown keys are dropped and invalid values fall back to the default for
     * that field, so a hand-edited or half-migrated row still renders.
     *
     * @param  array<string, mixed>|null  $theme
     * @return array<string, mixed>
     */
    public static function normalize(?array $theme): array
    {
        $defaults = self::defaults();

        if (! $theme) {
            return $defaults;
        }

        $preset = is_string($theme['preset'] ?? null) ? $theme['preset'] : null;
        $preset = $preset === 'custom' || isset(self::PRESETS[$preset]) ? $preset : $defaults['preset'];

        // A named preset supplies the fallback for any field left unset, so a
        // theme stored as just {"preset":"dark"} still resolves in full.
        $base = $preset === 'custom' ? $defaults : ['preset' => $preset] + self::presetValues($preset);

        return [
            'preset' => $preset,
            'primary_color' => self::color($theme['primary_color'] ?? null, $base['primary_color']),
            'surface_color' => self::color($theme['surface_color'] ?? null, $base['surface_color']),
            'card_color' => self::color($theme['card_color'] ?? null, $base['card_color']),
            'heading_font' => self::oneOf($theme['heading_font'] ?? null, self::FONTS, $base['heading_font']),
            'body_font' => self::oneOf($theme['body_font'] ?? null, self::FONTS, $base['body_font']),
            'radius' => self::radius($theme['radius'] ?? null, $base['radius']),
            'layout' => self::oneOf($theme['layout'] ?? null, self::LAYOUTS, $base['layout']),
            'show_images' => is_bool($theme['show_images'] ?? null)
                ? $theme['show_images']
                : filter_var($theme['show_images'] ?? $base['show_images'], FILTER_VALIDATE_BOOL),
            'hero_style' => self::oneOf($theme['hero_style'] ?? null, self::HERO_STYLES, $base['hero_style']),
        ];
    }

    /**
     * The presets offered in the design editor, keyed by name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogue(): array
    {
        return self::PRESETS;
    }

    private static function color(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1
            ? strtolower($value)
            : $fallback;
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function oneOf(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function radius(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max(self::MIN_RADIUS, min(self::MAX_RADIUS, (int) $value));
    }
}
