<?php

namespace App\Http\Controllers\Api\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile details.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return ApiResponse::success(
            new UserResource($user->load('roles')),
            'Profile updated successfully.'
        );
    }

    /**
     * Change the authenticated user's password. All other sessions are revoked;
     * the current token is preserved so the user stays signed in.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Null when the request isn't authenticated by a physical token
        // (e.g. session guard); in that case simply revoke every token.
        $currentTokenId = $user->currentAccessToken()?->getKey();

        $user->forceFill([
            'password' => Hash::make($request->validated()['password']),
        ])->save();

        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        return ApiResponse::success(null, 'Password changed successfully.');
    }
}
