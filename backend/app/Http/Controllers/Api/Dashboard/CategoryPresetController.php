<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CategoryPresetResource;
use App\Models\CategoryPreset;
use Illuminate\Http\JsonResponse;

/**
 * The category catalog as offered to restaurants: active options only.
 */
class CategoryPresetController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            CategoryPresetResource::collection(CategoryPreset::ordered()->where('is_active', true)->get()),
            'Category catalog retrieved.',
        );
    }
}
