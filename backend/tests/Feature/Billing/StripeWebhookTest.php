<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Billing\Money;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stripe tells us money moved. This endpoint is the only thing that grants a
 * paid subscription, so it has to be exactly as trusting as the signature.
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    private Restaurant $restaurant;

    private Subscription $subscription;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.stripe.webhook_secret', self::SECRET);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansSeeder::class);

        $this->plan = Plan::where('slug', 'standard')->sole();
        $this->restaurant = Restaurant::factory()->create();

        $this->subscription = $this->restaurant->subscription()->create([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        $owner = User::factory()->create();
        $owner->assignRole(Role::RestaurantOwner->value);
        $this->restaurant->users()->attach($owner->id, ['is_active' => true, 'joined_at' => now()]);
    }

    private function pendingPayment(string $interval = 'monthly'): SubscriptionPayment
    {
        return $this->subscription->payments()->create([
            'plan_id' => $this->plan->id,
            'interval' => $interval,
            'amount' => $this->plan->priceFor(\App\Enums\BillingInterval::from($interval)),
            'currency' => $this->plan->currency,
            'status' => PaymentStatus::Pending,
            'provider' => 'stripe',
            'provider_reference' => 'cs_test_123',
        ]);
    }

    /**
     * Post an event signed the way Stripe signs them.
     */
    private function sendEvent(array $payload, ?string $secret = null)
    {
        $body = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret ?? self::SECRET);

        return $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );
    }

    private function completedEvent(SubscriptionPayment $payment, string $paymentStatus = 'paid'): array
    {
        return [
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'payment_status' => $paymentStatus,
                'client_reference_id' => (string) $payment->id,
                'metadata' => ['payment_id' => (string) $payment->id],
            ]],
        ];
    }

    public function test_a_completed_checkout_activates_the_subscription(): void
    {
        $payment = $this->pendingPayment('yearly');

        $this->sendEvent($this->completedEvent($payment))->assertOk();

        $payment->refresh();
        $subscription = $this->subscription->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->hasAccess());
        $this->assertEqualsWithDelta(365, now()->diffInDays($subscription->current_period_end), 2);
    }

    public function test_an_unsigned_or_forged_payload_is_rejected(): void
    {
        $payment = $this->pendingPayment();

        // Signed with the wrong secret — i.e. not by Stripe.
        $this->sendEvent($this->completedEvent($payment), 'whsec_wrong')
            ->assertStatus(400);

        // No signature at all.
        $this->postJson('/api/webhooks/stripe', $this->completedEvent($payment))
            ->assertStatus(400);

        // Nothing was granted.
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertFalse($this->subscription->refresh()->hasAccess());
    }

    public function test_a_replayed_event_does_not_grant_a_second_period(): void
    {
        $payment = $this->pendingPayment('monthly');

        $this->sendEvent($this->completedEvent($payment))->assertOk();
        $firstEnd = $this->subscription->refresh()->current_period_end;

        // Stripe retries until it sees a 2xx, so duplicates are normal.
        $this->sendEvent($this->completedEvent($payment))->assertOk();

        $this->assertEquals(
            $firstEnd->timestamp,
            $this->subscription->refresh()->current_period_end->timestamp,
            'Replaying a webhook must not extend the subscription again.',
        );
    }

    public function test_an_unpaid_session_grants_nothing(): void
    {
        $payment = $this->pendingPayment();

        // Some payment methods complete the session before money clears.
        $this->sendEvent($this->completedEvent($payment, 'unpaid'))->assertOk();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertFalse($this->subscription->refresh()->hasAccess());
    }

    public function test_an_expired_session_marks_the_payment_failed(): void
    {
        $payment = $this->pendingPayment();

        $this->sendEvent([
            'id' => 'evt_test_2',
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => ['object' => [
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'client_reference_id' => (string) $payment->id,
                'metadata' => ['payment_id' => (string) $payment->id],
            ]],
        ])->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);
        $this->assertFalse($this->subscription->refresh()->hasAccess());
    }

    public function test_unrelated_events_are_acknowledged_and_ignored(): void
    {
        // Stripe retries anything that isn't a 2xx, so we must not 500 on the
        // event types we don't care about.
        $this->sendEvent([
            'id' => 'evt_test_3',
            'object' => 'event',
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_test']],
        ])->assertOk();
    }

    public function test_amounts_convert_to_stripe_minor_units(): void
    {
        // AMD has a minor unit, so 149,000 dram is 14,900,000 luma. Sending
        // the unmultiplied figure would charge roughly a hundredth of the price.
        $this->assertSame(14_900_000, Money::toMinorUnits(149000, 'AMD'));
        $this->assertSame(1_990, Money::toMinorUnits(19.90, 'USD'));

        // Zero-decimal currencies pass through untouched.
        $this->assertSame(5000, Money::toMinorUnits(5000, 'JPY'));

        $this->assertEqualsWithDelta(149000.0, Money::fromMinorUnits(14_900_000, 'AMD'), 0.001);
        $this->assertEqualsWithDelta(5000.0, Money::fromMinorUnits(5000, 'JPY'), 0.001);
    }
}
