<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreProductRequest;
use App\Http\Requests\Dashboard\UpdateProductRequest;
use App\Http\Requests\Dashboard\UpdateStockRequest;
use App\Http\Resources\Dashboard\ProductResource;
use App\Models\Product;
use App\Models\Restaurant;
use App\Services\Stock\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use ResolvesRestaurant;

    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        $products = $restaurant->products()
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->with('images')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ProductResource::collection($products));
    }

    public function store(StoreProductRequest $request, StockService $stock): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->assertCategoryOwned($restaurant, $request->integer('category_id'));

        $product = $restaurant->products()->create($this->withDailyStart($restaurant, $request->validated(), $stock));

        return ApiResponse::created(
            new ProductResource($product->load('images')),
            'Product created.',
        );
    }

    public function update(UpdateProductRequest $request, Product $product, StockService $stock): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $product);

        if ($request->filled('category_id')) {
            $this->assertCategoryOwned($restaurant, $request->integer('category_id'));
        }

        $product->update($this->withDailyStart($restaurant, $request->validated(), $stock, $product));

        return ApiResponse::success(
            new ProductResource($product->load('images')),
            'Product updated.',
        );
    }

    /**
     * Update many products' stock in one go (the "Update stock" button).
     * A null quantity stops tracking that product. Manual edits don't send
     * low-stock alerts: the person editing is already looking at the numbers.
     */
    public function updateStock(UpdateStockRequest $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $items = collect($request->validated('items'))->keyBy('id');

        $products = DB::transaction(function () use ($restaurant, $items) {
            $products = $restaurant->products()
                ->whereIn('id', $items->keys())
                ->lockForUpdate()
                ->get();

            abort_unless($products->count() === $items->count(), 422, 'One of the products is invalid.');

            foreach ($products as $product) {
                $product->update(['stock_quantity' => $items[$product->id]['stock_quantity'] ?? null]);
            }

            return $products;
        });

        return ApiResponse::success(
            ProductResource::collection($products->load('images')),
            'Stock updated.',
        );
    }

    /**
     * Tracked products that are low or out, for the in-app stock banner.
     * Open to anyone who can see orders: the floor needs to know a dessert is
     * about to run out, not just whoever manages the menu.
     */
    public function stockAlerts(Request $request, StockService $stock): JsonResponse
    {
        $items = $stock->lowItems($this->currentRestaurant($request));

        return ApiResponse::success($items->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'quantity' => $p->stock_quantity,
            'status' => $p->stockStatus(),
        ]));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $product);

        $product->delete();

        return ApiResponse::success(null, 'Product deleted.');
    }

    /**
     * Setting a daily quantity should take effect today, not tomorrow: fill
     * the stock now (unless a count was just entered by hand) and mark today
     * as already reset so the nightly job doesn't overwrite it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDailyStart(Restaurant $restaurant, array $data, StockService $stock, ?Product $existing = null): array
    {
        $daily = $data['daily_stock'] ?? null;

        if ($daily === null || (int) $daily === $existing?->daily_stock) {
            return $data;
        }

        $data['stock_reset_on'] = $stock->businessDate($restaurant);

        if (($data['stock_quantity'] ?? null) === null) {
            $data['stock_quantity'] = $daily;
        }

        return $data;
    }

    /**
     * Ensure the target category belongs to the restaurant.
     */
    private function assertCategoryOwned(Restaurant $restaurant, int $categoryId): void
    {
        abort_unless(
            $restaurant->categories()->whereKey($categoryId)->exists(),
            422,
            'The selected category is invalid.',
        );
    }
}
