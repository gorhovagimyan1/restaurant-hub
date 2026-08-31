<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A restaurant's access to the platform.
 *
 * Access is a function of status *and* time: a trial or paid period that has
 * run out grants nothing even though the stored status still says "trialing"
 * or "active". Nothing sweeps expired rows on a schedule, so every read goes
 * through hasAccess() rather than trusting the column.
 */
class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'restaurant_id',
        'plan_id',
        'interval',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'provider',
        'provider_reference',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<SubscriptionPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * When the current access window closes — the trial end while trialing,
     * the period end once something has been paid.
     */
    public function accessEndsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->status === SubscriptionStatus::Trialing
            ? $this->trial_ends_at
            : $this->current_period_end;
    }

    /**
     * The single question the gate asks.
     *
     * A cancelled subscription still passes until its paid period runs out —
     * they bought that time.
     */
    public function hasAccess(): bool
    {
        if (! $this->status->mayGrantAccess()) {
            return false;
        }

        $endsAt = $this->accessEndsAt();

        return $endsAt !== null && $endsAt->isFuture();
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at?->isFuture();
    }

    /**
     * Whole days left in the current window, floored at zero. Drives the
     * countdown banner.
     */
    public function daysRemaining(): int
    {
        $endsAt = $this->accessEndsAt();

        if ($endsAt === null || $endsAt->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInDays($endsAt, absolute: true));
    }
}
