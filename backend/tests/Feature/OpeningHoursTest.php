<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningHoursTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'is_active' => true,
            'timezone' => 'Asia/Yerevan', // UTC+4
        ]);
    }

    /**
     * Add a weekly business-hours row for an ISO weekday (1 = Mon … 7 = Sun).
     */
    private function weekday(int $dow, ?string $open, ?string $close, bool $closed = false): void
    {
        $this->restaurant->businessHours()->create([
            'day_of_week' => $dow,
            'is_closed' => $closed,
            'open_time' => $open,
            'close_time' => $close,
        ]);
    }

    private function statusAt(string $localDateTime): array
    {
        // Freeze "now" at a wall-clock time in the restaurant's timezone.
        $this->travelTo(Carbon::parse($localDateTime, 'Asia/Yerevan'));

        return $this->restaurant->openStatus();
    }

    public function test_open_during_weekday_hours(): void
    {
        // 2026-07-15 is a Wednesday (ISO 3).
        $this->weekday(3, '09:00', '17:00');

        $status = $this->statusAt('2026-07-15 12:00');
        $this->assertTrue($status['open']);
        $this->assertSame('17:00', $status['closes_at']);
        $this->assertFalse($status['today']['is_closed']);
        $this->assertSame('09:00', $status['today']['open_time']);
    }

    public function test_closed_before_opening_and_after_closing(): void
    {
        $this->weekday(3, '09:00', '17:00');

        $this->assertFalse($this->statusAt('2026-07-15 08:30')['open']);
        $this->assertFalse($this->statusAt('2026-07-15 17:00')['open']); // close is exclusive
        $this->assertFalse($this->statusAt('2026-07-15 19:00')['open']);
    }

    public function test_a_closed_weekday_is_closed(): void
    {
        $this->weekday(3, null, null, closed: true);

        $status = $this->statusAt('2026-07-15 12:00');
        $this->assertFalse($status['open']);
        $this->assertTrue($status['today']['is_closed']);
    }

    public function test_a_weekday_with_no_row_is_closed(): void
    {
        // No hours defined at all.
        $status = $this->statusAt('2026-07-15 12:00');
        $this->assertFalse($status['open']);
        $this->assertTrue($status['today']['is_closed']);
    }

    public function test_overnight_span_stays_open_past_midnight(): void
    {
        // Wednesday 18:00 → 02:00 (crosses into Thursday).
        $this->weekday(3, '18:00', '02:00');

        // Wednesday night — open under Wednesday's own span.
        $this->assertTrue($this->statusAt('2026-07-15 23:00')['open']);

        // Thursday 01:00 — still open thanks to Wednesday's overnight spill.
        $spill = $this->statusAt('2026-07-16 01:00');
        $this->assertTrue($spill['open']);
        $this->assertSame('02:00', $spill['closes_at']);

        // Thursday 03:00 — closed (Thursday has no hours of its own).
        $this->assertFalse($this->statusAt('2026-07-16 03:00')['open']);
    }

    public function test_special_closure_overrides_open_weekday(): void
    {
        $this->weekday(3, '09:00', '17:00');
        $this->restaurant->specialHours()->create([
            'date' => '2026-07-15',
            'is_closed' => true,
            'label' => 'Public Holiday',
        ]);

        $status = $this->statusAt('2026-07-15 12:00');
        $this->assertFalse($status['open']);
        $this->assertTrue($status['today']['is_closed']);
        $this->assertTrue($status['today']['is_special']);
        $this->assertSame('Public Holiday', $status['today']['label']);
    }

    public function test_special_hours_override_weekday_hours(): void
    {
        $this->weekday(3, '09:00', '17:00');
        $this->restaurant->specialHours()->create([
            'date' => '2026-07-15',
            'is_closed' => false,
            'open_time' => '12:00',
            'close_time' => '15:00',
            'label' => 'Reduced hours',
        ]);

        // 10:00 is within normal hours but before the special opening.
        $this->assertFalse($this->statusAt('2026-07-15 10:00')['open']);
        // 13:00 is within the special window.
        $open = $this->statusAt('2026-07-15 13:00');
        $this->assertTrue($open['open']);
        $this->assertSame('15:00', $open['closes_at']);
        $this->assertTrue($open['today']['is_special']);
    }

    public function test_status_is_computed_in_the_restaurant_timezone(): void
    {
        $this->weekday(3, '09:00', '17:00');

        // 06:00 UTC == 10:00 in Yerevan (UTC+4) → open.
        $this->travelTo(Carbon::parse('2026-07-15 06:00', 'UTC'));
        $this->assertTrue($this->restaurant->openStatus()['open']);

        // 18:00 UTC == 22:00 in Yerevan → closed.
        $this->travelTo(Carbon::parse('2026-07-15 18:00', 'UTC'));
        $this->assertFalse($this->restaurant->openStatus()['open']);
    }

    public function test_public_menu_includes_open_status(): void
    {
        $this->weekday(3, '09:00', '17:00');
        $this->travelTo(Carbon::parse('2026-07-15 12:00', 'Asia/Yerevan'));

        $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")
            ->assertOk()
            ->assertJsonPath('data.restaurant.open_status.open', true)
            ->assertJsonPath('data.restaurant.open_status.timezone', 'Asia/Yerevan');
    }

    public function test_status_endpoint_returns_open_state(): void
    {
        $this->weekday(3, '09:00', '17:00');
        $this->travelTo(Carbon::parse('2026-07-15 20:00', 'Asia/Yerevan'));

        $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/status")
            ->assertOk()
            ->assertJsonPath('data.open', false)
            ->assertJsonPath('data.today.close_time', '17:00');
    }

    public function test_status_endpoint_404s_for_inactive_restaurant(): void
    {
        $this->restaurant->update(['is_active' => false]);

        $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/status")
            ->assertNotFound();
    }
}
