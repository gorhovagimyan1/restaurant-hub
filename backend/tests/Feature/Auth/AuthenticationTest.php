<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
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

    public function test_registration_creates_a_restaurant_owner_and_issues_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'password' => 'super-secret-1',
            'password_confirmation' => 'super-secret-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertContains(Role::RestaurantOwner->value, $response->json('data.user.roles'));
        $this->assertDatabaseHas('users', ['email' => 'ada@example.test']);
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'password' => 'super-secret-1',
            'password_confirmation' => 'mismatch',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
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
