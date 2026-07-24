<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\RestaurantSettings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->restaurant = Restaurant::factory()->create();
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

    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'default_language' => 'hy',
            'tax_percentage' => 10,
            'service_charge' => 5,
            'allow_guest_orders' => true,
            'require_table_selection' => false,
            'enable_waiter_call' => true,
            'enable_bill_request' => true,
            'auto_accept_orders' => false,
        ], $overrides);
    }

    public function test_settings_require_authentication(): void
    {
        $this->getJson('/api/dashboard/settings')->assertUnauthorized();
        $this->putJson('/api/dashboard/settings', [])->assertUnauthorized();
    }

    public function test_show_creates_default_settings_when_missing(): void
    {
        $this->assertDatabaseMissing('restaurant_settings', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/settings')
            ->assertOk()
            ->assertJsonPath('data.allow_guest_orders', true);

        $this->assertDatabaseHas('restaurant_settings', [
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_owner_can_update_settings(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/settings', $this->validSettings([
                'tax_percentage' => 12.5,
                'allow_guest_orders' => false,
                'auto_accept_orders' => true,
            ]))
            ->assertOk()
            ->assertJsonPath('data.tax_percentage', 12.5)
            ->assertJsonPath('data.allow_guest_orders', false)
            ->assertJsonPath('data.auto_accept_orders', true);

        $this->assertDatabaseHas('restaurant_settings', [
            'restaurant_id' => $this->restaurant->id,
            'tax_percentage' => 12.50,
            'allow_guest_orders' => false,
            'auto_accept_orders' => true,
        ]);
    }

    public function test_update_validates_ranges_and_booleans(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/settings', $this->validSettings([
                'tax_percentage' => 150,
                'service_charge' => -1,
                'allow_guest_orders' => 'maybe',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_percentage', 'service_charge', 'allow_guest_orders']);
    }

    public function test_staff_without_settings_permission_are_forbidden(): void
    {
        // Managers deliberately lack settings.manage.
        $manager = $this->member(Role::RestaurantManager);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/dashboard/settings')
            ->assertForbidden();

        $this->actingAs($manager, 'sanctum')
            ->putJson('/api/dashboard/settings', $this->validSettings())
            ->assertForbidden();
    }

    public function test_settings_are_scoped_to_the_current_restaurant(): void
    {
        $other = Restaurant::factory()->create();
        RestaurantSettings::create([
            'restaurant_id' => $other->id,
            'tax_percentage' => 99,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/settings', $this->validSettings(['tax_percentage' => 8]))
            ->assertOk();

        // The other restaurant's settings are untouched.
        $this->assertDatabaseHas('restaurant_settings', [
            'restaurant_id' => $other->id,
            'tax_percentage' => 99.00,
        ]);
    }
}
