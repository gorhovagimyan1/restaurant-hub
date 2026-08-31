<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSettings;
use App\Models\RestaurantTable;
use App\Models\TableQrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guest-facing "where is my food" endpoint.
 */
class CustomerOrderTrackingTest extends TestCase
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

    /** Scan, then place one order. Returns the session token. */
    private function seatAndOrder(int $quantity = 2): string
    {
        $token = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');

        $this->postJson("/api/public/tables/{$this->qr->token}/orders", [
            'session_token' => $token,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity],
            ],
        ])->assertCreated();

        return $token;
    }

    private function tracking(string $sessionToken)
    {
        return $this->getJson(
            "/api/public/tables/{$this->qr->token}/orders?session_token={$sessionToken}",
        );
    }

    public function test_tracking_requires_a_valid_session_token(): void
    {
        $this->seatAndOrder();

        // No token at all, and a token from someone else's visit.
        $this->getJson("/api/public/tables/{$this->qr->token}/orders")->assertStatus(409);
        $this->tracking('11111111-1111-1111-1111-111111111111')->assertStatus(409);
    }

    public function test_guest_sees_their_order_with_items_and_status(): void
    {
        $token = $this->seatAndOrder();

        $this->tracking($token)
            ->assertOk()
            ->assertJsonPath('data.currency', 'AMD')
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.status', OrderStatus::Pending->value)
            ->assertJsonPath('data.orders.0.is_final', false)
            // 2 × 1000, tax and service both zero for this restaurant.
            ->assertJsonPath('data.orders.0.total', 2000)
            ->assertJsonCount(1, 'data.orders.0.items')
            ->assertJsonPath('data.orders.0.items.0.quantity', 2)
            ->assertJsonPath('data.orders.0.items.0.status', OrderItemStatus::Pending->value)
            ->assertJsonPath('data.has_active', true);
    }

    public function test_status_changes_by_staff_are_visible_to_the_guest(): void
    {
        $token = $this->seatAndOrder();

        $order = $this->restaurant->orders()->sole();
        $order->update(['status' => OrderStatus::Preparing]);

        $this->tracking($token)
            ->assertOk()
            ->assertJsonPath('data.orders.0.status', OrderStatus::Preparing->value)
            ->assertJsonPath('data.has_active', true);
    }

    public function test_per_item_progress_is_reported(): void
    {
        $token = $this->seatAndOrder();

        $item = $this->restaurant->orders()->sole()->items()->sole();
        $item->update(['status' => OrderItemStatus::Ready]);

        $this->tracking($token)
            ->assertOk()
            ->assertJsonPath('data.orders.0.items.0.status', OrderItemStatus::Ready->value);
    }

    public function test_cancelled_orders_stay_visible_so_the_guest_knows(): void
    {
        $token = $this->seatAndOrder();

        $this->restaurant->orders()->sole()->update(['status' => OrderStatus::Cancelled]);

        // The bill drops cancelled orders; the tracking screen must not.
        $this->tracking($token)
            ->assertOk()
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.orders.0.is_final', true)
            // Nothing can change again — the client may stop polling.
            ->assertJsonPath('data.has_active', false);
    }

    public function test_multiple_rounds_are_listed_newest_first(): void
    {
        $token = $this->seatAndOrder();

        $this->travel(1)->minutes();

        $this->postJson("/api/public/tables/{$this->qr->token}/orders", [
            'session_token' => $token,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ])->assertCreated();

        $response = $this->tracking($token)->assertOk()->assertJsonCount(2, 'data.orders');

        $orders = $response->json('data.orders');
        $this->assertGreaterThan(
            strtotime($orders[1]['created_at']),
            strtotime($orders[0]['created_at']),
            'The most recent round should come first.',
        );
    }

    public function test_another_tables_orders_are_never_exposed(): void
    {
        $mine = $this->seatAndOrder();

        $other = $this->restaurant->tables()->create([
            'name' => 'Table 2',
            'capacity' => 2,
            'status' => TableStatus::Available,
        ]);
        $otherQr = $other->qrCode()->create([]);

        $otherToken = $this->postJson("/api/public/tables/{$otherQr->token}/session")
            ->json('data.session_token');

        $this->postJson("/api/public/tables/{$otherQr->token}/orders", [
            'session_token' => $otherToken,
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ])->assertCreated();

        // My table still only sees my own single order.
        $this->tracking($mine)->assertOk()->assertJsonCount(1, 'data.orders');

        // And my token cannot be used against the other table's QR.
        $this->getJson("/api/public/tables/{$otherQr->token}/orders?session_token={$mine}")
            ->assertStatus(409);
    }

    public function test_tracking_keeps_the_session_alive(): void
    {
        $token = $this->seatAndOrder();

        $session = $this->table->openSession()->sole();
        $before = $session->last_activity_at;

        $this->travel(5)->minutes();
        $this->tracking($token)->assertOk();

        $this->assertTrue(
            $session->fresh()->last_activity_at->greaterThan($before),
            'Checking on an order counts as activity, so the idle job leaves the table alone.',
        );
    }
}
