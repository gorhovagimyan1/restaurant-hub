<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'restaurant_name' => "Ada's Bistro",
            'password' => 'super-secret-1',
            'password_confirmation' => 'super-secret-1',
        ], $overrides);
    }

    public function test_registration_provisions_an_owner_with_their_restaurant(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registrationPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'restaurant', 'token']])
            ->assertJsonPath('data.restaurant.name', "Ada's Bistro");

        $this->assertContains(Role::RestaurantOwner->value, $response->json('data.user.roles'));

        $user = User::where('email', 'ada@example.test')->firstOrFail();
        $restaurant = Restaurant::where('name', "Ada's Bistro")->firstOrFail();

        // Restaurant is active with a slug, default settings, and the owner is
        // a member of it.
        $this->assertTrue($restaurant->is_active);
        $this->assertNotEmpty($restaurant->slug);
        $this->assertDatabaseHas('restaurant_settings', ['restaurant_id' => $restaurant->id]);
        $this->assertDatabaseHas('restaurant_user', [
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_registration_generates_unique_slugs_for_duplicate_names(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload([
            'email' => 'one@example.test',
            'restaurant_name' => 'Corner Cafe',
        ]))->assertCreated();

        $this->postJson('/api/auth/register', $this->registrationPayload([
            'email' => 'two@example.test',
            'restaurant_name' => 'Corner Cafe',
        ]))->assertCreated();

        $slugs = Restaurant::where('name', 'Corner Cafe')->pluck('slug');
        $this->assertCount(2, $slugs);
        $this->assertSame($slugs->count(), $slugs->unique()->count());
    }

    public function test_a_freshly_registered_owner_can_reach_their_dashboard(): void
    {
        $token = $this->postJson('/api/auth/register', $this->registrationPayload())
            ->json('data.token');

        // The tenant resolves immediately — no 422 "no restaurant" error.
        $this->withToken($token)
            ->getJson('/api/dashboard/restaurant')
            ->assertOk()
            ->assertJsonPath('data.name', "Ada's Bistro");
    }

    public function test_registration_requires_a_restaurant_name(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload(['restaurant_name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('restaurant_name');
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload([
            'password_confirmation' => 'mismatch',
        ]))->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_failed_registration_creates_nothing(): void
    {
        // A duplicate email fails validation; no partial tenant should remain.
        User::factory()->create(['email' => 'taken@example.test']);

        $this->postJson('/api/auth/register', $this->registrationPayload([
            'email' => 'taken@example.test',
            'restaurant_name' => 'Ghost Kitchen',
        ]))->assertStatus(422);

        $this->assertDatabaseMissing('restaurants', ['name' => 'Ghost Kitchen']);
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);

        $this->postJson('/api/auth/login', [
            'email' => 'owner@example.test',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'owner@example.test']);

        $this->postJson('/api/auth/login', [
            'email' => 'owner@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_is_forbidden_for_disabled_accounts(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.test',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'disabled@example.test',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
