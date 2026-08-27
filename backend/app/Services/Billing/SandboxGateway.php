<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Exceptions\BillingUnavailable;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * A pretend card page, for developing and demoing the paid flow without a
 * provider account.
 *
 * It behaves like a hosted gateway — redirect out, pay, come back activated —
 * but no money moves and any card number is accepted. Switching
 * BILLING_GATEWAY to "stripe" swaps in the real thing with no other change.
 *
 * Refuses to run outside local/testing: enabling this on a live site would
 * hand out free subscriptions to anyone who found the URL.
 */
final readonly class SandboxGateway implements PaymentGateway
{
    public function __construct(
        private string $payUrl,
        private string $environment,
    ) {}

    public function name(): string
    {
        return 'sandbox';
    }

    public function checkout(
        Subscription $subscription,
        Plan $plan,
        BillingInterval $interval,
    ): CheckoutResult {
        if (! in_array($this->environment, ['local', 'testing'], true)) {
            throw BillingUnavailable::missingConfiguration(
                'The sandbox billing gateway cannot be used outside local development.',
            );
        }

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

        // Same shape a real hosted gateway returns, so the client code that
        // handles Stripe handles this too.
        return CheckoutResult::redirect(
            $payment,
            $this->payUrl.'?payment='.$payment->id,
        );
    }
}
