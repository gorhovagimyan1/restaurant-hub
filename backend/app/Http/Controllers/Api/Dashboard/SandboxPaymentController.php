<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\PaymentStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in for a payment provider's webhook while developing.
 *
 * The sandbox card page calls this instead of Stripe calling us. It activates
 * the subscription exactly as the real webhook does — through
 * SubscriptionService::activate — so the paid path being demoed is the same
 * one that runs in production.
 */
class SandboxPaymentController extends Controller
{
    use ResolvesRestaurant;

    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * What the sandbox card page shows: which plan, and how much.
     */
    public function show(Request $request, SubscriptionPayment $payment): JsonResponse
    {
        $this->guard($request, $payment);

        $payment->load('plan');

        return ApiResponse::success([
            'id' => $payment->id,
            'plan' => $payment->plan->name,
            'interval' => $payment->interval->value,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->value,
        ]);
    }

    /**
     * "Pay" — grants the subscription without any money moving.
     */
    public function pay(Request $request, SubscriptionPayment $payment): JsonResponse
    {
        $this->guard($request, $payment);

        if ($payment->status === PaymentStatus::Paid) {
            return ApiResponse::success(null, 'This payment has already been completed.');
        }

        $subscription = $this->subscriptions->activate($payment, $request->user());

        return ApiResponse::success([
            'status' => $subscription->status->value,
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
        ], 'Payment complete.');
    }

    /**
     * Refuse anywhere this could cost real money, and refuse payments that
     * belong to somebody else's restaurant.
     */
    private function guard(Request $request, SubscriptionPayment $payment): void
    {
        abort_unless(
            app()->environment('local', 'testing')
                && config('billing.gateway') === 'sandbox',
            Response::HTTP_NOT_FOUND,
        );

        $restaurant = $this->currentRestaurant($request);

        abort_unless(
            $payment->subscription?->restaurant_id === $restaurant->id,
            Response::HTTP_NOT_FOUND,
        );
    }
}
