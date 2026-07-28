<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RestaurantStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRestaurantStatusRequest;
use App\Http\Resources\Admin\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cross-tenant restaurant administration for platform super-admins.
 */
class RestaurantController extends Controller
{
    /**
     * List every restaurant on the platform, newest first, with a free-text
     * search over name / slug / email and an optional status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurants = Restaurant::query()
            ->with('users')
            ->withCount(['users', 'orders', 'tables', 'products'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')),
            )
            ->latest()
            ->get();

        return ApiResponse::success(
            RestaurantResource::collection($restaurants),
            'Restaurants retrieved.',
        );
    }

    /**
     * A single restaurant with its aggregate counts and owner.
     */
    public function show(Restaurant $restaurant): JsonResponse
    {
        $restaurant->load('users')
            ->loadCount(['users', 'orders', 'tables', 'products']);

        return ApiResponse::success(
            new RestaurantResource($restaurant),
            'Restaurant retrieved.',
        );
    }

    /**
     * Change a restaurant's platform status (e.g. suspend / re-activate).
     * A restaurant is operational only while Active, so the boolean flag is
     * kept in sync with the status.
     */
    public function updateStatus(UpdateRestaurantStatusRequest $request, Restaurant $restaurant): JsonResponse
    {
        $status = RestaurantStatus::from($request->validated()['status']);

        $restaurant->forceFill([
            'status' => $status->value,
            'is_active' => $status->isOperational(),
        ])->save();

        return ApiResponse::success(
            new RestaurantResource($restaurant->fresh()->load('users')),
            'Restaurant status updated.',
        );
    }

    /**
     * Soft-delete a restaurant, removing it from the platform.
     */
    public function destroy(Restaurant $restaurant): JsonResponse
    {
        $restaurant->delete();

        return ApiResponse::success(null, 'Restaurant removed.');
    }
}
