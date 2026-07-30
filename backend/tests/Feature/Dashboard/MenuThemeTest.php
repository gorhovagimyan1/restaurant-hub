<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\RestaurantSettings;
use App\Models\User;
use App\Support\MenuTheme;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuThemeTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validTheme(array $overrides = []): array
    {
        return array_merge(MenuTheme::defaults(), $overrides);
    }

    /**
     * What the editor sends when a preset is picked: the preset's name *and*
     * all of its design values.
     *
     * @return array<string, mixed>
     */
    private function presetTheme(string $preset): array
    {
        return ['preset' => $preset] + MenuTheme::presetValues($preset);
    }

    public function test_menu_theme_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/menu-theme')->assertUnauthorized();
        $this->putJson('/api/dashboard/menu-theme', [])->assertUnauthorized();
    }

    public function test_show_returns_the_default_theme_and_presets(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/menu-theme')
            ->assertOk()
            ->assertJsonPath('data.theme.preset', 'classic')
            ->assertJsonPath('data.theme.primary_color', '#10b981')
            ->assertJsonPath('data.theme.layout', 'list')
            ->assertJsonPath('data.presets.dark.label', 'Dark');
    }

    public function test_owner_can_save_a_custom_theme(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/menu-theme', $this->validTheme([
                'preset' => 'custom',
                'primary_color' => '#8C1C3A',
                'heading_font' => 'playfair',
                'radius' => 4,
                'layout' => 'grid',
                'show_images' => false,
            ]))
            ->assertOk()
            ->assertJsonPath('data.theme.preset', 'custom')
            // Hex values are stored lowercase so the portal and editor agree.
            ->assertJsonPath('data.theme.primary_color', '#8c1c3a')
            ->assertJsonPath('data.theme.heading_font', 'playfair')
            ->assertJsonPath('data.theme.radius', 4)
            ->assertJsonPath('data.theme.layout', 'grid')
            ->assertJsonPath('data.theme.show_images', false);

        $stored = RestaurantSettings::where('restaurant_id', $this->restaurant->id)->sole();
        $this->assertSame('#8c1c3a', $stored->menu_theme['primary_color']);
    }

    public function test_update_rejects_unknown_fonts_colours_and_layouts(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/menu-theme', $this->validTheme([
                'primary_color' => 'red',
                'heading_font' => 'comic-sans',
                'layout' => 'carousel',
                'radius' => 999,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['primary_color', 'heading_font', 'layout', 'radius']);
    }

    public function test_theme_can_be_reset_to_the_default(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/menu-theme', $this->presetTheme('dark'))
            ->assertOk()
            ->assertJsonPath('data.theme.preset', 'dark')
            ->assertJsonPath('data.theme.card_color', '#1a1f26');

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson('/api/dashboard/menu-theme')
            ->assertOk()
            ->assertJsonPath('data.theme.preset', 'classic');

        $stored = RestaurantSettings::where('restaurant_id', $this->restaurant->id)->sole();
        $this->assertNull($stored->menu_theme);
    }

    public function test_staff_without_restaurant_permission_are_forbidden(): void
    {
        $waiter = $this->member(Role::Waiter);

        $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/dashboard/menu-theme')
            ->assertForbidden();

        $this->actingAs($waiter, 'sanctum')
            ->putJson('/api/dashboard/menu-theme', $this->validTheme())
            ->assertForbidden();
    }

    public function test_public_menu_carries_the_restaurant_theme(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/menu-theme', $this->presetTheme('dark'))
            ->assertOk();

        $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")
            ->assertOk()
            ->assertJsonPath('data.restaurant.menu_theme.preset', 'dark')
            ->assertJsonPath('data.restaurant.menu_theme.card_color', '#1a1f26');
    }

    public function test_public_menu_falls_back_to_the_default_theme(): void
    {
        $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")
            ->assertOk()
            ->assertJsonPath('data.restaurant.menu_theme.preset', 'classic')
            ->assertJsonPath('data.restaurant.menu_theme.primary_color', '#10b981');
    }

    public function test_partial_stored_theme_is_filled_out_from_its_preset(): void
    {
        // Rows written by an older client (or by hand) still render in full.
        RestaurantSettings::create([
            'restaurant_id' => $this->restaurant->id,
            'menu_theme' => ['preset' => 'elegant', 'radius' => 20],
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/menu-theme')
            ->assertOk()
            ->assertJsonPath('data.theme.preset', 'elegant')
            ->assertJsonPath('data.theme.radius', 20)
            ->assertJsonPath('data.theme.body_font', 'lora')
            ->assertJsonPath('data.theme.hero_style', 'gradient');
    }
}
