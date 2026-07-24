<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        $this->putJson('/api/auth/profile', [])->assertUnauthorized();
        $this->putJson('/api/auth/password', [])->assertUnauthorized();
    }

    public function test_user_can_update_their_profile(): void
    {
        $user = $this->actingUser(['first_name' => 'Old', 'last_name' => 'Name']);

        $this->putJson('/api/auth/profile', [
            'first_name' => 'New',
            'last_name' => 'Person',
            'email' => 'new.person@example.test',
            'phone' => '+37411223344',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.first_name', 'New')
            ->assertJsonPath('data.email', 'new.person@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'New',
            'email' => 'new.person@example.test',
            'phone' => '+37411223344',
        ]);
    }

    public function test_updating_profile_to_the_same_email_is_allowed(): void
    {
        $user = $this->actingUser(['email' => 'keep@example.test']);

        $this->putJson('/api/auth/profile', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'keep@example.test',
        ])->assertOk();
    }

    public function test_profile_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        $user = $this->actingUser();

        $this->putJson('/api/auth/profile', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'taken@example.test',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_profile_requires_name_and_email(): void
    {
        $this->actingUser();

        $this->putJson('/api/auth/profile', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
    }

    public function test_user_can_change_their_password(): void
    {
        $user = $this->actingUser();

        $this->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'brand-new-secret-1',
            'password_confirmation' => 'brand-new-secret-1',
        ])->assertOk()->assertJsonPath('success', true);

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-secret-1', $user->password));
    }

    public function test_change_password_rejects_a_wrong_current_password(): void
    {
        $user = $this->actingUser();

        $this->putJson('/api/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-secret-1',
            'password_confirmation' => 'brand-new-secret-1',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $user->refresh();
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_change_password_rejects_reusing_the_current_password(): void
    {
        $this->actingUser();

        $this->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_change_password_requires_confirmation(): void
    {
        $this->actingUser();

        $this->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'brand-new-secret-1',
            'password_confirmation' => 'mismatch',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_change_password_revokes_other_tokens_but_keeps_current(): void
    {
        $user = User::factory()->create();
        // An old session token that should be revoked.
        $user->createToken('old-device');
        // The token representing the current request.
        $current = $user->createToken('current');

        $this->withToken($current->plainTextToken)->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'brand-new-secret-1',
            'password_confirmation' => 'brand-new-secret-1',
        ])->assertOk();

        $tokens = $user->tokens()->pluck('name');
        $this->assertCount(1, $tokens);
        $this->assertSame('current', $tokens->first());
    }
}
