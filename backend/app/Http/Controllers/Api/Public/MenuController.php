<?php

namespace App\Http\Controllers\Api\Public;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\CategoryResource;
use App\Http\Resources\Public\ProductResource;
use App\Http\Resources\Public\RestaurantResource;
use App\Models\Restaurant;
use App\Services\Reviews\BestChoices;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    /**
     * Return the full public menu for a restaurant: its active menu's
     * categories (ordered), each with its available products and images.
     *
     * This is a guest-accessible endpoint used by the customer portal after
     * scanning a table QR code.
     */
    public function show(Restaurant $restaurant, BestChoices $bestChoices): JsonResponse
    {
        abort_unless($restaurant->is_active, 404);

        $restaurant->load([
            'businessHours',
            // Carries the menu design the portal renders with.
            'settings',
            // Only forthcoming overrides matter to a customer viewing the menu.
            'specialHours' => fn ($query) => $query->whereDate('date', '>=', now()),
        ]);

        $menu = $restaurant->menus()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        $categories = $menu
            ? $menu->categories()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->with(['preset', 'products' => function ($query) {
                    $query->orderBy('sort_order')
                        ->orderBy('name')
                        ->with('images')
                        ->withAvg('reviews', 'rating')
                        ->withCount('reviews');
                }])
                ->get()
            : collect();

        return ApiResponse::success([
            'restaurant' => new RestaurantResource($restaurant),
            'categories' => CategoryResource::collection($categories),
            // Top-rated dishes, for a "Best choices" section at the top of the
            // menu. They still appear in their own categories too.
            'best_choices' => ProductResource::collection(
                $bestChoices->pick($categories->flatMap->products),
            ),
        ], 'Menu retrieved successfully.');
    }

    /**
     * Lightweight open/closed status for a restaurant — cheap enough for the
     * customer portal to poll without re-fetching the whole menu.
     */
    public function status(Restaurant $restaurant): JsonResponse
    {
        abort_unless($restaurant->is_active, 404);

        return ApiResponse::success($restaurant->openStatus(), 'Status retrieved.');
    }
}
