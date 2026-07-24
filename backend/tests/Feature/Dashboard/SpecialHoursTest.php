<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\SpecialHour;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialHoursTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->restaurant = Restaurant::factory()->create(['is_active' => true]);
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

    public function test_special_hours_require_authentication(): void
    {
        $this->getJson('/api/dashboard/special-hours')->assertUnauthorized();
        $this->putJson('/api/dashboard/special-hours', [])->assertUnauthorized();
    }

    public function test_owner_can_set_special_days(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/special-hours', [
                'special_hours' => [
                    ['date' => '2026-12-25', 'is_closed' => true, 'label' => 'Christmas Day'],
                    [
                        'date' => '2026-12-31',
                        'is_closed' => false,
                        'open_time' => '10:00',
                        'close_time' => '15:00',
                        'label' => "New Year's Eve",
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Ordered by date.
            ->assertJsonPath('data.0.date', '2026-12-25')
            ->assertJsonPath('data.0.is_closed', true)
            ->assertJsonPath('data.0.label', 'Christmas Day')
            ->assertJsonPath('data.1.date', '2026-12-31')
            ->assertJsonPath('data.1.open_time', '10:00');

        $this->assertDatabaseHas('special_hours', [
            'restaurant_id' => $this->restaurant->id,
            'date' => '2026-12-25',
            'is_closed' => true,
        ]);
    }

    public function test_a_closed_special_day_clears_its_times(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/special-hours', [
                'special_hours' => [
                    ['date' => '2026-12-25', 'is_closed' => true, 'open_time' => '10:00', 'close_time' => '15:00'],
                ],
            ])
            ->assertOk();

        $day = SpecialHour::where('restaurant_id', $this->restaurant->id)->firstOrFail();
        $this->assertTrue($day->is_closed);
        $this->assertNull($day->open_time);
        $this->assertNull($day->close_time);
    }

    public function test_update_replaces_the_whole_set(): void
    {
        $this->restaurant->specialHours()->create(['date' => '2026-01-01', 'is_closed' => true]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/special-hours', [
                'special_hours' => [
                    ['date' => '2026-12-25', 'is_closed' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseMissing('special_hours', [
            'restaurant_id' => $this->restaurant->id,
            'date' => '2026-01-01',
        ]);
        $this->assertSame(1, $this->restaurant->specialHours()->count());
    }

    public function test_empty_payload_clears_all_special_days(): void
    {
        $this->restaurant->specialHours()->create(['date' => '2026-12-25', 'is_closed' => true]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/special-hours', ['special_hours' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(0, $this->restaurant->specialHours()->count());
    }

    public function test_an_open_special_day_requires_both_times(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/special-hours', [
                'special_hours' => [
                    ['date' => '2026-12-31', 'is_closed' => false, 'open_time' => '10:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['special_hours.0.close_time']);
    }

    public function test_update_rejects_bad_dates_and_duplicates(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/special-hours', [
                'special_hours' => [
                    ['date' => '25-12-2026', 'is_closed' => true],
                    ['date' => '2026-12-25', 'is_closed' => true],
                    ['date' => '2026-12-25', 'is_closed' => true],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'special_hours.0.date',
                'special_hours.1.date',
                'special_hours.2.date',
            ]);
    }

    public function test_staff_without_manage_permission_are_forbidden(): void
    {
        $waiter = $this->member(Role::Waiter);

        $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/dashboard/special-hours')
            ->assertForbidden();
    }

    public function test_public_menu_exposes_only_upcoming_special_days(): void
    {
        $this->restaurant->specialHours()->createMany([
            ['date' => now()->subDays(5)->toDateString(), 'is_closed' => true, 'label' => 'Past'],
            ['date' => now()->addDays(5)->toDateString(), 'is_closed' => true, 'label' => 'Upcoming'],
        ]);

        $response = $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")
            ->assertOk();

        $labels = collect($response->json('data.restaurant.special_hours'))->pluck('label');
        $this->assertTrue($labels->contains('Upcoming'));
        $this->assertFalse($labels->contains('Past'));
    }

    public function test_special_hours_are_scoped_to_the_current_restaurant(): void
    {
        $other = Restaurant::factory()->create();
        $other->specialHours()->create(['date' => '2026-12-25', 'is_closed' => true, 'label' => 'Other']);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/special-hours', [
                'special_hours' => [['date' => '2026-12-25', 'is_closed' => true]],
            ])
            ->assertOk();

        // The other restaurant's override is untouched by our replace.
        $this->assertDatabaseHas('special_hours', [
            'restaurant_id' => $other->id,
            'label' => 'Other',
        ]);
        $this->assertSame(1, $other->specialHours()->count());
    }
}
