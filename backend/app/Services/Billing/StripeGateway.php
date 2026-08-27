<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Models\Plan;
use App\Models\Subscription;
use Stripe\StripeClient;

/**
 * Card payment through Stripe's hosted Checkout.
 *
 * The owner is sent to Stripe's own card page — card details never touch this
 * application, which is what keeps PCI scope off our door. Payment is
 * recognised by webhook rather than by the browser coming back: a customer who
 * closes the tab after paying must still get their subscription.
 */
final readonly class StripeGateway implements PaymentGateway
{
    public function __construct(
        private StripeClient $stripe,
        private string $successUrl,
        private string $cancelUrl,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function checkout(
        Subscription $subscription,
        Plan $plan,
        BillingInterval $interval,
    ): CheckoutResult {
        // Supersede earlier unpaid attempts so an abandoned checkout doesn't
        // linger as a second open payment for the same restaurant.
        $subscription->payments()
            ->where('status', PaymentStatus::Pending)
            ->update(['status' => PaymentStatus::Failed]);

        $amount = $plan->priceFor($interval);

        $payment = $subscription->payments()->create([
            'plan_id' => $plan->id,
            'interval' => $interval,
            'amount' => $amount,
            'currency' => $plan->currency,
            'status' => PaymentStatus::Pending,
            'provider' => $this->name(),
        ]);

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($plan->currency),
                    'unit_amount' => Money::toMinorUnits($amount, $plan->currency),
                    'product_data' => [
                        'name' => $plan->name,
                        'description' => $interval === BillingInterval::Yearly
                            ? 'Yearly subscription'
                            : 'Monthly subscription',
                    ],
                ],
            ]],
            // Our payment id is the thread back from the webhook to this row.
            'client_reference_id' => (string) $payment->id,
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'subscription_id' => (string) $subscription->id,
                'restaurant_id' => (string) $subscription->restaurant_id,
            ],
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
        ]);

        $payment->update(['provider_reference' => $session->id]);

        return CheckoutResult::redirect($payment, $session->url);
    }

    /**
     * The payment id carried on a completed Checkout Session.
     *
     * Reads client_reference_id first and falls back to metadata, since the
     * two are set together and either may be the one present on a given
     * payload version.
     */
    public static function paymentIdFrom(object $session): ?int
    {
        $id = $session->client_reference_id
            ?? ($session->metadata->payment_id ?? null);

        return $id === null ? null : (int) $id;
    }
}
