<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\PlanResource;
use App\Http\Resources\Dashboard\SubscriptionResource;
use App\Models\Plan;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The owner's own billing.
 *
 * These routes sit outside the `subscribed` gate on purpose — an owner whose
 * trial has lapsed has to be able to see the plans and pay for one.
 */
class SubscriptionController extends Controller
{
    use ResolvesRestaurant;

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Current billing state plus the plans on sale.
     */
    public function show(Request $request): JsonResponse
    {
        $subscription = $this->subscriptions
            ->forRestaurant($this->currentRestaurant($request))
            ->load('plan');

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($subscription),
            'plans' => PlanResource::collection($this->subscriptions->availablePlans()),
            'trial_days' => $this->subscriptions->trialDays(),
        ]);
    }

    /**
     * Start paying for a plan.
     *
     * The response says what to do next — follow a redirect, or read the
     * instructions — so the client never needs to know which gateway is live.
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'interval' => ['required', Rule::in(\App\Enums\BillingInterval::values())],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $this->subscriptions->assertPlanIsAvailable($plan);

        $subscription = $this->subscriptions->forRestaurant($this->currentRestaurant($request));

        $result = $this->gateway->checkout(
            $subscription,
            $plan,
            $this->subscriptions->intervalFrom($data['interval']),
        );

        return ApiResponse::success(
            $result->toArray(),
            'Checkout started.',
        );
    }

    /**
     * Stop the subscription renewing. Paid time already bought is honoured.
     */
    public function cancel(Request $request): JsonResponse
    {
        $subscription = $this->subscriptions->forRestaurant($this->currentRestaurant($request));

        abort_if(
            $subscription->plan_id === null,
            422,
            'There is no active subscription to cancel.',
        );

        $subscription = $this->subscriptions->cancel($subscription);

        return ApiResponse::success(
            new SubscriptionResource($subscription->load('plan')),
            'Subscription cancelled. You keep access until the end of the period you have paid for.',
        );
    }
}
