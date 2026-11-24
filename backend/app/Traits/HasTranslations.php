<?php

declare(strict_types=1);

namespace App\Traits;

use App\Http\Middleware\SetLocale;

/**
 * Owner-written text in several languages.
 *
 * The plain columns (name, description, …) hold the original wording; the
 * `translations` JSON column holds per-locale overrides for the fields listed
 * in the model's `$translatable`. A missing or blank translation falls back
 * to the original, so a half-translated menu still reads sensibly.
 *
 * @property array<string, array<string, mixed>>|null $translations
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
        $this->mergeFillable(['translations']);
    }

    /**
     * The field in the given (default: current) locale, or the original.
     */
    public function translated(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $value = $this->translations[$locale][$field] ?? null;

        $blank = is_array($value) ? $value === [] : trim((string) $value) === '';

        return $blank ? $this->getAttribute($field) : $value;
    }

    /**
     * Validation rules for a `translations` payload covering $fields.
     *
     * @param  array<string, array<int, mixed>>  $fields  field => rules for one value
     * @return array<string, array<int, mixed>>
     */
    public static function translationRules(array $fields): array
    {
        $rules = [
            'translations' => ['nullable', 'array:'.implode(',', SetLocale::SUPPORTED)],
            // Only the top-level fields (not "ingredients.*") are allowed keys.
            'translations.*' => ['nullable', 'array:'.implode(',', array_filter(
                array_keys($fields),
                fn (string $field) => ! str_contains($field, '.'),
            ))],
        ];

        foreach ($fields as $field => $fieldRules) {
            $rules["translations.*.{$field}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Drop empty entries so the column stays small and fallbacks keep working.
     *
     * @param  array<string, mixed>|null  $translations
     * @return array<string, array<string, mixed>>|null
     */
    public static function cleanTranslations(?array $translations): ?array
    {
        $clean = [];

        foreach ($translations ?? [] as $locale => $fields) {
            foreach ((array) $fields as $field => $value) {
                if (is_array($value)) {
                    $value = array_values(array_filter(array_map('trim', $value), fn ($v) => $v !== ''));
                } elseif (is_string($value)) {
                    $value = trim($value);
                }

                if ($value !== '' && $value !== [] && $value !== null) {
                    $clean[$locale][$field] = $value;
                }
            }
        }

        return $clean ?: null;
    }

    public function setTranslationsAttribute(mixed $value): void
    {
        $value = is_string($value) ? json_decode($value, true) : $value;
        $clean = static::cleanTranslations(is_array($value) ? $value : null);

        $this->attributes['translations'] = $clean === null ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
    }
}
