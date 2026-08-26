<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\TableStatus;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\RestaurantSettings;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who gets in, and who is asked to pay.
 */
class SubscriptionAccessTest extends TestCase
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

        $this->restaurant = Restaurant::factory()->create(['is_active' => true]);
        RestaurantSettings::create(['restaurant_id' => $this->restaurant->id]);

        $this->owner = $this->member(Role::RestaurantOwner);
    }

    private function member(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->restaurant->users()->attach($user->id, [
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return $user;
    }

    /** Put the restaurant in a given billing state. */
    private function subscribe(array $attributes): void
    {
        $this->restaurant->subscription()->delete();
        $this->restaurant->subscription()->create($attributes);
    }

    public function test_registration_starts_a_free_trial(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Ani',
            'last_name' => 'Grigoryan',
            'email' => 'new-owner@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'restaurant_name' => 'Brand New Bistro',
        ])->assertCreated();

        $restaurant = Restaurant::where('name', 'Brand New Bistro')->sole();
        $subscription = $restaurant->subscription;

        $this->assertNotNull($subscription, 'Registering should provision a trial.');
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($subscription->hasAccess());
        $this->assertNull($subscription->plan_id, 'A trial is not tied to a plan.');
        $this->assertEqualsWithDelta(
            14,
            now()->diffInDays($subscription->trial_ends_at, absolute: true),
            1,
        );

        // And that owner can immediately work.
        $token = $response->json('data.token');
        $this->withToken($token)->getJson('/api/dashboard/overview')->assertOk();
    }

    public function test_owner_on_an_active_trial_can_use_the_dashboard(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk();
    }

    public function test_expired_trial_blocks_the_dashboard_with_402(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertStatus(402)
            ->assertJsonPath('data.subscription_required', true);

        // Every other working endpoint is shut too, not just the overview.
        foreach (['menu-theme', 'products', 'tables', 'orders', 'settings'] as $path) {
            $this->actingAs($this->owner, 'sanctum')
                ->getJson("/api/dashboard/{$path}")
                ->assertStatus(402);
        }
    }

    public function test_a_lapsed_restaurant_can_still_reach_billing_to_pay(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        // Seeing the plans and starting checkout must survive the lockout —
        // otherwise there is no way back in.
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription.has_access', false)
            ->assertJsonCount(1, 'data.plans');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'instructions');

        // As does identifying the restaurant, which the dashboard chrome needs.
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/restaurant')
            ->assertOk();
    }

    public function test_the_customer_menu_keeps_working_when_the_restaurant_has_not_paid(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Expired,
            'trial_ends_at' => now()->subMonth(),
        ]);

        $table = $this->restaurant->tables()->create([
            'name' => 'Table 1',
            'capacity' => 4,
            'status' => TableStatus::Available,
        ]);
        $qr = $table->qrCode()->create([]);

        // Diners know nothing about their restaurant's billing; service must
        // not stop for them.
        $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")->assertOk();
        $this->getJson("/api/public/tables/{$qr->token}")->assertOk();
        $this->postJson("/api/public/tables/{$qr->token}/session")->assertOk();
    }

    public function test_kitchen_staff_are_locked_out_too(): void
    {
        $kitchen = $this->member(Role::KitchenStaff);

        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($kitchen, 'sanctum')
            ->getJson('/api/dashboard/orders')
            ->assertStatus(402);
    }

    public function test_super_admin_is_never_billed(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin->value);

        $this->subscribe([
            'status' => SubscriptionStatus::Expired,
            'trial_ends_at' => now()->subMonth(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk();
    }

    public function test_restaurants_predating_billing_get_a_trial_rather_than_a_lockout(): void
    {
        // No subscription row at all — the state every existing tenant is in
        // the moment this feature deploys.
        $this->assertNull($this->restaurant->subscription);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk();

        $this->assertSame(
            SubscriptionStatus::Trialing,
            $this->restaurant->refresh()->subscription->status,
        );
    }

    public function test_cancelled_subscription_keeps_access_until_the_period_ends(): void
    {
        $this->subscribe([
            'plan_id' => $this->plan->id,
            'interval' => 'monthly',
            'status' => SubscriptionStatus::Cancelled,
            'current_period_end' => now()->addWeek(),
            'cancelled_at' => now(),
        ]);

        // They paid for this time; cancelling should not claw it back.
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk();
    }

    public function test_cancelled_subscription_blocks_once_the_period_ends(): void
    {
        $this->subscribe([
            'plan_id' => $this->plan->id,
            'interval' => 'monthly',
            'status' => SubscriptionStatus::Cancelled,
            'current_period_end' => now()->subDay(),
            'cancelled_at' => now()->subMonth(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertStatus(402);
    }

    public function test_paying_grants_access_and_sets_the_period(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'yearly',
            ])->assertOk();

        // Still locked out — nothing has been paid yet.
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertStatus(402);

        $payment = $this->restaurant->subscription->payments()->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertEqualsWithDelta(149000.0, (float) $payment->amount, 0.01);

        // A super-admin confirms the transfer.
        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin->value);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/subscription-payments/{$payment->id}/confirm")
            ->assertOk();

        $subscription = $this->restaurant->refresh()->subscription;
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertEqualsWithDelta(365, now()->diffInDays($subscription->current_period_end), 2);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk();
    }

    public function test_renewing_early_extends_from_the_period_end_not_from_today(): void
    {
        $this->subscribe([
            'plan_id' => $this->plan->id,
            'interval' => 'monthly',
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(10),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])->assertOk();

        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin->value);
        $payment = $this->restaurant->subscription->payments()->latest('id')->sole();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/subscription-payments/{$payment->id}/confirm")
            ->assertOk();

        // 10 days remaining + a month, not a month from today.
        $this->assertEqualsWithDelta(
            40,
            now()->diffInDays($this->restaurant->refresh()->subscription->current_period_end),
            2,
            'Paying early must not forfeit days already held.',
        );
    }

    public function test_checkout_supersedes_an_earlier_unpaid_attempt(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDay(),
        ]);

        foreach (['monthly', 'yearly'] as $interval) {
            $this->actingAs($this->owner, 'sanctum')
                ->postJson('/api/dashboard/subscription/checkout', [
                    'plan_id' => $this->plan->id,
                    'interval' => $interval,
                ])->assertOk();
        }

        // The admin queue should show one row per restaurant, not one per click.
        $pending = $this->restaurant->subscription->payments()
            ->where('status', PaymentStatus::Pending)->get();

        $this->assertCount(1, $pending);
        $this->assertSame('yearly', $pending->first()->interval->value);
    }

    public function test_a_payment_cannot_be_confirmed_twice(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])->assertOk();

        $payment = $this->restaurant->subscription->payments()->sole();

        $admin = User::factory()->create();
        $admin->assignRole(Role::SuperAdmin->value);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/subscription-payments/{$payment->id}/confirm")
            ->assertOk();

        // Replaying it must not hand out another period for free.
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/subscription-payments/{$payment->id}/confirm")
            ->assertStatus(422);
    }

    public function test_the_admin_queue_lists_pending_then_confirmed_payments(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])->assertOk();

        $admin = User::factory()->create(['first_name' => 'Super', 'last_name' => 'Admin']);
        $admin->assignRole(Role::SuperAdmin->value);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/subscription-payments')
            ->assertOk()
            ->assertJsonCount(1, 'data.pending')
            ->assertJsonCount(0, 'data.recently_confirmed')
            ->assertJsonPath('data.pending.0.restaurant.name', $this->restaurant->name)
            ->assertJsonPath('data.pending.0.interval', 'monthly');

        $payment = $this->restaurant->subscription->payments()->sole();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/subscription-payments/{$payment->id}/confirm")
            ->assertOk();

        // It moves out of the queue and into the confirmed list, so the admin
        // can see the action landed rather than the row simply vanishing.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/subscription-payments')
            ->assertOk()
            ->assertJsonCount(0, 'data.pending')
            ->assertJsonCount(1, 'data.recently_confirmed')
            ->assertJsonPath('data.recently_confirmed.0.confirmed_by', 'Super Admin')
            ->assertJsonPath('data.recently_confirmed.0.restaurant.name', $this->restaurant->name);
    }

    public function test_the_admin_queue_is_super_admin_only(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/admin/subscription-payments')
            ->assertForbidden();
    }

    public function test_only_super_admins_can_confirm_payments(): void
    {
        $this->subscribe([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDay(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'interval' => 'monthly',
            ])->assertOk();

        $payment = $this->restaurant->subscription->payments()->sole();

        // An owner marking their own payment paid would be free service.
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/admin/subscription-payments/{$payment->id}/confirm")
            ->assertForbidden();
    }

    public function test_checkout_rejects_an_unknown_plan_or_interval(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/checkout', [
                'plan_id' => 99999,
                'interval' => 'weekly',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id', 'interval']);
    }

    public function test_owner_can_cancel_and_keeps_paid_time(): void
    {
        $this->subscribe([
            'plan_id' => $this->plan->id,
            'interval' => 'monthly',
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(20),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/subscription/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Cancelled->value)
            ->assertJsonPath('data.has_access', true);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk();
    }
}
