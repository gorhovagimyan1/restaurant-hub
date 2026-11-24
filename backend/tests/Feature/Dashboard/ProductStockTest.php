<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSettings;
use App\Models\TableQrCode;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProductStockTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private User $waiter;

    private TableQrCode $qr;

    private Product $dessert;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->restaurant = Restaurant::factory()->create(['is_active' => true]);
        RestaurantSettings::create([
            'restaurant_id' => $this->restaurant->id,
            'currency' => 'AMD',
            'tax_percentage' => 0,
            'service_charge' => 0,
            'allow_guest_orders' => true,
        ]);

        $this->owner = $this->staff(Role::RestaurantOwner);
        $this->waiter = $this->staff(Role::Waiter);

        $table = $this->restaurant->tables()->create([
            'name' => 'Table 1', 'capacity' => 4, 'status' => TableStatus::Available,
        ]);
        $this->qr = $table->qrCode()->create([]);

        $menu = Menu::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $category = Category::factory()->create(['menu_id' => $menu->id]);
        $this->dessert = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'is_available' => true,
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
        ]);
    }

    private function staff(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->restaurant->users()->attach($user->id, ['is_active' => true, 'joined_at' => now()]);

        return $user;
    }

    private function order(int $quantity, ?Product $product = null)
    {
        $token = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');

        return $this->postJson("/api/public/tables/{$this->qr->token}/orders", [
            'session_token' => $token,
            'items' => [['product_id' => ($product ?? $this->dessert)->id, 'quantity' => $quantity]],
        ]);
    }

    public function test_ordering_deducts_stock(): void
    {
        $this->order(4)->assertCreated();

        $this->assertSame(6, $this->dessert->fresh()->stock_quantity);
    }

    public function test_cannot_order_more_than_is_in_stock_and_nothing_is_deducted(): void
    {
        $this->order(11)->assertStatus(422);

        $this->assertSame(10, $this->dessert->fresh()->stock_quantity);
    }

    public function test_untracked_products_are_never_limited(): void
    {
        $this->dessert->update(['stock_quantity' => null]);

        $this->order(50)->assertCreated();

        $this->assertNull($this->dessert->fresh()->stock_quantity);
    }

    public function test_out_of_stock_product_shows_sold_out_without_leaking_the_count(): void
    {
        $this->order(10)->assertCreated();

        $menu = $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")->assertOk();

        $product = collect($menu->json('data.categories'))->pluck('products')->flatten(1)
            ->firstWhere('id', $this->dessert->id);

        $this->assertFalse($product['is_available']);
        $this->assertArrayNotHasKey('stock_quantity', $product);
        $this->assertArrayNotHasKey('low_stock_threshold', $product);
    }

    public function test_managers_are_alerted_when_stock_falls_to_the_threshold_once(): void
    {
        Notification::fake();

        $this->order(6)->assertCreated(); // 10 -> 4, still above 3
        Notification::assertNothingSent();

        $this->order(1)->assertCreated(); // 4 -> 3, crosses
        Notification::assertSentTo($this->owner, LowStockNotification::class);
        Notification::assertNotSentTo($this->waiter, LowStockNotification::class);

        $this->order(1)->assertCreated(); // 3 -> 2, already low: no repeat
        Notification::assertSentToTimes($this->owner, LowStockNotification::class, 1);
    }

    public function test_cancelling_an_order_restores_stock(): void
    {
        $this->order(4)->assertCreated();
        $order = $this->restaurant->orders()->firstOrFail();

        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/dashboard/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(10, $this->dessert->fresh()->stock_quantity);
    }

    public function test_cancelling_a_single_item_restores_it_only_once(): void
    {
        $this->order(4)->assertCreated();
        $item = $this->restaurant->orders()->firstOrFail()->items()->firstOrFail();

        $this->actingAs($this->owner, 'sanctum');
        $this->patchJson("/api/dashboard/order-items/{$item->id}/status", ['status' => 'cancelled'])->assertOk();
        $this->patchJson("/api/dashboard/order-items/{$item->id}/status", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(10, $this->dessert->fresh()->stock_quantity);
    }

    public function test_a_served_dish_is_not_restocked_when_cancelled(): void
    {
        $this->order(4)->assertCreated();
        $item = $this->restaurant->orders()->firstOrFail()->items()->firstOrFail();

        $this->actingAs($this->owner, 'sanctum');
        $this->patchJson("/api/dashboard/order-items/{$item->id}/status", ['status' => 'served'])->assertOk();
        $this->patchJson("/api/dashboard/order-items/{$item->id}/status", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(6, $this->dessert->fresh()->stock_quantity);
    }

    public function test_bulk_stock_update_sets_and_clears_tracking(): void
    {
        $other = Product::factory()->create([
            'category_id' => $this->dessert->category_id,
            'stock_quantity' => null,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/dashboard/products/stock', ['items' => [
                ['id' => $this->dessert->id, 'stock_quantity' => 25],
                ['id' => $other->id, 'stock_quantity' => 8],
            ]])
            ->assertOk();

        $this->assertSame(25, $this->dessert->fresh()->stock_quantity);
        $this->assertSame(8, $other->fresh()->stock_quantity);

        $this->putJson('/api/dashboard/products/stock', ['items' => [
            ['id' => $other->id, 'stock_quantity' => null],
        ]])->assertOk();

        $this->assertNull($other->fresh()->stock_quantity);
    }

    public function test_bulk_stock_update_rejects_other_restaurants_products_and_negatives(): void
    {
        $foreign = Product::factory()->create(['stock_quantity' => 5]);

        $this->actingAs($this->owner, 'sanctum');

        $this->putJson('/api/dashboard/products/stock', ['items' => [
            ['id' => $foreign->id, 'stock_quantity' => 99],
        ]])->assertStatus(422);
        $this->assertSame(5, $foreign->fresh()->stock_quantity);

        $this->putJson('/api/dashboard/products/stock', ['items' => [
            ['id' => $this->dessert->id, 'stock_quantity' => -1],
        ]])->assertStatus(422);
    }

    public function test_waiters_cannot_update_stock(): void
    {
        $this->actingAs($this->waiter, 'sanctum')
            ->putJson('/api/dashboard/products/stock', ['items' => [
                ['id' => $this->dessert->id, 'stock_quantity' => 99],
            ]])
            ->assertForbidden();
    }

    public function test_dashboard_product_list_exposes_stock_status(): void
    {
        $this->dessert->update(['stock_quantity' => 2]);

        $row = collect(
            $this->actingAs($this->owner, 'sanctum')->getJson('/api/dashboard/products')->json('data'),
        )->firstWhere('id', $this->dessert->id);

        $this->assertSame(2, $row['stock_quantity']);
        $this->assertSame('low', $row['stock_status']);
    }

    public function test_daily_stock_refills_once_per_business_day(): void
    {
        $this->restaurant->update(['timezone' => 'UTC']);
        $this->dessert->update(['daily_stock' => 20, 'stock_quantity' => 3, 'stock_reset_on' => '2026-11-04']);

        $this->travelTo('2026-11-05 09:00:00');
        $this->artisan('stock:reset-daily')->assertSuccessful();
        $this->assertSame(20, $this->dessert->fresh()->stock_quantity);

        // Sold some during the day; a second run the same day must not refill.
        $this->dessert->update(['stock_quantity' => 7]);
        $this->artisan('stock:reset-daily')->assertSuccessful();
        $this->assertSame(7, $this->dessert->fresh()->stock_quantity);

        // Next business day it refills again.
        $this->travelTo('2026-11-06 09:00:00');
        $this->artisan('stock:reset-daily')->assertSuccessful();
        $this->assertSame(20, $this->dessert->fresh()->stock_quantity);
    }

    public function test_business_day_does_not_roll_over_at_midnight(): void
    {
        $this->restaurant->update(['timezone' => 'UTC']);
        $this->dessert->update(['daily_stock' => 20, 'stock_quantity' => 2, 'stock_reset_on' => '2026-11-04']);

        // 01:00 on the 5th is still the 4th's service (rollover is 05:00).
        $this->travelTo('2026-11-05 01:00:00');
        $this->artisan('stock:reset-daily')->assertSuccessful();
        $this->assertSame(2, $this->dessert->fresh()->stock_quantity);

        $this->travelTo('2026-11-05 05:00:00');
        $this->artisan('stock:reset-daily')->assertSuccessful();
        $this->assertSame(20, $this->dessert->fresh()->stock_quantity);
    }

    public function test_daily_reset_uses_the_restaurants_timezone(): void
    {
        $this->restaurant->update(['timezone' => 'Asia/Yerevan']); // UTC+4
        $this->dessert->update(['daily_stock' => 20, 'stock_quantity' => 2, 'stock_reset_on' => '2026-11-04']);

        // 02:00 UTC = 06:00 in Yerevan: already past the 05:00 rollover.
        $this->travelTo('2026-11-05 02:00:00');
        $this->artisan('stock:reset-daily')->assertSuccessful();

        $this->assertSame(20, $this->dessert->fresh()->stock_quantity);
    }

    public function test_setting_a_daily_quantity_fills_stock_immediately(): void
    {
        $this->dessert->update(['stock_quantity' => null]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/products/{$this->dessert->id}", ['daily_stock' => 15])
            ->assertOk();

        $fresh = $this->dessert->fresh();
        $this->assertSame(15, $fresh->stock_quantity);

        // ...and tonight's job must not refill it a second time today.
        $this->dessert->update(['stock_quantity' => 4]);
        $this->artisan('stock:reset-daily')->assertSuccessful();
        $this->assertSame(4, $this->dessert->fresh()->stock_quantity);
    }

    public function test_stock_alerts_list_low_and_out_items_for_floor_staff(): void
    {
        $out = Product::factory()->create(['category_id' => $this->dessert->category_id, 'name' => 'Tiramisu', 'stock_quantity' => 0]);
        $fine = Product::factory()->create(['category_id' => $this->dessert->category_id, 'stock_quantity' => 50]);
        $untracked = Product::factory()->create(['category_id' => $this->dessert->category_id, 'stock_quantity' => null]);
        $this->dessert->update(['stock_quantity' => 2]); // threshold 3 -> low

        $rows = $this->actingAs($this->waiter, 'sanctum')
            ->getJson('/api/dashboard/stock-alerts')
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing(
            [$out->id, $this->dessert->id],
            collect($rows)->pluck('id')->all(),
        );
        $this->assertSame('out', collect($rows)->firstWhere('id', $out->id)['status']);
    }
}
