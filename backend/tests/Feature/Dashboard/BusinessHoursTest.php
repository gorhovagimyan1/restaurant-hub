<?php

namespace Tests\Feature\Dashboard;

use App\Enums\DayOfWeek;
use App\Enums\Role;
use App\Models\BusinessHour;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
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

    /**
     * A payload where every day is open 09:00–17:00.
     */
    private function openAllWeek(array $overrides = []): array
    {
        $hours = array_map(fn (int $day) => [
            'day_of_week' => $day,
            'is_closed' => false,
            'open_time' => '09:00',
            'close_time' => '17:00',
        ], DayOfWeek::values());

        foreach ($overrides as $day => $override) {
            $hours[$day - 1] = array_merge($hours[$day - 1], $override);
        }

        return ['hours' => $hours];
    }

    public function test_business_hours_require_authentication(): void
    {
        $this->getJson('/api/dashboard/business-hours')->assertUnauthorized();
        $this->putJson('/api/dashboard/business-hours', [])->assertUnauthorized();
    }

    public function test_index_always_returns_seven_days_monday_first(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/business-hours')
            ->assertOk();

        $days = collect($response->json('data'));
        $this->assertCount(7, $days);
        $this->assertSame(DayOfWeek::values(), $days->pluck('day_of_week')->all());
        $this->assertSame('Monday', $days->first()['day_label']);
        // Unset days come back as "not closed, no times".
        $this->assertFalse($days->first()['is_closed']);
        $this->assertNull($days->first()['open_time']);
    }

    public function test_owner_can_set_hours_and_a_closed_day_clears_its_times(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/business-hours', $this->openAllWeek([
                DayOfWeek::Sunday->value => ['is_closed' => true, 'open_time' => '09:00', 'close_time' => '17:00'],
            ]))
            ->assertOk();

        $this->assertDatabaseHas('business_hours', [
            'restaurant_id' => $this->restaurant->id,
            'day_of_week' => DayOfWeek::Monday->value,
            'is_closed' => false,
            'open_time' => '09:00:00',
            'close_time' => '17:00:00',
        ]);

        // A closed day is stored closed with null times, regardless of input.
        $sunday = BusinessHour::where('restaurant_id', $this->restaurant->id)
            ->where('day_of_week', DayOfWeek::Sunday->value)
            ->firstOrFail();
        $this->assertTrue($sunday->is_closed);
        $this->assertNull($sunday->open_time);
    }

    public function test_update_is_idempotent_and_reflected_by_index(): void
    {
        $put = fn () => $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/business-hours', $this->openAllWeek([
                DayOfWeek::Friday->value => ['open_time' => '10:00', 'close_time' => '23:00'],
            ]));

        $put()->assertOk();
        $put()->assertOk();

        // One row per day, not duplicated by the second write.
        $this->assertSame(7, BusinessHour::where('restaurant_id', $this->restaurant->id)->count());

        $friday = collect(
            $this->actingAs($this->owner, 'sanctum')->getJson('/api/dashboard/business-hours')->json('data')
        )->firstWhere('day_of_week', DayOfWeek::Friday->value);
        $this->assertSame('10:00', $friday['open_time']);
        $this->assertSame('23:00', $friday['close_time']);
    }

    public function test_an_open_day_requires_both_times(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/business-hours', $this->openAllWeek([
                DayOfWeek::Monday->value => ['open_time' => null, 'close_time' => null],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hours.0.open_time', 'hours.0.close_time']);
    }

    public function test_update_rejects_bad_days_and_time_formats(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/business-hours', [
                'hours' => [
                    ['day_of_week' => 9, 'is_closed' => false, 'open_time' => '09:00', 'close_time' => '17:00'],
                    ['day_of_week' => 2, 'is_closed' => false, 'open_time' => '9am', 'close_time' => '17:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hours.0.day_of_week', 'hours.1.open_time']);
    }

    public function test_update_rejects_duplicate_days(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/business-hours', [
                'hours' => [
                    ['day_of_week' => 1, 'is_closed' => true],
                    ['day_of_week' => 1, 'is_closed' => true],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hours.0.day_of_week', 'hours.1.day_of_week']);
    }

    public function test_staff_without_manage_permission_are_forbidden(): void
    {
        $waiter = $this->member(Role::Waiter);

        $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/dashboard/business-hours')
            ->assertForbidden();
    }

    public function test_public_menu_exposes_business_hours(): void
    {
        $this->restaurant->businessHours()->create([
            'day_of_week' => DayOfWeek::Monday->value,
            'is_closed' => false,
            'open_time' => '08:30',
            'close_time' => '22:00',
        ]);

        $response = $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")
            ->assertOk();

        $hours = collect($response->json('data.restaurant.business_hours'));
        $monday = $hours->firstWhere('day_of_week', DayOfWeek::Monday->value);
        $this->assertSame('08:30', $monday['open_time']);
        $this->assertSame('22:00', $monday['close_time']);
    }

    public function test_hours_are_scoped_to_the_current_restaurant(): void
    {
        $other = Restaurant::factory()->create();
        $other->businessHours()->create([
            'day_of_week' => DayOfWeek::Monday->value,
            'is_closed' => false,
            'open_time' => '01:00',
            'close_time' => '02:00',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/business-hours', $this->openAllWeek())
            ->assertOk();

        // The other restaurant's Monday is untouched.
        $this->assertDatabaseHas('business_hours', [
            'restaurant_id' => $other->id,
            'day_of_week' => DayOfWeek::Monday->value,
            'open_time' => '01:00:00',
        ]);
        $this->assertSame(1, $other->businessHours()->count());
    }
}
