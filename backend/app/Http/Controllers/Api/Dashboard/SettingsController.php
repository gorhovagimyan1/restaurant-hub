<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateSettingsRequest;
use App\Http\Resources\Dashboard\RestaurantSettingsResource;
use App\Models\RestaurantSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ResolvesRestaurant;

    /**
     * The current restaurant's operational settings.
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new RestaurantSettingsResource($this->settingsFor($request)),
        );
    }

    /**
     * Update the current restaurant's operational settings.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->settingsFor($request);
        $settings->update($request->validated());

        return ApiResponse::success(
            new RestaurantSettingsResource($settings),
            'Settings updated.',
        );
    }

    /**
     * The settings row for the current restaurant, created with defaults if a
     * restaurant somehow has none yet.
     */
    private function settingsFor(Request $request): RestaurantSettings
    {
        $restaurant = $this->currentRestaurant($request);

        $settings = $restaurant->settings()->firstOrCreate([
            'restaurant_id' => $restaurant->id,
        ]);

        // A freshly-created row doesn't reflect the columns' DB defaults in
        // memory (they come back null); reload so the response carries them.
        if ($settings->wasRecentlyCreated) {
            $settings->refresh();
        }

        return $settings;
    }
}
