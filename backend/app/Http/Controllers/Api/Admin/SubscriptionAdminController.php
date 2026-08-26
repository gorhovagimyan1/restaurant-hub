<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-side billing control.
 *
 * With the manual gateway this is where money actually gets recognised: a
 * transfer lands, a super-admin confirms the matching payment, and the
 * restaurant's access is extended. A provider webhook would call the same
 * SubscriptionService::activate().
 */
class SubscriptionAdminController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * The billing work queue: payments awaiting confirmation, oldest first.
     *
     * Recently confirmed ones ride along so an admin can see their action
     * landed — a queue that empties without trace is hard to trust.
     */
    public function pending(): JsonResponse
    {
        $pending = SubscriptionPayment::query()
            ->where('status', PaymentStatus::Pending)
            ->with(['plan', 'subscription.restaurant'])
            ->oldest()
            ->get();

        $confirmed = SubscriptionPayment::query()
            ->where('status', PaymentStatus::Paid)
            ->with(['plan', 'subscription.restaurant', 'confirmedBy'])
            ->latest('paid_at')
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'pending' => $pending->map(fn (SubscriptionPayment $p) => $this->present($p)),
            'recently_confirmed' => $confirmed->map(fn (SubscriptionPayment $p) => $this->present($p) + [
                'paid_at' => $p->paid_at?->toIso8601String(),
                'confirmed_by' => $p->confirmedBy?->full_name,
                'period_end' => $p->subscription->current_period_end?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SubscriptionPayment $payment): array
    {
        $restaurant = $payment->subscription->restaurant;

        return [
            'id' => $payment->id,
            'restaurant' => [
                'id' => $restaurant->uuid,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
            ],
            'plan' => $payment->plan->name,
            'interval' => $payment->interval->value,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'requested_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    /**
     * Confirm a payment and put the restaurant into a paid period.
     */
    public function confirm(Request $request, SubscriptionPayment $payment): JsonResponse
    {
        abort_unless(
            $payment->status === PaymentStatus::Pending,
            422,
            'That payment has already been settled.',
        );

        $subscription = $this->subscriptions->activate($payment, $request->user());

        return ApiResponse::success([
            'status' => $subscription->status->value,
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
        ], 'Payment confirmed — the restaurant now has access.');
    }
}
