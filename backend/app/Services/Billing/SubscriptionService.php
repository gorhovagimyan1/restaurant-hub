<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every transition a subscription can make.
 *
 * Kept out of the controllers so the same rules apply whether a change comes
 * from an owner clicking checkout, a super-admin confirming a transfer, or a
 * provider webhook later on.
 */
class SubscriptionService
{
    /**
     * Give a newly registered restaurant its free evaluation period.
     */
    public function startTrial(Restaurant $restaurant): Subscription
    {
        return $restaurant->subscription()->create([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays($this->trialDays()),
        ]);
    }

    /**
     * Confirm a payment and put the restaurant into a paid period.
     *
     * Renewals extend from the existing period end rather than from now, so
     * paying early never costs the owner the days they already hold.
     */
    public function activate(SubscriptionPayment $payment, ?User $confirmedBy = null): Subscription
    {
        return DB::transaction(function () use ($payment, $confirmedBy) {
            $subscription = $payment->subscription()->lockForUpdate()->firstOrFail();
            $interval = $payment->interval;

            $start = now();
            $extendFrom = $subscription->current_period_end?->isFuture()
                ? $subscription->current_period_end
                : $start;

            $subscription->update([
                'plan_id' => $payment->plan_id,
                'interval' => $interval,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $start,
                'current_period_end' => $interval->advance($extendFrom),
                // A fresh payment revives a cancelled subscription.
                'cancelled_at' => null,
                'provider' => $payment->provider,
            ]);

            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'confirmed_by' => $confirmedBy?->id,
            ]);

            return $subscription->refresh();
        });
    }

    /**
     * Stop the subscription renewing. Access runs to the end of the period
     * they have already paid for.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $subscription->refresh();
    }

    /**
     * Fold a lapsed subscription into the Expired status.
     *
     * Access checks are time-based and never trust the column, so this is
     * cosmetic — it keeps the admin list honest rather than gating anything.
     */
    public function markExpiredIfLapsed(Subscription $subscription): Subscription
    {
        if (! $subscription->hasAccess() && $subscription->status !== SubscriptionStatus::Expired) {
            $subscription->update(['status' => SubscriptionStatus::Expired]);
        }

        return $subscription;
    }

    /**
     * The subscription for a restaurant, creating a trial for tenants that
     * predate billing so existing restaurants are not locked out on deploy.
     */
    public function forRestaurant(Restaurant $restaurant): Subscription
    {
        return $restaurant->subscription()->firstOr(
            fn () => $this->startTrial($restaurant),
        );
    }

    public function trialDays(): int
    {
        return (int) config('billing.trial_days', 14);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Plan>
     */
    public function availablePlans()
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get();
    }

    /**
     * Guard against paying for a plan that is no longer on sale.
     */
    public function assertPlanIsAvailable(Plan $plan): void
    {
        abort_unless($plan->is_active, 422, 'That plan is no longer available.');
    }

    public function intervalFrom(string $value): BillingInterval
    {
        return BillingInterval::from($value);
    }
}
