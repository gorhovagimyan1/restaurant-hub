<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingInterval;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * How money is taken.
 *
 * The rest of the application never names a provider: it resolves this
 * interface from the container (see config/billing.php) and asks for a
 * checkout. Adding Paddle, a local Armenian gateway or Stripe later means
 * writing one class and changing one config value — nothing else moves.
 */
interface PaymentGateway
{
    /** Identifier stored on payments, e.g. "manual" or "paddle". */
    public function name(): string;

    /**
     * Begin paying for $plan on $interval, recording a pending payment.
     */
    public function checkout(
        Subscription $subscription,
        Plan $plan,
        BillingInterval $interval,
    ): CheckoutResult;
}
