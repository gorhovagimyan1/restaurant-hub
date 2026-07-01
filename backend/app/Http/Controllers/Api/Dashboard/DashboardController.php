<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\RestaurantResource;
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
}
