<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\Billing\StripeGateway;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where Stripe tells us money actually moved.
 *
 * This — not the browser returning to the success URL — is what grants access.
 * A guest who pays and immediately closes the tab must still end up subscribed,
 * and conversely nobody should get in by visiting a success URL by hand.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('billing.stripe.webhook_secret');

        abort_if($secret === '', Response::HTTP_SERVICE_UNAVAILABLE, 'Stripe webhooks are not configured.');

        try {
            // Verifies the payload was signed by Stripe. Without this the
            // endpoint would grant free subscriptions to anyone who can POST.
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning('Rejected a Stripe webhook.', ['reason' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_BAD_REQUEST);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->completed($event->data->object),
            'checkout.session.expired' => $this->expired($event->data->object),
            default => null,
        };

        // Anything else is acknowledged so Stripe stops retrying it.
        return response()->json(['received' => true]);
    }

    /**
     * A Checkout Session finished paying: activate the subscription.
     */
    private function completed(object $session): void
    {
        // Asynchronous methods can complete a session before the money clears.
        if (($session->payment_status ?? 'paid') !== 'paid') {
            return;
        }

        $payment = $this->resolvePayment($session);

        if ($payment === null) {
            return;
        }

        // Stripe retries until it gets a 2xx, so this will be delivered more
        // than once. Activating twice would hand out a second free period.
        if ($payment->status === PaymentStatus::Paid) {
            return;
        }

        $this->subscriptions->activate($payment);
    }

    /**
     * The owner abandoned the hosted page and Stripe expired the session.
     */
    private function expired(object $session): void
    {
        $payment = $this->resolvePayment($session);

        if ($payment?->status === PaymentStatus::Pending) {
            $payment->update(['status' => PaymentStatus::Failed]);
        }
    }

    private function resolvePayment(object $session): ?SubscriptionPayment
    {
        $id = StripeGateway::paymentIdFrom($session);

        if ($id === null) {
            Log::warning('Stripe session carried no payment reference.', [
                'session' => $session->id ?? null,
            ]);

            return null;
        }

        return SubscriptionPayment::with('subscription')->find($id);
    }
}
