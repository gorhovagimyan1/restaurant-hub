<?php

namespace Tests\Feature\Dashboard;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PrepStation;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\CategoryPreset;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantSettings;
use App\Models\TableQrCode;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Drinks: items are routed to the kitchen, the bar, or straight to "ready"
 * (nothing to prepare), and each board's shortcuts only move its own items.
 */
class DrinksPrepStationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $owner;

    private User $waiter;

    private TableQrCode $qr;

    private Menu $menu;

    private Product $burger;

    private Product $cocktail;

    private Product $cola;

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

        $this->menu = Menu::factory()->create(['restaurant_id' => $this->restaurant->id]);

        $food = Category::factory()->create(['menu_id' => $this->menu->id]);
        $cocktails = Category::factory()->create([
            'menu_id' => $this->menu->id,
            'prep_station' => PrepStation::Bar,
            'is_alcoholic' => true,
        ]);
        $softDrinks = Category::factory()->create([
            'menu_id' => $this->menu->id,
            'prep_station' => PrepStation::Ready,
        ]);

        $this->burger = $this->product($food);
        $this->cocktail = $this->product($cocktails);
        $this->cola = $this->product($softDrinks);
    }

    private function staff(Role $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->restaurant->users()->attach($user->id, ['is_active' => true, 'joined_at' => now()]);

        return $user;
    }

    private function product(Category $category, array $attributes = []): Product
    {
        return Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'is_available' => true,
            ...$attributes,
        ]);
    }

    /** Scan the table and order one of each product given. */
    private function order(Product ...$products): Order
    {
        $token = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');

        $this->postJson("/api/public/tables/{$this->qr->token}/orders", [
            'session_token' => $token,
            'items' => array_map(fn (Product $p) => ['product_id' => $p->id, 'quantity' => 1], $products),
        ])->assertCreated();

        return Order::latest('id')->firstOrFail();
    }

    private function item(Order $order, Product $product)
    {
        return $order->items()->where('product_id', $product->id)->firstOrFail();
    }

    private function boardAction(Order $order, string $status, string $board)
    {
        return $this->actingAs($this->waiter, 'sanctum')
            ->patchJson("/api/dashboard/orders/{$order->id}/status", ['status' => $status, 'board' => $board]);
    }

    /* ---------------- Placing an order ---------------- */

    public function test_each_item_is_routed_to_its_station(): void
    {
        $order = $this->order($this->burger, $this->cocktail, $this->cola);

        $burger = $this->item($order, $this->burger);
        $cocktail = $this->item($order, $this->cocktail);
        $cola = $this->item($order, $this->cola);

        $this->assertSame(PrepStation::Kitchen, $burger->prep_station);
        $this->assertSame(OrderItemStatus::Pending, $burger->status);
        $this->assertSame(PrepStation::Bar, $cocktail->prep_station);
        $this->assertSame(OrderItemStatus::Pending, $cocktail->status);

        // Nothing to prepare: ready for the waiter straight away…
        $this->assertSame(PrepStation::Ready, $cola->prep_station);
        $this->assertSame(OrderItemStatus::Ready, $cola->status);

        // …while the order itself still waits to be accepted.
        $this->assertSame(OrderStatus::Pending, $order->status);
    }

    public function test_a_product_can_override_its_category(): void
    {
        $food = $this->burger->category;
        $lemonade = $this->product($food, ['prep_station' => PrepStation::Bar]);

        $order = $this->order($lemonade);

        $this->assertSame(PrepStation::Bar, $this->item($order, $lemonade)->prep_station);
    }

    public function test_staff_see_the_station_of_each_item(): void
    {
        $this->order($this->burger, $this->cola);

        $items = collect(
            $this->actingAs($this->waiter, 'sanctum')->getJson('/api/dashboard/orders')
                ->assertOk()
                ->json('data.0.items'),
        )->pluck('prep_station', 'product_name');

        $this->assertSame('kitchen', $items[$this->burger->name]);
        $this->assertSame('ready', $items[$this->cola->name]);
    }

    public function test_a_drinks_only_order_is_served_once_the_waiter_brings_it(): void
    {
        $order = $this->order($this->cola);

        $this->actingAs($this->waiter, 'sanctum')
            ->patchJson("/api/dashboard/order-items/{$this->item($order, $this->cola)->id}/status", ['status' => 'served'])
            ->assertOk();

        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
    }

    /* ---------------- Board shortcuts ---------------- */

    public function test_kitchen_mark_all_ready_leaves_the_bar_alone(): void
    {
        $order = $this->order($this->burger, $this->cocktail);

        $this->boardAction($order, 'ready', 'kitchen')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Preparing->value);

        $this->assertSame(OrderItemStatus::Ready, $this->item($order, $this->burger)->status);
        $this->assertSame(OrderItemStatus::Pending, $this->item($order, $this->cocktail)->status);
    }

    public function test_bar_deliver_all_covers_bar_and_ready_items_only(): void
    {
        $order = $this->order($this->burger, $this->cocktail, $this->cola);

        $this->boardAction($order, 'served', 'bar')->assertOk();

        $this->assertSame(OrderItemStatus::Served, $this->item($order, $this->cocktail)->status);
        $this->assertSame(OrderItemStatus::Served, $this->item($order, $this->cola)->status);
        $this->assertSame(OrderItemStatus::Pending, $this->item($order, $this->burger)->status);
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);

        // Once the kitchen delivers too, the whole order is served.
        $this->boardAction($order, 'served', 'kitchen')->assertOk();
        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
    }

    public function test_boards_cannot_cancel_or_complete_orders(): void
    {
        $order = $this->order($this->burger);

        $this->boardAction($order, 'cancelled', 'kitchen')->assertStatus(422);
        $this->boardAction($order, 'completed', 'bar')->assertStatus(422);
        $this->boardAction($order, 'ready', 'cellar')->assertStatus(422);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_whole_order_actions_without_a_board_still_move_everything(): void
    {
        $order = $this->order($this->burger, $this->cocktail);

        $this->actingAs($this->waiter, 'sanctum')
            ->patchJson("/api/dashboard/orders/{$order->id}/status", ['status' => 'ready'])
            ->assertOk();

        $this->assertSame(OrderItemStatus::Ready, $this->item($order, $this->burger)->status);
        $this->assertSame(OrderItemStatus::Ready, $this->item($order, $this->cocktail)->status);
    }

    /* ---------------- Menu setup ---------------- */

    public function test_a_category_from_the_catalog_starts_with_its_drink_defaults(): void
    {
        $preset = CategoryPreset::create([
            'translations' => ['en' => ['name' => 'Cocktails', 'description' => '']],
            'prep_station' => PrepStation::Bar,
            'is_alcoholic' => true,
        ]);

        $id = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/categories', ['category_preset_id' => $preset->id])
            ->assertCreated()
            ->assertJsonPath('data.prep_station', 'bar')
            ->assertJsonPath('data.is_alcoholic', true)
            ->json('data.id');

        // The owner's own choice wins, and re-saving the preset keeps it.
        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/categories/{$id}", ['category_preset_id' => $preset->id, 'prep_station' => 'ready'])
            ->assertOk()
            ->assertJsonPath('data.prep_station', 'ready');

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/categories/{$id}", ['category_preset_id' => $preset->id])
            ->assertOk()
            ->assertJsonPath('data.prep_station', 'ready')
            ->assertJsonPath('data.is_alcoholic', true);
    }

    public function test_a_custom_category_defaults_to_the_kitchen(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/dashboard/categories', ['name' => 'Burgers'])
            ->assertCreated()
            ->assertJsonPath('data.prep_station', 'kitchen')
            ->assertJsonPath('data.is_alcoholic', false);
    }

    public function test_product_settings_can_be_set_and_reset_to_the_category(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/products/{$this->burger->id}", ['prep_station' => 'bar', 'is_alcoholic' => true])
            ->assertOk()
            ->assertJsonPath('data.prep_station', 'bar')
            ->assertJsonPath('data.is_alcoholic', true);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/products/{$this->burger->id}", ['prep_station' => null, 'is_alcoholic' => null])
            ->assertOk()
            ->assertJsonPath('data.prep_station', null)
            ->assertJsonPath('data.is_alcoholic', null);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/dashboard/products/{$this->burger->id}", ['prep_station' => 'cellar'])
            ->assertStatus(422);
    }

    public function test_the_menu_flags_alcoholic_products(): void
    {
        // A mocktail in the 18+ cocktails category opts out.
        $mocktail = $this->product($this->cocktail->category, ['is_alcoholic' => false]);

        $products = collect(
            $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")
                ->assertOk()
                ->json('data.categories'),
        )->flatMap(fn (array $c) => $c['products'])->pluck('is_alcoholic', 'id');

        $this->assertTrue($products[$this->cocktail->id]);
        $this->assertFalse($products[$mocktail->id]);
        $this->assertFalse($products[$this->burger->id]);
        $this->assertFalse($products[$this->cola->id]);
    }
}
