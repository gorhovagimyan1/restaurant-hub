<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Services\Billing\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the restaurant dashboard when a restaurant is neither trialing nor
 * paid up.
 *
 * Only staff-facing routes carry this. The public customer endpoints are
 * deliberately left open: a diner scanning a QR has no idea their restaurant's
 * card failed, and killing service mid-meal punishes the wrong people.
 *
 * Answers 402 Payment Required with the subscription state, which the client
 * turns into a redirect to checkout.
 */
class EnsureSubscribed
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Platform staff are not tenants and never pay.
        if ($user === null || $user->hasRole(Role::SuperAdmin->value)) {
            return $next($request);
        }

        $restaurant = $user->restaurants()->first();

        // No restaurant means nothing to bill; the controllers already answer
        // that case with their own message.
        if ($restaurant === null) {
            return $next($request);
        }

        $subscription = $this->subscriptions->forRestaurant($restaurant);

        if ($subscription->hasAccess()) {
            return $next($request);
        }

        $this->subscriptions->markExpiredIfLapsed($subscription);

        return response()->json([
            'success' => false,
            'message' => $subscription->trial_ends_at && ! $subscription->plan_id
                ? 'Your free trial has ended. Choose a plan to continue.'
                : 'Your subscription is not active. Choose a plan to continue.',
            'data' => [
                'subscription_required' => true,
                'status' => $subscription->status->value,
            ],
        ], Response::HTTP_PAYMENT_REQUIRED);
    }
}
