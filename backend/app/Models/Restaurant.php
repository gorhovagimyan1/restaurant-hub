<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use App\Support\OpeningHours;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
     * This restaurant's access to the platform — trial or paid.
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * The opening hours for this restaurant, one row per weekday.
     *
     * @return HasMany<BusinessHour, $this>
     */
    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class)->orderBy('day_of_week');
    }

    /**
     * Date-specific opening-hour overrides (holidays / special days), which
     * take precedence over {@see self::businessHours()} for their date.
     *
     * @return HasMany<SpecialHour, $this>
     */
    public function specialHours(): HasMany
    {
        return $this->hasMany(SpecialHour::class)->orderBy('date');
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

    /**
     * Open/closed snapshot for a given moment (defaults to now), resolving the
     * weekly hours and any special-day overrides in the restaurant's timezone.
     *
     * @return array<string, mixed>
     */
    public function openStatus(?CarbonInterface $at = null): array
    {
        return (new OpeningHours($this))->statusAt($at ?? Carbon::now());
    }

    /**
     * Whether the restaurant is open at the given moment (defaults to now).
     */
    public function isOpen(?CarbonInterface $at = null): bool
    {
        return (new OpeningHours($this))->isOpenAt($at ?? Carbon::now());
    }
}
