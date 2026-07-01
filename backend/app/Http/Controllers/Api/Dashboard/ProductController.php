<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreProductRequest;
use App\Http\Requests\Dashboard\UpdateProductRequest;
use App\Http\Resources\Dashboard\ProductResource;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ResolvesRestaurant;

    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        $products = $restaurant->products()
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->with('images')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ProductResource::collection($products));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->assertCategoryOwned($restaurant, $request->integer('category_id'));

        $product = $restaurant->products()->create($request->validated());

        return ApiResponse::created(
            new ProductResource($product->load('images')),
            'Product created.',
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $product);

        if ($request->filled('category_id')) {
            $this->assertCategoryOwned($restaurant, $request->integer('category_id'));
        }

        $product->update($request->validated());

        return ApiResponse::success(
            new ProductResource($product->load('images')),
            'Product updated.',
        );
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $product);

        $product->delete();

        return ApiResponse::success(null, 'Product deleted.');
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
