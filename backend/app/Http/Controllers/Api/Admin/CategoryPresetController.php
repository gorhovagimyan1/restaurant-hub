<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryPresetRequest;
use App\Http\Resources\Admin\CategoryPresetResource;
use App\Models\CategoryPreset;
use Illuminate\Http\JsonResponse;

/**
 * The platform's category catalog: menu sections restaurants pick from,
 * named in every language by super-admins.
 */
class CategoryPresetController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            CategoryPresetResource::collection(CategoryPreset::ordered()->withCount('categories')->get()),
            'Category catalog retrieved.',
        );
    }

    public function store(SaveCategoryPresetRequest $request): JsonResponse
    {
        $preset = CategoryPreset::create($request->validated());

        return ApiResponse::created(
            new CategoryPresetResource($preset->loadCount('categories')),
            'Catalog category created.',
        );
    }

    public function update(SaveCategoryPresetRequest $request, CategoryPreset $categoryPreset): JsonResponse
    {
        $categoryPreset->update($request->validated());

        return ApiResponse::success(
            new CategoryPresetResource($categoryPreset->loadCount('categories')),
            'Catalog category updated.',
        );
    }

    /**
     * Only unused options can be deleted; ones restaurants already use can be
     * hidden from the picker instead, without touching their menus.
     */
    public function destroy(CategoryPreset $categoryPreset): JsonResponse
    {
        abort_if(
            $categoryPreset->categories()->withTrashed()->exists(),
            422,
            'Restaurants use this category. Hide it instead of deleting it.',
        );

        $categoryPreset->delete();

        return ApiResponse::noContent('Catalog category deleted.');
    }
}
