<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Middleware\SetLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One option in the platform's category catalog, managed by super-admins.
 * Its name and description exist in every supported language.
 *
 * @property array<string, array{name: string, description?: string|null}> $translations
 */
class CategoryPreset extends Model
{
    protected $fillable = [
        'translations',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The field in the given (default: current) locale, falling back to the
     * first supported language that has it.
     */
    public function text(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        foreach (array_unique([$locale, ...SetLocale::SUPPORTED]) as $code) {
            $value = trim((string) ($this->translations[$code][$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
