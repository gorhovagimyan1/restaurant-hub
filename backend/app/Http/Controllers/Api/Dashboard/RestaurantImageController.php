<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The restaurant's own imagery: the logo shown in the menu's top bar and the
 * cover photo behind its header.
 *
 * Kept apart from the profile endpoint because these are multipart uploads
 * whose old files have to be cleaned up on replace.
 */
class RestaurantImageController extends Controller
{
    use ResolvesRestaurant;

    /** Request type => model column. */
    private const COLUMNS = [
        'logo' => 'logo',
        'cover' => 'cover_image',
    ];

    /**
     * Upload (or replace) the restaurant's logo or cover photo.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', Rule::in(array_keys(self::COLUMNS))],
            // Covers are displayed large, so allow a heavier file than a logo.
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:6144'],
        ]);

        $restaurant = $this->currentRestaurant($request);
        $column = self::COLUMNS[$request->string('type')->value()];

        $previous = $restaurant->{$column};
        $path = $request->file('image')->store('restaurants', 'public');

        $restaurant->update([$column => '/storage/'.$path]);
        $this->forget($previous);

        return ApiResponse::success(
            new RestaurantResource($restaurant),
            $request->string('type')->value() === 'logo' ? 'Logo updated.' : 'Cover photo updated.',
        );
    }

    /**
     * Remove the restaurant's logo or cover photo.
     */
    public function destroy(Request $request, string $type): JsonResponse
    {
        abort_unless(isset(self::COLUMNS[$type]), 404);

        $restaurant = $this->currentRestaurant($request);
        $column = self::COLUMNS[$type];

        $previous = $restaurant->{$column};
        $restaurant->update([$column => null]);
        $this->forget($previous);

        return ApiResponse::success(
            new RestaurantResource($restaurant),
            $type === 'logo' ? 'Logo removed.' : 'Cover photo removed.',
        );
    }

    /**
     * Delete a replaced file, but only when it is one of ours — seeded demo
     * data and externally hosted images point elsewhere and must survive.
     */
    private function forget(?string $image): void
    {
        if ($image && str_starts_with($image, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $image));
        }
    }
}
