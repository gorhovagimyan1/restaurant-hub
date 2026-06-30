<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurant extends Model
{
    /** @use HasFactory<\Database\Factories\RestaurantFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'cover_image',
        'phone',
        'email',
        'website',
        'address',
        'city',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'currency',
        'timezone',
        'status',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RestaurantStatus::class,
            'is_active' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * The settings record for this restaurant.
     *
     * @return HasOne<RestaurantSettings, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(RestaurantSettings::class);
    }

    /**
     * The users (staff) attached to this restaurant.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'restaurant_user')
            ->using(RestaurantUser::class)
            ->withPivot(['is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * The dining tables belonging to this restaurant.
     *
     * @return HasMany<RestaurantTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    /**
     * The menus belonging to this restaurant.
     *
     * @return HasMany<Menu, $this>
     */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    /**
     * Categories owned by this restaurant (denormalized tenant link).
     *
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Products owned by this restaurant (denormalized tenant link).
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The orders placed at this restaurant.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
