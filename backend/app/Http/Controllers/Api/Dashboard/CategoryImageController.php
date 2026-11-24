<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A category's photo, uploaded from the owner's device.
 */
class CategoryImageController extends Controller
{
    use ResolvesRestaurant;

    public function store(Request $request, Category $category): JsonResponse
    {
        $this->guardTenant($this->currentRestaurant($request), $category);

        $request->validate([
            // `image` + `mimes` check the file's real content, not its name,
            // so only genuine JPEG/PNG/WebP images get through.
            'image' => ['bail', 'required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $previous = $category->image;
        $path = $request->file('image')->store('categories', 'public');

        $category->update(['image' => '/storage/'.$path]);
        $this->forget($previous);

        return ApiResponse::success(
            new CategoryResource($category->load('preset')->loadCount('products')),
            'Image uploaded.',
        );
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->guardTenant($this->currentRestaurant($request), $category);

        $previous = $category->image;
        $category->update(['image' => null]);
        $this->forget($previous);

        return ApiResponse::success(
            new CategoryResource($category->load('preset')->loadCount('products')),
            'Image deleted.',
        );
    }

    /** Remove a file we stored; external URLs (old data) are left alone. */
    private function forget(?string $image): void
    {
        if ($image && str_starts_with($image, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $image));
        }
    }
}
