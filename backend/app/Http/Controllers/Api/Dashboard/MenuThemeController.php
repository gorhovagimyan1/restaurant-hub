<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateMenuThemeRequest;
use App\Models\RestaurantSettings;
use App\Support\MenuTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The design a restaurant applies to its own public menu.
 *
 * Kept apart from the operational settings endpoint: this is branding, edited
 * from the Menu Design screen, and read by the customer portal on every visit.
 */
class MenuThemeController extends Controller
{
    use ResolvesRestaurant;

    /**
     * The current restaurant's menu design, plus the presets to choose from.
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'theme' => $this->settingsFor($request)->menuTheme(),
            'presets' => MenuTheme::catalogue(),
        ]);
    }

    /**
     * Save the current restaurant's menu design.
     */
    public function update(UpdateMenuThemeRequest $request): JsonResponse
    {
        $settings = $this->settingsFor($request);

        // Normalise before storing so the column only ever holds a complete,
        // renderable theme — the customer portal never has to guess.
        $settings->update([
            'menu_theme' => MenuTheme::normalize($request->validated()),
        ]);

        return ApiResponse::success([
            'theme' => $settings->menuTheme(),
            'presets' => MenuTheme::catalogue(),
        ], 'Menu design updated.');
    }

    /**
     * Drop the customisation and go back to the default design.
     */
    public function destroy(Request $request): JsonResponse
    {
        $settings = $this->settingsFor($request);
        $settings->update(['menu_theme' => null]);

        return ApiResponse::success([
            'theme' => $settings->menuTheme(),
            'presets' => MenuTheme::catalogue(),
        ], 'Menu design reset.');
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

        if ($settings->wasRecentlyCreated) {
            $settings->refresh();
        }

        return $settings;
    }
}
