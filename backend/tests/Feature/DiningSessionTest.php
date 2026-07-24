<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\DiningSession;
use App\Models\Menu;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSettings;
use App\Models\RestaurantTable;
use App\Models\TableQrCode;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiningSessionTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantTable $table;

    private TableQrCode $qr;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['is_active' => true]);

        RestaurantSettings::create([
            'restaurant_id' => $this->restaurant->id,
            'currency' => 'AMD',
            'tax_percentage' => 0,
            'service_charge' => 0,
            'allow_guest_orders' => true,
            'enable_waiter_call' => true,
            'enable_bill_request' => true,
            'auto_accept_orders' => false,
        ]);

        $this->table = $this->restaurant->tables()->create([
            'name' => 'Table 1',
            'capacity' => 4,
            'status' => TableStatus::Available,
        ]);

        $this->qr = $this->table->qrCode()->create([]);

        $menu = Menu::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $category = Category::factory()->create(['menu_id' => $menu->id]);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'is_available' => true,
        ]);
    }

    private function orderPayload(?string $sessionToken): array
    {
        return [
            'session_token' => $sessionToken,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ];
    }

    public function test_scanning_opens_a_dining_session(): void
    {
        $response = $this->postJson("/api/public/tables/{$this->qr->token}/session");

        $response->assertOk();
        $token = $response->json('data.session_token');
        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('dining_sessions', [
            'restaurant_table_id' => $this->table->id,
            'session_token' => $token,
            'status' => DiningSessionStatus::Open->value,
        ]);
    }

    public function test_second_scan_joins_the_same_open_session(): void
    {
        $first = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');
        $second = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');

        $this->assertSame($first, $second);
        $this->assertSame(1, DiningSession::where('restaurant_table_id', $this->table->id)->count());
    }

    public function test_order_requires_a_session_token(): void
    {
        $this->postJson("/api/public/tables/{$this->qr->token}/session");

        $this->postJson("/api/public/tables/{$this->qr->token}/orders", $this->orderPayload(null))
            ->assertStatus(422);
    }

    public function test_order_with_a_stale_session_token_is_rejected(): void
    {
        $this->postJson("/api/public/tables/{$this->qr->token}/session");

        $this->postJson(
            "/api/public/tables/{$this->qr->token}/orders",
            $this->orderPayload('11111111-1111-1111-1111-111111111111'),
        )->assertStatus(409);
    }

    public function test_order_with_the_open_session_token_succeeds_and_is_bound(): void
    {
        $token = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');

        $this->postJson("/api/public/tables/{$this->qr->token}/orders", $this->orderPayload($token))
            ->assertCreated();

        $session = DiningSession::where('session_token', $token)->firstOrFail();
        $this->assertDatabaseHas('orders', [
            'restaurant_table_id' => $this->table->id,
            'dining_session_id' => $session->id,
        ]);
    }

    public function test_bill_and_waiter_require_an_open_session(): void
    {
        // No session opened yet.
        $this->getJson("/api/public/tables/{$this->qr->token}/bill")
            ->assertStatus(409);
        $this->postJson("/api/public/tables/{$this->qr->token}/call-waiter", ['session_token' => null])
            ->assertStatus(409);

        $token = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');

        $this->getJson("/api/public/tables/{$this->qr->token}/bill?session_token={$token}")
            ->assertOk();
        $this->postJson("/api/public/tables/{$this->qr->token}/call-waiter", ['session_token' => $token])
            ->assertOk();
    }

    public function test_bill_request_respects_the_restaurant_setting(): void
    {
        $token = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');

        $this->postJson("/api/public/tables/{$this->qr->token}/bill/request", ['session_token' => $token])
            ->assertOk();
        $this->assertNotNull($this->table->fresh()->bill_requested_at);

        $this->restaurant->settings->update(['enable_bill_request' => false]);
        $this->table->update(['bill_requested_at' => null]);

        $this->postJson("/api/public/tables/{$this->qr->token}/bill/request", ['session_token' => $token])
            ->assertStatus(422);
        $this->assertNull($this->table->fresh()->bill_requested_at);

        // Reviewing the running total stays available either way.
        $this->getJson("/api/public/tables/{$this->qr->token}/bill?session_token={$token}")
            ->assertOk();
    }

    public function test_settling_the_bill_closes_the_session_and_blocks_further_orders(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $owner = User::factory()->create();
        $owner->assignRole(Role::RestaurantOwner->value);
        $owner->restaurants()->attach($this->restaurant->id, ['is_active' => true, 'joined_at' => now()]);

        $token = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');
        $this->postJson("/api/public/tables/{$this->qr->token}/orders", $this->orderPayload($token))
            ->assertCreated();

        // Staff settle the table.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/dashboard/tables/{$this->table->id}/settle")
            ->assertOk();

        $this->assertDatabaseHas('dining_sessions', [
            'session_token' => $token,
            'status' => DiningSessionStatus::Closed->value,
            'open_table_lock' => null,
        ]);

        // The old session token no longer works — the guest must scan again.
        $this->postJson("/api/public/tables/{$this->qr->token}/orders", $this->orderPayload($token))
            ->assertStatus(409);

        // A fresh scan opens a brand-new session.
        $newToken = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');
        $this->assertNotSame($token, $newToken);
    }

    private function openSession(): DiningSession
    {
        $token = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');

        return DiningSession::where('session_token', $token)->firstOrFail();
    }

    public function test_idle_session_is_closed_by_the_job(): void
    {
        $session = $this->openSession();
        // Idle for longer than the default 3h timeout.
        $session->forceFill(['last_activity_at' => now()->subHours(4)])->save();

        $this->artisan('sessions:close-idle')->assertSuccessful();

        $this->assertDatabaseHas('dining_sessions', [
            'id' => $session->id,
            'status' => DiningSessionStatus::Closed->value,
            'open_table_lock' => null,
        ]);
    }

    public function test_recently_active_session_is_kept_open(): void
    {
        $session = $this->openSession();
        $session->forceFill(['last_activity_at' => now()->subMinutes(30)])->save();

        $this->artisan('sessions:close-idle')->assertSuccessful();

        $this->assertDatabaseHas('dining_sessions', [
            'id' => $session->id,
            'status' => DiningSessionStatus::Open->value,
        ]);
    }

    public function test_interaction_bumps_activity_and_defers_closing(): void
    {
        $session = $this->openSession();
        // Push it past the timeout, then simulate a fresh guest interaction.
        $session->forceFill(['last_activity_at' => now()->subHours(4)])->save();

        $this->postJson(
            "/api/public/tables/{$this->qr->token}/call-waiter",
            ['session_token' => $session->session_token],
        )->assertOk();

        // Activity was bumped, so the idle job leaves the session open.
        $this->artisan('sessions:close-idle')->assertSuccessful();

        $this->assertDatabaseHas('dining_sessions', [
            'id' => $session->id,
            'status' => DiningSessionStatus::Open->value,
        ]);
    }

    public function test_hours_option_overrides_the_configured_timeout(): void
    {
        $session = $this->openSession();
        $session->forceFill(['last_activity_at' => now()->subMinutes(90)])->save();

        // 90 min of idle is under the 3h default but over a 1h override.
        $this->artisan('sessions:close-idle', ['--hours' => 1])->assertSuccessful();

        $this->assertDatabaseHas('dining_sessions', [
            'id' => $session->id,
            'status' => DiningSessionStatus::Closed->value,
        ]);
    }
}
