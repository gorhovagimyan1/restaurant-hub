<?php

namespace Tests\Feature\Dashboard;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff may only move orders and their items forwards, and never out of a
 * terminal state — see App\Enums\OrderStatus::allowedTransitions().
 */
class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantTable $table;

    private User $waiter;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->restaurant = Restaurant::factory()->create(['is_active' => true]);

        $this->table = $this->restaurant->tables()->create([
            'name' => 'Table 1',
            'capacity' => 4,
            'status' => TableStatus::Occupied,
        ]);

        $menu = Menu::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $category = Category::factory()->create(['menu_id' => $menu->id]);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'is_available' => true,
        ]);

        $this->waiter = User::factory()->create();
        $this->waiter->assignRole(Role::Waiter->value);
        $this->waiter->restaurants()->attach($this->restaurant->id, [
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    private function makeOrder(OrderStatus $status, OrderItemStatus $itemStatus = OrderItemStatus::Pending): Order
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'restaurant_table_id' => $this->table->id,
            'subtotal' => 1000,
            'tax' => 0,
            'service_charge' => 0,
            'total' => 1000,
            'status' => $status,
            'ordered_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'status' => $itemStatus,
        ]);

        return $order;
    }

    private function setStatus(Order $order, string $status)
    {
        return $this->actingAs($this->waiter, 'sanctum')
            ->patchJson("/api/dashboard/orders/{$order->id}/status", ['status' => $status]);
    }

    private function setItemStatus(OrderItem $item, string $status)
    {
        return $this->actingAs($this->waiter, 'sanctum')
            ->patchJson("/api/dashboard/order-items/{$item->id}/status", ['status' => $status]);
    }

    /* ---------------- Order level ---------------- */

    public function test_order_can_move_forwards(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        $this->setStatus($order, OrderStatus::Preparing->value)->assertOk();

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_order_can_skip_ahead(): void
    {
        $order = $this->makeOrder(OrderStatus::Pending);

        // The "mark everything ready" shortcut jumps past accepted/preparing.
        $this->setStatus($order, OrderStatus::Ready->value)->assertOk();

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
        $this->assertSame(OrderItemStatus::Ready, $order->items()->first()->status);
    }

    public function test_order_cannot_move_backwards(): void
    {
        $order = $this->makeOrder(OrderStatus::Served, OrderItemStatus::Served);

        $this->setStatus($order, OrderStatus::Pending->value)->assertStatus(422);

        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
    }

    public function test_completed_order_cannot_be_reopened(): void
    {
        $order = $this->makeOrder(OrderStatus::Completed, OrderItemStatus::Served);

        $this->setStatus($order, OrderStatus::Preparing->value)->assertStatus(422);
        $this->setStatus($order, OrderStatus::Cancelled->value)->assertStatus(422);

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_cancelled_order_cannot_be_revived(): void
    {
        $order = $this->makeOrder(OrderStatus::Cancelled, OrderItemStatus::Cancelled);

        $this->setStatus($order, OrderStatus::Preparing->value)->assertStatus(422);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_order_can_be_cancelled_until_it_is_settled(): void
    {
        $order = $this->makeOrder(OrderStatus::Ready, OrderItemStatus::Ready);

        $this->setStatus($order, OrderStatus::Cancelled->value)->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(OrderItemStatus::Cancelled, $order->items()->first()->status);
    }

    public function test_reapplying_the_current_status_is_a_no_op(): void
    {
        $order = $this->makeOrder(OrderStatus::Completed, OrderItemStatus::Served);
        $completedAt = $order->fresh()->completed_at;

        // A double-tap on the board must not error or re-stamp anything.
        $this->setStatus($order, OrderStatus::Completed->value)->assertOk();

        $this->assertEquals($completedAt, $order->fresh()->completed_at);
    }

    /* ---------------- Item level ---------------- */

    public function test_item_can_move_forwards(): void
    {
        $order = $this->makeOrder(OrderStatus::Accepted);
        $item = $order->items()->first();

        $this->setItemStatus($item, OrderItemStatus::Preparing->value)->assertOk();

        $this->assertSame(OrderItemStatus::Preparing, $item->fresh()->status);
    }

    public function test_item_cannot_move_backwards(): void
    {
        $order = $this->makeOrder(OrderStatus::Ready, OrderItemStatus::Ready);
        $item = $order->items()->first();

        $this->setItemStatus($item, OrderItemStatus::Pending->value)->assertStatus(422);

        $this->assertSame(OrderItemStatus::Ready, $item->fresh()->status);
    }

    public function test_cancelled_item_cannot_be_revived(): void
    {
        $order = $this->makeOrder(OrderStatus::Preparing, OrderItemStatus::Cancelled);
        $item = $order->items()->first();

        $this->setItemStatus($item, OrderItemStatus::Preparing->value)->assertStatus(422);

        $this->assertSame(OrderItemStatus::Cancelled, $item->fresh()->status);
    }

    public function test_served_item_can_still_be_written_off(): void
    {
        $order = $this->makeOrder(OrderStatus::Served, OrderItemStatus::Served);
        $item = $order->items()->first();

        // Comping a delivered dish is legitimate.
        $this->setItemStatus($item, OrderItemStatus::Cancelled->value)->assertOk();

        $this->assertSame(OrderItemStatus::Cancelled, $item->fresh()->status);
    }

    public function test_cancelling_the_only_active_item_does_not_drag_the_order_back(): void
    {
        $order = $this->makeOrder(OrderStatus::Accepted, OrderItemStatus::Pending);
        $item = $order->items()->first();

        $this->setItemStatus($item, OrderItemStatus::Cancelled->value)->assertOk();

        // Derived status would be "pending"; the order must not regress and
        // resurface on the board as new work.
        $this->assertSame(OrderStatus::Accepted, $order->fresh()->status);
    }

    public function test_transition_rules_do_not_leak_other_restaurants_orders(): void
    {
        $other = Restaurant::factory()->create(['is_active' => true]);
        $otherTable = $other->tables()->create([
            'name' => 'Table 9',
            'capacity' => 2,
            'status' => TableStatus::Occupied,
        ]);

        $order = Order::create([
            'restaurant_id' => $other->id,
            'restaurant_table_id' => $otherTable->id,
            'subtotal' => 500,
            'tax' => 0,
            'service_charge' => 0,
            'total' => 500,
            'status' => OrderStatus::Completed,
            'ordered_at' => now(),
        ]);

        // 404 (not a 422 about the status), so the response reveals nothing
        // about another tenant's order.
        $this->setStatus($order, OrderStatus::Preparing->value)->assertStatus(404);
    }
}
