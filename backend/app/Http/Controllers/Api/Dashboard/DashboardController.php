<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateRestaurantRequest;
use App\Http\Resources\Dashboard\RestaurantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesRestaurant;

    /**
     * The restaurant the authenticated user manages.
     */
    public function restaurant(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new RestaurantResource($this->currentRestaurant($request)),
        );
    }

    /**
     * Update the current restaurant's profile.
     */
    public function updateRestaurant(UpdateRestaurantRequest $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $restaurant->update($request->validated());

        return ApiResponse::success(
            new RestaurantResource($restaurant),
            'Restaurant profile updated.',
        );
    }
}
