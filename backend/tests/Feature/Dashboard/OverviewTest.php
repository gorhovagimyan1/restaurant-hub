<?php

namespace Tests\Feature\Dashboard;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private RestaurantTable $table;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Freeze time mid-day so "today" is unambiguous.
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00', 'UTC'));

        $this->restaurant = Restaurant::factory()->create([
            'is_active' => true,
            'timezone' => 'UTC',
        ]);

        $this->owner = $this->member(Role::RestaurantOwner);

        $this->table = $this->restaurant->tables()->create([
            'name' => 'T1',
            'capacity' => 4,
            'status' => TableStatus::Available,
        ]);

        $menu = Menu::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $category = Category::factory()->create(['menu_id' => $menu->id]);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Khorovats',
        ]);
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
     * @param  array<int, array<string, mixed>>  $items
     */
    private function order(array $attributes = [], array $items = []): Order
    {
        // created_at isn't mass-assignable; apply it after the insert.
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $order = $this->restaurant->orders()->create(array_merge([
            'restaurant_table_id' => $this->table->id,
            'status' => OrderStatus::Pending->value,
            'subtotal' => 0,
            'tax' => 0,
            'service_charge' => 0,
            'total' => 0,
        ], $attributes));

        if ($createdAt) {
            $order->forceFill(['created_at' => $createdAt])->save();
        }

        foreach ($items as $item) {
            $order->items()->create(array_merge([
                'restaurant_id' => $this->restaurant->id,
                'product_id' => $this->product->id,
                'product_name' => $this->product->name,
                'quantity' => 1,
                'unit_price' => 1000,
                'total_price' => 1000,
            ], $item));
        }

        return $order;
    }

    public function test_overview_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/overview')->assertUnauthorized();
    }

    public function test_staff_without_reporting_access_are_forbidden(): void
    {
        // Waiters have orders.view but not reports.view.
        $waiter = $this->member(Role::Waiter);

        $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertForbidden();
    }

    public function test_today_takings_count_only_completed_orders_today(): void
    {
        $this->order(['status' => OrderStatus::Completed->value, 'completed_at' => now(), 'total' => 5000]);
        $this->order(['status' => OrderStatus::Completed->value, 'completed_at' => now(), 'total' => 3000]);
        // Created today but not completed → counts toward orders, not revenue.
        $this->order(['status' => OrderStatus::Pending->value, 'total' => 9000]);
        // Completed yesterday → excluded from today entirely.
        $this->order([
            'status' => OrderStatus::Completed->value,
            'total' => 7000,
            'created_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.today.orders', 3)
            ->assertJsonPath('data.today.completed', 2)
            ->assertJsonPath('data.today.revenue', 8000)
            ->assertJsonPath('data.today.avg_order', 4000)
            ->assertJsonPath('data.currency', $this->restaurant->currency);
    }

    public function test_live_order_load_is_broken_down_by_status(): void
    {
        $this->order(['status' => OrderStatus::Pending->value]);
        $this->order(['status' => OrderStatus::Pending->value]);
        $this->order(['status' => OrderStatus::Preparing->value]);
        $this->order(['status' => OrderStatus::Ready->value]);
        // Final orders are not "live".
        $this->order(['status' => OrderStatus::Completed->value, 'completed_at' => now()]);
        $this->order(['status' => OrderStatus::Cancelled->value]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.live.active_orders', 4)
            ->assertJsonPath('data.live.pending', 2)
            ->assertJsonPath('data.live.preparing', 1)
            ->assertJsonPath('data.live.ready', 1);
    }

    public function test_table_occupancy_and_service_calls(): void
    {
        $this->restaurant->tables()->create(['name' => 'T2', 'capacity' => 2, 'status' => TableStatus::Occupied]);
        $this->restaurant->tables()->create([
            'name' => 'T3',
            'capacity' => 2,
            'status' => TableStatus::Occupied,
            'waiter_called_at' => now(),
        ]);
        $this->table->update(['bill_requested_at' => now()]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.tables.total', 3)
            ->assertJsonPath('data.tables.occupied', 2)
            ->assertJsonPath('data.tables.available', 1)
            ->assertJsonPath('data.service.waiter_calls', 1)
            ->assertJsonPath('data.service.bill_requests', 1);
    }

    public function test_recent_orders_and_top_products(): void
    {
        $other = Product::factory()->create([
            'category_id' => $this->product->category_id,
            'name' => 'Lavash',
        ]);

        $this->order(['status' => OrderStatus::Pending->value, 'total' => 4000], [
            ['product_id' => $this->product->id, 'product_name' => 'Khorovats', 'quantity' => 3],
            ['product_id' => $other->id, 'product_name' => 'Lavash', 'quantity' => 5],
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk();

        $this->assertCount(1, $response->json('data.recent_orders'));

        $top = $response->json('data.top_products');
        $this->assertSame('Lavash', $top[0]['name']);
        $this->assertSame(5, $top[0]['quantity']);
        $this->assertSame('Khorovats', $top[1]['name']);
        $this->assertSame(3, $top[1]['quantity']);
    }

    public function test_overview_is_scoped_to_the_current_restaurant(): void
    {
        // Another restaurant with its own completed order today.
        $other = Restaurant::factory()->create();
        $otherTable = $other->tables()->create(['name' => 'X', 'capacity' => 2, 'status' => TableStatus::Available]);
        $other->orders()->create([
            'restaurant_table_id' => $otherTable->id,
            'status' => OrderStatus::Completed->value,
            'completed_at' => now(),
            'total' => 99000,
            'subtotal' => 0, 'tax' => 0, 'service_charge' => 0,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.today.orders', 0)
            ->assertJsonPath('data.today.revenue', 0);
    }
}
