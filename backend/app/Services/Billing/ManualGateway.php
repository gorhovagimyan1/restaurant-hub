<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * Payment by arrangement: the owner is shown how to transfer the money and a
 * super-admin confirms it from the platform admin area.
 *
 * This is a real, working way to bill a handful of local restaurants — and it
 * keeps the whole subscription flow exercisable end to end while the provider
 * decision is still open.
 */
final readonly class ManualGateway implements PaymentGateway
{
    public function __construct(private ?string $instructions = null) {}

    public function name(): string
    {
        return 'manual';
    }

    public function checkout(
        Subscription $subscription,
        Plan $plan,
        BillingInterval $interval,
    ): CheckoutResult {
        // Supersede any earlier unpaid attempt so the admin queue shows one
        // row per restaurant rather than every plan they clicked through.
        $subscription->payments()
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Failed]);

        $payment = $subscription->payments()->create([
            'plan_id' => $plan->id,
            'interval' => $interval,
            'amount' => $plan->priceFor($interval),
            'currency' => $plan->currency,
            'status' => PaymentStatus::Pending,
            'provider' => $this->name(),
        ]);

        return CheckoutResult::instructions(
            $payment,
            $this->instructions ?: 'Our team will contact you shortly to arrange payment and activate your subscription.',
        );
    }
}
