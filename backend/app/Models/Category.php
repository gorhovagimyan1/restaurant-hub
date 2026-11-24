<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use BelongsToRestaurant, HasFactory, SoftDeletes;
    use HasTranslations {
        translated as translatedOwn;
    }

    /** Fields owners can write per language (see HasTranslations). */
    public const TRANSLATABLE = ['name', 'description'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'menu_id',
        'category_preset_id',
        'name',
        'description',
        'image',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Derive restaurant_id from the parent menu.
     */
    protected function restaurantParent(): ?Model
    {
        return $this->menu;
    }

    /**
     * @return HasMany<Product, $this>
     */
    /** The catalog option this category was picked from, if any. */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(CategoryPreset::class, 'category_preset_id');
    }

    /**
     * Name and description come from the catalog for a picked category, so
     * it is translated without the owner writing anything; custom categories
     * use their own translations (see HasTranslations).
     */
    public function translated(string $field, ?string $locale = null): mixed
    {
        if ($this->category_preset_id && in_array($field, ['name', 'description'], true) && $this->preset) {
            return $this->preset->text($field, $locale);
        }

        return $this->translatedOwn($field, $locale);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
