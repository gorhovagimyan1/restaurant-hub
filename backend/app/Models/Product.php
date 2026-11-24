<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToRestaurant, HasFactory, HasTranslations, SoftDeletes;

    /** Fields owners can write per language (see HasTranslations). */
    public const TRANSLATABLE = ['name', 'description', 'ingredients'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'description',
        'ingredients',
        'sku',
        'price',
        'preparation_time',
        'prep_station',
        'is_alcoholic',
        'is_available',
        'stock_quantity',
        'low_stock_threshold',
        'daily_stock',
        'stock_reset_on',
        'is_featured',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ingredients' => 'array',
            'price' => 'decimal:2',
            'preparation_time' => 'integer',
            // Null = use the category's setting (see effectivePrepStation()).
            'prep_station' => \App\Enums\PrepStation::class,
            'is_alcoholic' => 'boolean',
            'is_available' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'daily_stock' => 'integer',
            'stock_reset_on' => 'date',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Whether portions are counted for this product.
     */
    public function tracksStock(): bool
    {
        return $this->stock_quantity !== null;
    }

    /**
     * Where this product is prepared: its own setting, else its category's.
     */
    public function effectivePrepStation(): \App\Enums\PrepStation
    {
        return $this->prep_station
            ?? $this->category?->prep_station
            ?? \App\Enums\PrepStation::Kitchen;
    }

    /**
     * Whether it contains alcohol (18+): its own setting, else its category's.
     */
    public function effectiveIsAlcoholic(): bool
    {
        return $this->is_alcoholic ?? (bool) $this->category?->is_alcoholic;
    }

    /**
     * Can a guest order this right now? Honours the manual toggle and, for
     * tracked products, whether any portions are left.
     */
    public function isOrderable(): bool
    {
        return $this->is_available && (! $this->tracksStock() || $this->stock_quantity > 0);
    }

    /**
     * Stock level at (or below) which staff are alerted.
     */
    public function lowStockLevel(): int
    {
        return $this->low_stock_threshold ?? (int) config('stock.low_threshold');
    }

    /**
     * untracked | ok | low | out — staff-facing only.
     */
    public function stockStatus(): string
    {
        return match (true) {
            ! $this->tracksStock() => 'untracked',
            $this->stock_quantity === 0 => 'out',
            $this->stock_quantity <= $this->lowStockLevel() => 'low',
            default => 'ok',
        };
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Derive restaurant_id from the parent category.
     */
    protected function restaurantParent(): ?Model
    {
        return $this->category;
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Guest ratings of this dish.
     *
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * The primary image for this product.
     *
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }
}
