<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription product. One plan carries both a monthly and a yearly price —
 * a restaurant chooses how often to pay, not a different product.
 */
class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'currency',
        'features',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function priceFor(BillingInterval $interval): float
    {
        return $interval->priceOn($this);
    }

    /**
     * What a year costs monthly, for the "save X%" line on the checkout screen.
     */
    public function yearlySavingPercent(): int
    {
        $monthlyForAYear = (float) $this->monthly_price * 12;

        if ($monthlyForAYear <= 0) {
            return 0;
        }

        return (int) round((1 - ((float) $this->yearly_price / $monthlyForAYear)) * 100);
    }
}
