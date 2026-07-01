<?php

namespace App\Http\Controllers\Api\Public;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\CategoryResource;
use App\Http\Resources\Public\RestaurantResource;
use App\Models\Restaurant;
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
    public function show(Restaurant $restaurant): JsonResponse
    {
        abort_unless($restaurant->is_active, 404);

        $menu = $restaurant->menus()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        $categories = $menu
            ? $menu->categories()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->with(['products' => function ($query) {
                    $query->orderBy('sort_order')
                        ->orderBy('name')
                        ->with('images');
                }])
                ->get()
            : collect();

        return ApiResponse::success([
            'restaurant' => new RestaurantResource($restaurant),
            'categories' => CategoryResource::collection($categories),
        ], 'Menu retrieved successfully.');
    }
}
