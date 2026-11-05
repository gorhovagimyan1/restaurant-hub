<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Restaurant;
use App\Models\RestaurantSettings;
use App\Models\TableQrCode;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private TableQrCode $qr;

    private Category $category;

    private Product $cake;

    private string $session;

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

        $table = $this->restaurant->tables()->create([
            'name' => 'Table 1', 'capacity' => 4, 'status' => TableStatus::Available,
        ]);
        $this->qr = $table->qrCode()->create([]);

        $menu = Menu::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->category = Category::factory()->create(['menu_id' => $menu->id]);
        $this->cake = $this->product('Cheesecake');

        $this->session = $this->postJson("/api/public/tables/{$this->qr->token}/session")
            ->json('data.session_token');
    }

    private function product(string $name): Product
    {
        return Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => $name,
            'price' => 1000,
            'is_available' => true,
        ]);
    }

    /** Order one of a product and return its order item id. */
    private function orderItem(Product $product, OrderItemStatus $status = OrderItemStatus::Served): int
    {
        $this->postJson("/api/public/tables/{$this->qr->token}/orders", [
            'session_token' => $this->session,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $item = $this->restaurant->orders()->latest('id')->firstOrFail()->items()->firstOrFail();
        $item->update(['status' => $status]);

        return $item->id;
    }

    private function review(int $itemId, int $rating, ?string $comment = null)
    {
        return $this->postJson("/api/public/tables/{$this->qr->token}/reviews", [
            'session_token' => $this->session,
            'reviews' => [['order_item_id' => $itemId, 'rating' => $rating, 'comment' => $comment]],
        ]);
    }

    /** N reviews of a product, all with the given rating. */
    private function rate(Product $product, int $times, int $rating): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->review($this->orderItem($product), $rating)->assertCreated();
        }
    }

    public function test_guest_can_rate_a_served_dish(): void
    {
        $item = $this->orderItem($this->cake);

        $this->review($item, 5, 'Best cake ever')->assertCreated();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $this->cake->id,
            'order_item_id' => $item,
            'rating' => 5,
            'comment' => 'Best cake ever',
        ]);
    }

    public function test_a_dish_that_has_not_been_served_cannot_be_rated(): void
    {
        $item = $this->orderItem($this->cake, OrderItemStatus::Preparing);

        $this->review($item, 5)->assertStatus(422);
        $this->assertSame(0, ProductReview::count());
    }

    public function test_a_dish_can_only_be_rated_once(): void
    {
        $item = $this->orderItem($this->cake);

        $this->review($item, 5)->assertCreated();
        $this->review($item, 1)->assertStatus(422);

        $this->assertSame(1, ProductReview::count());
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $item = $this->orderItem($this->cake);

        $this->review($item, 0)->assertStatus(422);
        $this->review($item, 6)->assertStatus(422);
    }

    public function test_cannot_rate_another_tables_dish(): void
    {
        $item = $this->orderItem($this->cake);

        $otherTable = $this->restaurant->tables()->create([
            'name' => 'Table 2', 'capacity' => 2, 'status' => TableStatus::Available,
        ]);
        $otherQr = $otherTable->qrCode()->create([]);
        $otherSession = $this->postJson("/api/public/tables/{$otherQr->token}/session")
            ->json('data.session_token');

        $this->postJson("/api/public/tables/{$otherQr->token}/reviews", [
            'session_token' => $otherSession,
            'reviews' => [['order_item_id' => $item, 'rating' => 1]],
        ])->assertStatus(422);
    }

    public function test_review_needs_a_valid_session(): void
    {
        $item = $this->orderItem($this->cake);

        $this->postJson("/api/public/tables/{$this->qr->token}/reviews", [
            'session_token' => '00000000-0000-4000-8000-000000000000',
            'reviews' => [['order_item_id' => $item, 'rating' => 5]],
        ])->assertStatus(409);
    }

    public function test_tracking_flags_served_unrated_items_as_reviewable(): void
    {
        $item = $this->orderItem($this->cake);

        $row = fn () => $this->getJson("/api/public/tables/{$this->qr->token}/orders?session_token={$this->session}")
            ->json('data.orders.0.items.0');

        $this->assertTrue($row()['can_review']);
        $this->assertSame($item, $row()['id']);

        $this->review($item, 4)->assertCreated();

        $this->assertFalse($row()['can_review']);
        $this->assertSame(4, $row()['rating']);
    }

    private function menu(): array
    {
        return $this->getJson("/api/public/restaurants/{$this->restaurant->slug}/menu")
            ->assertOk()
            ->json('data');
    }

    public function test_menu_shows_average_rating_and_count(): void
    {
        $this->rate($this->cake, 1, 5);
        $this->rate($this->cake, 1, 4);

        $product = collect($this->menu()['categories'][0]['products'])->firstWhere('id', $this->cake->id);

        $this->assertSame(4.5, $product['rating']);
        $this->assertSame(2, $product['reviews_count']);
    }

    public function test_best_choices_needs_enough_reviews_and_a_high_rating(): void
    {
        $loved = $this->product('Tiramisu');
        $thin = $this->product('Brownie');   // 5 stars, but only 1 review
        $meh = $this->product('Soup');       // enough reviews, low rating

        $this->rate($loved, 3, 5);
        $this->rate($thin, 1, 5);
        $this->rate($meh, 3, 2);

        $best = collect($this->menu()['best_choices'])->pluck('name')->all();

        $this->assertSame(['Tiramisu'], $best);
    }

    public function test_best_choices_ranks_a_proven_favourite_above_a_thin_perfect_score(): void
    {
        $proven = $this->product('Proven');   // 10 reviews, averaging 4.9
        $newer = $this->product('Newer');     // 3 reviews, all 5 stars
        $meh = $this->product('Soup');        // drags the restaurant-wide mean down

        $this->rate($proven, 9, 5);
        $this->rate($proven, 1, 4);
        $this->rate($newer, 3, 5);
        $this->rate($meh, 3, 2);

        $best = collect($this->menu()['best_choices'])->pluck('name')->all();

        // Newer has the higher raw average (5.0 vs 4.9) but fewer reviews, so
        // the smoothed score puts the proven dish first.
        $this->assertSame(['Proven', 'Newer'], $best);
    }

    public function test_sold_out_dishes_are_left_out_of_best_choices(): void
    {
        $this->rate($this->cake, 3, 5);
        $this->cake->update(['stock_quantity' => 0]);

        $this->assertSame([], $this->menu()['best_choices']);
    }

    public function test_menu_without_reviews_has_no_best_choices(): void
    {
        $this->assertSame([], $this->menu()['best_choices']);
    }

    public function test_owner_can_list_and_delete_reviews_but_not_other_restaurants(): void
    {
        $this->review($this->orderItem($this->cake), 2, 'Dry')->assertCreated();

        $owner = User::factory()->create();
        $owner->assignRole(Role::RestaurantOwner->value);
        $this->restaurant->users()->attach($owner->id, ['is_active' => true, 'joined_at' => now()]);

        $this->actingAs($owner, 'sanctum');

        $rows = $this->getJson('/api/dashboard/reviews')->assertOk()->json('data.reviews');
        $this->assertSame('Dry', $rows[0]['comment']);
        $this->assertSame('Cheesecake', $rows[0]['product_name']);

        $reviewId = $rows[0]['id'];
        $this->deleteJson("/api/dashboard/reviews/{$reviewId}")->assertOk();
        $this->assertSame(0, ProductReview::count());
    }

    public function test_waiters_cannot_manage_reviews(): void
    {
        $this->review($this->orderItem($this->cake), 2)->assertCreated();

        $waiter = User::factory()->create();
        $waiter->assignRole(Role::Waiter->value);
        $this->restaurant->users()->attach($waiter->id, ['is_active' => true, 'joined_at' => now()]);

        $this->actingAs($waiter, 'sanctum')->getJson('/api/dashboard/reviews')->assertForbidden();
    }

    private function settle(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole(Role::RestaurantOwner->value);
        $this->restaurant->users()->attach($owner->id, ['is_active' => true, 'joined_at' => now()]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/dashboard/tables/'.$this->qr->restaurant_table_id.'/settle')
            ->assertOk();

        // Back to being an anonymous guest.
        $this->app['auth']->forgetGuards();
    }

    public function test_guest_can_still_rate_shortly_after_the_bill_is_settled(): void
    {
        $item = $this->orderItem($this->cake);
        $this->settle();

        $pending = $this->getJson("/api/public/tables/{$this->qr->token}/reviews/pending?session_token={$this->session}")
            ->assertOk()
            ->json('data.items');
        $this->assertSame([$item], collect($pending)->pluck('id')->all());

        $this->review($item, 5)->assertCreated();

        $this->getJson("/api/public/tables/{$this->qr->token}/reviews/pending?session_token={$this->session}")
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }

    public function test_rating_window_closes_after_the_configured_hours(): void
    {
        $item = $this->orderItem($this->cake);
        $this->settle();

        $this->travel(2)->hours();
        $this->travel(1)->minutes();

        $this->review($item, 5)->assertStatus(409);
        $this->getJson("/api/public/tables/{$this->qr->token}/reviews/pending?session_token={$this->session}")
            ->assertStatus(409);
    }

    public function test_a_closed_session_still_cannot_order_or_see_the_bill(): void
    {
        $this->orderItem($this->cake);
        $this->settle();

        $this->postJson("/api/public/tables/{$this->qr->token}/orders", [
            'session_token' => $this->session,
            'items' => [['product_id' => $this->cake->id, 'quantity' => 1]],
        ])->assertStatus(409);

        $this->getJson("/api/public/tables/{$this->qr->token}/bill?session_token={$this->session}")
            ->assertStatus(409);
    }

    public function test_a_session_that_timed_out_idle_can_still_be_rated(): void
    {
        $item = $this->orderItem($this->cake);

        $this->travel(4)->hours();
        $this->artisan('sessions:close-idle')->assertSuccessful();

        $this->review($item, 4)->assertCreated();
    }
}
