<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateSpecialHoursRequest;
use App\Http\Resources\Dashboard\SpecialHourResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpecialHoursController extends Controller
{
    use ResolvesRestaurant;

    /**
     * The restaurant's special days (holidays / overrides), earliest first.
     */
    public function index(Request $request): JsonResponse
    {
        $specialHours = $this->currentRestaurant($request)
            ->specialHours()
            ->get();

        return ApiResponse::success(
            SpecialHourResource::collection($specialHours),
            'Special hours retrieved.',
        );
    }

    /**
     * Replace the restaurant's special days with the supplied set.
     */
    public function update(UpdateSpecialHoursRequest $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        DB::transaction(function () use ($restaurant, $request) {
            $restaurant->specialHours()->delete();

            foreach ($request->validated()['special_hours'] as $day) {
                $closed = $day['is_closed'];

                $restaurant->specialHours()->create([
                    'date' => $day['date'],
                    'is_closed' => $closed,
                    // Times are irrelevant on a closed day — null them out.
                    'open_time' => $closed ? null : $day['open_time'],
                    'close_time' => $closed ? null : $day['close_time'],
                    'label' => $day['label'] ?? null,
                ]);
            }
        });

        return ApiResponse::success(
            SpecialHourResource::collection($restaurant->specialHours()->get()),
            'Special hours updated.',
        );
    }
}
