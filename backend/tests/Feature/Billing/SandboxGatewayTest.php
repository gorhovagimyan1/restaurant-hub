<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stand-in card page used while no provider account exists.
 *
 * It grants subscriptions without money changing hands, so most of what
 * matters here is that it cannot be reached anywhere it would do harm.
 */
class SandboxGatewayTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.gateway', 'sandbox');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansSeeder::class);

        $this->plan = Plan::where('slug', 'standard')->sole();
        $this->restaurant = Restaurant::factory()->create();
        $this->restaurant->subscription()->create([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->owner = $this->member(Role::RestaurantOwner);
    }

    private function member(Role $role, ?Restaurant $restaurant = null): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        ($restaurant ?? $this->restaurant)->users()->attach($user->id, [
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function startCheckout(string $interval = 'yearly'): array
    {
        return $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => $interval,
            ])
            ->assertOk()
            ->json('data');
    }

    public function test_choosing_a_plan_redirects_to_the_card_page(): void
    {
        $result = $this->startCheckout();

        $this->assertSame('redirect', $result['action']);
        $this->assertStringContainsString('/sandbox-pay?payment=', $result['redirect_url']);
    }

    public function test_paying_activates_the_subscription(): void
    {
        $result = $this->startCheckout('yearly');
        $paymentId = $result['payment_id'];

        // Nothing granted just by reaching the card page.
        $this->assertFalse($this->restaurant->refresh()->subscription->hasAccess());

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/dashboard/sandbox-payments/{$paymentId}/pay")
            ->assertOk();

        $subscription = $this->restaurant->refresh()->subscription;
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->hasAccess());
        $this->assertEqualsWithDelta(365, now()->diffInDays($subscription->current_period_end), 2);

        // And the dashboard opens again.
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk();
    }

    public function test_paying_twice_does_not_grant_two_periods(): void
    {
        $paymentId = $this->startCheckout('monthly')['payment_id'];

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/dashboard/sandbox-payments/{$paymentId}/pay")->assertOk();
        $firstEnd = $this->restaurant->refresh()->subscription->current_period_end;

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/dashboard/sandbox-payments/{$paymentId}/pay")->assertOk();

        $this->assertEquals(
            $firstEnd->timestamp,
            $this->restaurant->refresh()->subscription->current_period_end->timestamp,
        );
    }

    public function test_it_is_unreachable_when_another_gateway_is_configured(): void
    {
        $paymentId = $this->startCheckout()['payment_id'];

        // The page must die the moment a real gateway takes over, or it would
        // be a way to obtain a subscription for nothing.
        config()->set('billing.gateway', 'stripe');

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/dashboard/sandbox-payments/{$paymentId}")
            ->assertNotFound();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/dashboard/sandbox-payments/{$paymentId}/pay")
            ->assertNotFound();
    }

    public function test_it_refuses_to_run_outside_local_development(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])
            ->assertStatus(503);
    }

    public function test_one_restaurant_cannot_pay_against_anothers_payment(): void
    {
        $paymentId = $this->startCheckout()['payment_id'];

        $other = Restaurant::factory()->create();
        $other->subscription()->create([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDay(),
        ]);
        $intruder = $this->member(Role::RestaurantOwner, $other);

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/dashboard/sandbox-payments/{$paymentId}/pay")
            ->assertNotFound();

        $this->assertSame(
            PaymentStatus::Pending,
            $this->restaurant->subscription->payments()->latest('id')->sole()->status,
        );
    }

    public function test_staff_cannot_reach_the_card_page(): void
    {
        $paymentId = $this->startCheckout()['payment_id'];
        $waiter = $this->member(Role::Waiter);

        $this->actingAs($waiter, 'sanctum')
            ->postJson("/api/dashboard/sandbox-payments/{$paymentId}/pay")
            ->assertForbidden();
    }
}
