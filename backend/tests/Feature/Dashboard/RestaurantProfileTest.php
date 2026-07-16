<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantProfileTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->restaurant = Restaurant::factory()->create([
            'name' => 'The Golden Fork',
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
        ]);

        $this->owner = $this->member(Role::RestaurantOwner);
    }

    /**
     * Create a user with the given role attached to the restaurant.
     */
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

    private function validProfile(array $overrides = []): array
    {
        return array_merge([
            'name' => 'The Golden Fork',
            'description' => 'Fine dining.',
            'phone' => '+37411000000',
            'email' => 'hello@goldenfork.test',
            'website' => 'https://goldenfork.test',
            'address' => '1 Main St',
            'city' => 'Yerevan',
            'country' => 'Armenia',
            'postal_code' => '0001',
            'currency' => 'AMD',
            'timezone' => 'Asia/Yerevan',
        ], $overrides);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/restaurant')->assertUnauthorized();
        $this->putJson('/api/dashboard/restaurant', [])->assertUnauthorized();
    }

    public function test_owner_can_read_the_profile_with_all_editable_fields(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/restaurant')
            ->assertOk()
            ->assertJsonPath('data.name', 'The Golden Fork')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'slug', 'timezone', 'currency', 'postal_code', 'status'],
            ]);
    }

    public function test_owner_can_update_the_profile(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/restaurant', $this->validProfile([
                'name' => 'Golden Fork Bistro',
                'city' => 'Gyumri',
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Golden Fork Bistro')
            ->assertJsonPath('data.city', 'Gyumri');

        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'name' => 'Golden Fork Bistro',
            'city' => 'Gyumri',
        ]);
    }

    public function test_update_validates_required_and_typed_fields(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/restaurant', $this->validProfile([
                'name' => '',
                'email' => 'not-an-email',
                'website' => 'not-a-url',
                'currency' => 'ARMENIANDRAM',
                'timezone' => 'Middle/Earth',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'website', 'currency', 'timezone']);
    }

    public function test_staff_without_manage_permission_cannot_update_the_profile(): void
    {
        $waiter = $this->member(Role::Waiter);

        $this->actingAs($waiter, 'sanctum')
            ->putJson('/api/dashboard/restaurant', $this->validProfile())
            ->assertForbidden();
    }

    public function test_slug_and_status_are_not_mutable_via_the_profile(): void
    {
        $originalSlug = $this->restaurant->slug;

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/restaurant', $this->validProfile([
                'slug' => 'hacked-slug',
                'status' => 'suspended',
                'is_active' => false,
            ]))
            ->assertOk();

        $this->restaurant->refresh();
        $this->assertSame($originalSlug, $this->restaurant->slug);
        $this->assertTrue($this->restaurant->is_active);
    }
}
