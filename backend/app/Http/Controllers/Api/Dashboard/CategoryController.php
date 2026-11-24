<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreCategoryRequest;
use App\Http\Requests\Dashboard\UpdateCategoryRequest;
use App\Http\Resources\Dashboard\CategoryResource;
use App\Models\Category;
use App\Models\CategoryPreset;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ResolvesRestaurant;

    public function index(Request $request): JsonResponse
    {
        $categories = $this->currentRestaurant($request)
            ->categories()
            ->with('preset')
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(CategoryResource::collection($categories));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $data = $this->withPreset($restaurant, $request->validated());

        $category = $restaurant->categories()->make($data);
        $category->menu_id = $this->defaultMenu($restaurant)->id;

        // Append new categories at the end of the list by default.
        if (! isset($data['sort_order'])) {
            $category->sort_order = ((int) $restaurant->categories()->max('sort_order')) + 1;
        }

        $category->save();

        return ApiResponse::created(
            new CategoryResource($category->loadCount('products')),
            'Category created.',
        );
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $category);

        $category->update($this->withPreset($restaurant, $request->validated(), $category));

        return ApiResponse::success(
            new CategoryResource($category->loadCount('products')),
            'Category updated.',
        );
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $category);

        $category->delete();

        return ApiResponse::success(null, 'Category deleted.');
    }

    /**
     * A category picked from the catalog takes its name and description from
     * there (stored in the restaurant's language as a plain-text fallback) and
     * needs no translations of its own. Each catalog option can be used once
     * per restaurant.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withPreset(\App\Models\Restaurant $restaurant, array $data, ?Category $category = null): array
    {
        if (empty($data['category_preset_id'])) {
            return $data;
        }

        $preset = CategoryPreset::findOrFail($data['category_preset_id']);

        abort_if(
            $restaurant->categories()
                ->where('category_preset_id', $preset->id)
                ->when($category, fn ($q) => $q->whereKeyNot($category->id))
                ->exists(),
            422,
            'Your menu already has this category.',
        );

        $locale = $restaurant->settings?->default_language ?? 'en';

        return [
            ...$data,
            'name' => $preset->text('name', $locale),
            'description' => $preset->text('description', $locale),
            'translations' => null,
        ];
    }

    /**
     * The restaurant's active menu, created on demand if it has none.
     */
    private function defaultMenu(\App\Models\Restaurant $restaurant): Menu
    {
        return $restaurant->menus()->orderBy('sort_order')->first()
            ?? $restaurant->menus()->create([
                'name' => 'Main Menu',
                'is_active' => true,
                'sort_order' => 0,
            ]);
    }
}
