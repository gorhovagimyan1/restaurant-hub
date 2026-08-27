<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\StripeGateway;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * What we hand Stripe when an owner picks a plan.
 *
 * Stripe itself is stubbed — the point is the request we build (currency,
 * amount, the reference the webhook reads back) and the payment row we record,
 * not Stripe's own behaviour.
 */
class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansSeeder::class);

        $this->plan = Plan::where('slug', 'standard')->sole();
        $this->restaurant = Restaurant::factory()->create();
        $this->restaurant->subscription()->create([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole(Role::RestaurantOwner->value);
        $this->restaurant->users()->attach($this->owner->id, [
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    /**
     * Bind a Stripe client that records every Checkout session it is asked to
     * create. Accumulating rather than overwriting lets one test drive several
     * checkouts and compare them.
     *
     * @param  array<int, array<string, mixed>>  $calls
     */
    private function fakeStripe(array &$calls): void
    {
        $sessions = Mockery::mock();
        $sessions->shouldReceive('create')
            ->andReturnUsing(function (array $params) use (&$calls) {
                $calls[] = $params;

                return (object) [
                    'id' => 'cs_test_abc123',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc123',
                ];
            });

        $checkout = (object) ['sessions' => $sessions];
        $client = Mockery::mock(StripeClient::class);
        $client->checkout = $checkout;

        $this->app->bind(PaymentGateway::class, fn () => new StripeGateway(
            $client,
            'http://localhost:5173/checkout?paid=1',
            'http://localhost:5173/checkout?cancelled=1',
        ));
    }

    public function test_choosing_a_plan_returns_a_stripe_redirect(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'yearly',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'redirect')
            ->assertJsonPath('data.redirect_url', 'https://checkout.stripe.com/c/pay/cs_test_abc123');
    }

    public function test_the_session_carries_the_right_amount_and_currency(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'yearly',
            ])->assertOk();

        $line = $calls[0]['line_items'][0]['price_data'];

        // 149,000 AMD in luma. Sending 149000 here would charge 1,490 dram.
        $this->assertSame(14_900_000, $line['unit_amount']);
        $this->assertSame('amd', $line['currency']);
        $this->assertSame('payment', $calls[0]['mode']);
    }

    public function test_the_session_references_the_payment_so_the_webhook_can_find_it(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])->assertOk();

        $payment = $this->restaurant->subscription->payments()->sole();

        $this->assertSame((string) $payment->id, $calls[0]['client_reference_id']);
        $this->assertSame((string) $payment->id, $calls[0]['metadata']['payment_id']);
        // And the session id is stored, so a payment can be traced in Stripe.
        $this->assertSame('cs_test_abc123', $payment->provider_reference);
        $this->assertSame('stripe', $payment->provider);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    public function test_nothing_is_granted_until_the_webhook_confirms(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])->assertOk();

        // Being sent to a card page is not paying for anything.
        $subscription = $this->restaurant->refresh()->subscription;
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertNull($subscription->current_period_end);
    }

    public function test_monthly_and_yearly_charge_their_own_prices(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        foreach (['monthly', 'yearly'] as $interval) {
            $this->actingAs($this->owner, 'sanctum')
                ->postJson('/api/dashboard/subscription/checkout', [
                    'plan_id' => $this->plan->id,
                    'interval' => $interval,
                ])->assertOk();
        }

        $this->assertSame(
            1_490_000,
            $calls[0]['line_items'][0]['price_data']['unit_amount'],
            'A monthly subscription should charge the monthly price.',
        );
        $this->assertSame(
            14_900_000,
            $calls[1]['line_items'][0]['price_data']['unit_amount'],
            'A yearly subscription should charge the yearly price.',
        );
    }

    public function test_the_plans_price_is_used_not_a_client_supplied_one(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        // A tampered request must not set its own price.
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'yearly',
                'amount' => 1,
                'unit_amount' => 1,
            ])->assertOk();

        $this->assertSame(
            (int) round(BillingInterval::Yearly->priceOn($this->plan) * 100),
            $calls[0]['line_items'][0]['price_data']['unit_amount'],
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
