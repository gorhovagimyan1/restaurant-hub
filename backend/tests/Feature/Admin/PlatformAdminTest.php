<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole(Role::SuperAdmin->value);
    }

    /**
     * An owner attached to their own restaurant — should be barred from the
     * platform area despite holding every permission for their tenant.
     */
    private function owner(): User
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create();
        $owner->assignRole(Role::RestaurantOwner->value);
        $restaurant->users()->attach($owner->id, ['is_active' => true, 'joined_at' => now()]);

        return $owner;
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/admin/overview')->assertUnauthorized();
    }

    public function test_restaurant_owner_is_forbidden_from_platform_area(): void
    {
        $this->actingAs($this->owner(), 'sanctum');

        $this->getJson('/api/admin/overview')->assertForbidden();
        $this->getJson('/api/admin/restaurants')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_super_admin_can_read_platform_overview(): void
    {
        Restaurant::factory()->count(3)->create();

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.restaurants.total', 3)
            ->assertJsonStructure([
                'data' => [
                    'restaurants' => ['total', 'active', 'pending', 'suspended'],
                    'users' => ['total', 'active'],
                    'orders' => ['today', 'completed_today', 'revenue_today', 'total'],
                    'recent_restaurants',
                ],
            ]);
    }

    public function test_super_admin_can_list_every_restaurant(): void
    {
        Restaurant::factory()->count(2)->create();

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/restaurants')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_super_admin_can_search_restaurants(): void
    {
        Restaurant::factory()->create(['name' => 'Bella Pasta']);
        Restaurant::factory()->create(['name' => 'Sushi World']);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/restaurants?search=Bella')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bella Pasta');
    }

    public function test_super_admin_can_suspend_a_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create([
            'status' => RestaurantStatus::Active->value,
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/restaurants/{$restaurant->uuid}/status", [
            'status' => RestaurantStatus::Suspended->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', RestaurantStatus::Suspended->value)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'status' => RestaurantStatus::Suspended->value,
            'is_active' => false,
        ]);
    }

    public function test_status_update_rejects_an_unknown_value(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/restaurants/{$restaurant->uuid}/status", [
            'status' => 'exploded',
        ])->assertStatus(422);
    }

    public function test_super_admin_can_soft_delete_a_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->deleteJson("/api/admin/restaurants/{$restaurant->uuid}")->assertOk();

        $this->assertSoftDeleted('restaurants', ['id' => $restaurant->id]);
    }

    public function test_super_admin_can_list_and_deactivate_users(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::Waiter->value);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/users')->assertOk();

        $this->patchJson("/api/admin/users/{$user->uuid}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_super_admin_cannot_deactivate_their_own_account(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/users/{$this->superAdmin->uuid}/status", ['is_active' => false])
            ->assertForbidden();
    }
}
