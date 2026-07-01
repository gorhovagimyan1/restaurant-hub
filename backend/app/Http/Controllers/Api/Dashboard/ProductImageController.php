<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    use ResolvesRestaurant;

    /**
     * Upload an image for a product and make it the primary image.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $product);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $path = $request->file('image')->store('products', 'public');

        // New upload becomes the primary image.
        $product->images()->update(['is_primary' => false]);
        $product->images()->create([
            'image' => '/storage/'.$path,
            'is_primary' => true,
        ]);

        return ApiResponse::created(
            new ProductResource($product->load('images')),
            'Image uploaded.',
        );
    }

    /**
     * Delete a product image.
     */
    public function destroy(Request $request, ProductImage $productImage): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $productImage);

        // Remove the stored file when it lives on our public disk.
        if (str_starts_with((string) $productImage->image, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $productImage->image));
        }

        $product = $productImage->product;
        $productImage->delete();

        // Promote another image to primary if we removed the primary one.
        if (! $product->images()->where('is_primary', true)->exists()) {
            $product->images()->oldest()->first()?->update(['is_primary' => true]);
        }

        return ApiResponse::success(
            new ProductResource($product->load('images')),
            'Image deleted.',
        );
    }
}
