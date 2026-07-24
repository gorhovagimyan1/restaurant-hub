<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->restaurant = Restaurant::factory()->create(['is_active' => true]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole(Role::RestaurantOwner->value);
        $this->restaurant->users()->attach($this->owner->id, [
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    private function actingAsOwner(): self
    {
        $this->actingAs($this->owner, 'sanctum');

        return $this;
    }

    /**
     * Attach a staff member with the given role to the restaurant.
     */
    private function staff(Role $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);
        $this->restaurant->users()->attach($user->id, [
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return $user;
    }

    public function test_employee_endpoints_require_authentication(): void
    {
        $this->getJson('/api/dashboard/employees')->assertUnauthorized();
    }

    public function test_staff_without_manage_permission_are_forbidden(): void
    {
        $waiter = $this->staff(Role::Waiter);
        $this->actingAs($waiter, 'sanctum');

        $this->getJson('/api/dashboard/employees')->assertForbidden();
    }

    public function test_owner_can_list_restaurant_staff(): void
    {
        $this->staff(Role::Waiter);
        $this->staff(Role::KitchenStaff);

        // Staff of another restaurant must not leak in.
        $other = Restaurant::factory()->create();
        $stranger = User::factory()->create();
        $stranger->assignRole(Role::Waiter->value);
        $other->users()->attach($stranger->id, ['is_active' => true, 'joined_at' => now()]);

        $response = $this->actingAsOwner()->getJson('/api/dashboard/employees')->assertOk();

        // Owner + 2 staff = 3, but not the stranger.
        $this->assertCount(3, $response->json('data'));
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertFalse($emails->contains($stranger->email));
    }

    public function test_owner_can_invite_an_employee_and_a_setup_email_is_sent(): void
    {
        Notification::fake();

        $response = $this->actingAsOwner()->postJson('/api/dashboard/employees', [
            'first_name' => 'Nora',
            'last_name' => 'Baker',
            'email' => 'nora@example.test',
            'role' => Role::KitchenStaff->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'nora@example.test')
            ->assertJsonPath('data.role', Role::KitchenStaff->value)
            ->assertJsonPath('data.is_active', true);

        $employee = User::where('email', 'nora@example.test')->firstOrFail();
        $this->assertTrue($employee->hasRole(Role::KitchenStaff->value));
        $this->assertDatabaseHas('restaurant_user', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $employee->id,
        ]);

        Notification::assertSentTo($employee, ResetPasswordNotification::class);
    }

    public function test_invite_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.test']);

        $this->actingAsOwner()->postJson('/api/dashboard/employees', [
            'first_name' => 'Nora',
            'last_name' => 'Baker',
            'email' => 'dupe@example.test',
            'role' => Role::Waiter->value,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_invite_rejects_a_non_assignable_role(): void
    {
        $this->actingAsOwner()->postJson('/api/dashboard/employees', [
            'first_name' => 'Nora',
            'last_name' => 'Baker',
            'email' => 'nora@example.test',
            'role' => Role::RestaurantOwner->value,
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_owner_can_change_an_employee_role(): void
    {
        $employee = $this->staff(Role::Waiter);

        $this->actingAsOwner()->putJson("/api/dashboard/employees/{$employee->uuid}", [
            'role' => Role::RestaurantManager->value,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.role', Role::RestaurantManager->value);

        $employee->refresh();
        $this->assertTrue($employee->hasRole(Role::RestaurantManager->value));
        $this->assertFalse($employee->hasRole(Role::Waiter->value));
    }

    public function test_deactivating_an_employee_revokes_their_tokens(): void
    {
        $employee = $this->staff(Role::Waiter);
        $employee->createToken('device');
        $this->assertSame(1, $employee->tokens()->count());

        $this->actingAsOwner()->putJson("/api/dashboard/employees/{$employee->uuid}", [
            'role' => Role::Waiter->value,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $employee->refresh();
        $this->assertFalse($employee->is_active);
        $this->assertSame(0, $employee->tokens()->count());
        $this->assertDatabaseHas('restaurant_user', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $employee->id,
            'is_active' => false,
        ]);
    }

    public function test_owner_cannot_manage_themselves(): void
    {
        $this->actingAsOwner()->putJson("/api/dashboard/employees/{$this->owner->uuid}", [
            'role' => Role::Waiter->value,
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_owner_cannot_manage_another_owner(): void
    {
        $coOwner = $this->staff(Role::RestaurantOwner);

        $this->actingAsOwner()->deleteJson("/api/dashboard/employees/{$coOwner->uuid}")
            ->assertForbidden();
    }

    public function test_cannot_manage_staff_from_another_restaurant(): void
    {
        $other = Restaurant::factory()->create();
        $stranger = User::factory()->create();
        $stranger->assignRole(Role::Waiter->value);
        $other->users()->attach($stranger->id, ['is_active' => true, 'joined_at' => now()]);

        $this->actingAsOwner()->putJson("/api/dashboard/employees/{$stranger->uuid}", [
            'role' => Role::Waiter->value,
            'is_active' => true,
        ])->assertNotFound();
    }

    public function test_owner_can_remove_an_employee(): void
    {
        $employee = $this->staff(Role::Waiter);
        $employee->createToken('device');

        $this->actingAsOwner()->deleteJson("/api/dashboard/employees/{$employee->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('restaurant_user', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $employee->id,
        ]);
        // Orphaned account is soft-deleted and its tokens revoked.
        $this->assertSoftDeleted('users', ['id' => $employee->id]);
        $this->assertSame(0, $employee->tokens()->count());
    }

    public function test_removing_an_employee_keeps_the_account_if_it_serves_another_restaurant(): void
    {
        $employee = $this->staff(Role::Waiter);
        // Also a member of a second restaurant.
        $second = Restaurant::factory()->create();
        $second->users()->attach($employee->id, ['is_active' => true, 'joined_at' => now()]);

        $this->actingAsOwner()->deleteJson("/api/dashboard/employees/{$employee->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('restaurant_user', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $employee->id,
        ]);
        $this->assertNotSoftDeleted('users', ['id' => $employee->id]);
    }
}
