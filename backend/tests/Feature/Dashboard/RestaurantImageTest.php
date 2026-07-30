<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantImageTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

    public function test_image_upload_requires_authentication(): void
    {
        $this->postJson('/api/dashboard/restaurant/image', [])->assertUnauthorized();
        $this->deleteJson('/api/dashboard/restaurant/image/logo')->assertUnauthorized();
    }

    public function test_owner_can_upload_a_cover_photo(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'cover',
                'image' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
            ])
            ->assertOk();

        $stored = $response->json('data.cover_image');
        $this->assertStringStartsWith('/storage/restaurants/', $stored);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $stored));

        $this->assertSame($stored, $this->restaurant->fresh()->cover_image);
    }

    public function test_owner_can_upload_a_logo(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'logo',
                'image' => UploadedFile::fake()->image('logo.png', 512, 512),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Logo updated.');

        $this->assertNotNull($this->restaurant->fresh()->logo);
    }

    public function test_replacing_an_image_deletes_the_previous_file(): void
    {
        $first = $this->actingAs($this->owner, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'cover',
                'image' => UploadedFile::fake()->image('one.jpg'),
            ])
            ->json('data.cover_image');

        $second = $this->actingAs($this->owner, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'cover',
                'image' => UploadedFile::fake()->image('two.jpg'),
            ])
            ->json('data.cover_image');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing(str_replace('/storage/', '', $first));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $second));
    }

    public function test_seeded_or_external_images_are_not_deleted_on_replace(): void
    {
        // Demo/external covers live outside our disk; replacing one must not
        // attempt (or appear) to delete someone else's file.
        $this->restaurant->update(['cover_image' => 'https://images.example.com/hero.jpg']);

        $this->actingAs($this->owner, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'cover',
                'image' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertOk();

        $this->assertStringStartsWith('/storage/', $this->restaurant->fresh()->cover_image);
    }

    public function test_image_can_be_removed(): void
    {
        $path = $this->actingAs($this->owner, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'cover',
                'image' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->json('data.cover_image');

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/dashboard/restaurant/image/cover')
            ->assertOk()
            ->assertJsonPath('data.cover_image', null);

        Storage::disk('public')->assertMissing(str_replace('/storage/', '', $path));
    }

    public function test_upload_validates_type_and_file(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/restaurant/image', ['type' => 'banner'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'image']);

        $this->actingAs($this->owner, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'logo',
                'image' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_unknown_image_type_404s_on_delete(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/dashboard/restaurant/image/banner')
            ->assertNotFound();
    }

    public function test_staff_without_restaurant_permission_are_forbidden(): void
    {
        $waiter = $this->member(Role::Waiter);

        $this->actingAs($waiter, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'logo',
                'image' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertForbidden();
    }

    public function test_uploaded_cover_reaches_the_public_menu(): void
    {
        $path = $this->actingAs($this->owner, 'sanctum')
            ->post('/api/dashboard/restaurant/image', [
                'type' => 'cover',
                'image' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->json('data.cover_image');

        $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")
            ->assertOk()
            ->assertJsonPath('data.restaurant.cover_image', $path);
    }
}
