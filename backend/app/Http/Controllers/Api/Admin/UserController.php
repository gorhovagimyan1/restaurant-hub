<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cross-tenant user administration for platform super-admins.
 */
class UserController extends Controller
{
    /**
     * List every user account, with a free-text search over name / email and
     * an optional role filter.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['roles', 'restaurants'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when(
                $request->filled('role'),
                fn ($query) => $query->whereHas(
                    'roles',
                    fn ($q) => $q->where('name', $request->string('role')),
                ),
            )
            ->orderBy('first_name')
            ->get();

        return ApiResponse::success(
            UserResource::collection($users),
            'Users retrieved.',
        );
    }

    /**
     * Activate or deactivate a user account. A deactivated user loses their
     * active sessions and can no longer sign in.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        abort_if(
            $user->id === $request->user()->id,
            403,
            'You cannot change your own account status.',
        );

        $isActive = $request->validated()['is_active'];

        $user->forceFill(['is_active' => $isActive])->save();

        if (! $isActive) {
            $user->tokens()->delete();
        }

        return ApiResponse::success(
            new UserResource($user->fresh()->load(['roles', 'restaurants'])),
            'User status updated.',
        );
    }
}
