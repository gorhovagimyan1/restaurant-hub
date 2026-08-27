<?php

namespace Tests\Feature\Billing;

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
 * How the billing screen behaves while the gateway is half-configured — the
 * state every operator passes through when switching providers.
 */
class GatewayConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlansSeeder::class);

        $this->plan = Plan::where('slug', 'standard')->sole();

        $restaurant = Restaurant::factory()->create();
        $restaurant->subscription()->create([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole(Role::RestaurantOwner->value);
        $restaurant->users()->attach($this->owner->id, [
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    public function test_plans_are_still_visible_when_the_gateway_is_misconfigured(): void
    {
        // Switched to Stripe, keys not pasted in yet.
        config()->set('billing.gateway', 'stripe');
        config()->set('billing.stripe.secret', '');

        // Seeing what things cost must not depend on being able to pay.
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/subscription')
            ->assertOk()
            ->assertJsonCount(1, 'data.plans');
    }

    public function test_paying_with_a_misconfigured_gateway_fails_politely(): void
    {
        config()->set('billing.gateway', 'stripe');
        config()->set('billing.stripe.secret', '');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])
            ->assertStatus(503)
            // The owner gets a plain message, not our configuration troubles.
            ->assertJsonPath('message', 'Payments are not available right now. Please try again shortly.');
    }

    public function test_an_unknown_gateway_name_is_reported_not_silently_ignored(): void
    {
        config()->set('billing.gateway', 'paypal');

        // Falling back to "manual" here would quietly give away subscriptions.
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])
            ->assertStatus(503);
    }
}
