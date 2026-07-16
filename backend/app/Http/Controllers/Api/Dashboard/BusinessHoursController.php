<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\DayOfWeek;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateBusinessHoursRequest;
use App\Http\Resources\Dashboard\BusinessHourResource;
use App\Models\BusinessHour;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BusinessHoursController extends Controller
{
    use ResolvesRestaurant;

    /**
     * The full week of opening hours (always seven days, Monday first).
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        return ApiResponse::success(
            BusinessHourResource::collection($this->fullWeek($restaurant)),
            'Business hours retrieved.',
        );
    }

    /**
     * Replace the restaurant's weekly opening hours.
     */
    public function update(UpdateBusinessHoursRequest $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);

        DB::transaction(function () use ($restaurant, $request) {
            foreach ($request->validated()['hours'] as $hour) {
                $closed = $hour['is_closed'];

                $restaurant->businessHours()->updateOrCreate(
                    ['day_of_week' => $hour['day_of_week']],
                    [
                        'is_closed' => $closed,
                        // Times are irrelevant on a closed day — null them out.
                        'open_time' => $closed ? null : $hour['open_time'],
                        'close_time' => $closed ? null : $hour['close_time'],
                    ],
                );
            }
        });

        return ApiResponse::success(
            BusinessHourResource::collection($this->fullWeek($restaurant->fresh())),
            'Business hours updated.',
        );
    }

    /**
     * All seven weekdays for the restaurant, backfilling any day that has no
     * row yet with an unsaved "not set" default so the editor always shows a
     * complete week.
     *
     * @return Collection<int, BusinessHour>
     */
    private function fullWeek(Restaurant $restaurant): Collection
    {
        $existing = $restaurant->businessHours()->get()->keyBy(
            fn (BusinessHour $hour) => $hour->day_of_week->value,
        );

        return collect(DayOfWeek::cases())->map(
            fn (DayOfWeek $day) => $existing->get($day->value) ?? new BusinessHour([
                'restaurant_id' => $restaurant->id,
                'day_of_week' => $day->value,
                'is_closed' => false,
                'open_time' => null,
                'close_time' => null,
            ]),
        );
    }
}
