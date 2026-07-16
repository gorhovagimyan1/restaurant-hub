<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\RestaurantStatus;
use App\Enums\Role;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Dashboard\RestaurantResource;
use App\Http\Resources\UserResource;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new account. Self-registration provisions a fresh tenant: a
     * Restaurant Owner plus their restaurant, its default settings, and their
     * membership — so they can start managing it immediately.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        [$user, $restaurant] = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);
            $user->assignRole(Role::RestaurantOwner->value);

            $restaurant = Restaurant::create([
                'name' => $data['restaurant_name'],
                'slug' => $this->uniqueSlug($data['restaurant_name']),
                'currency' => 'AMD',
                'timezone' => 'Asia/Yerevan',
                'status' => RestaurantStatus::Active->value,
                'is_active' => true,
            ]);
            $restaurant->settings()->create([]);
            $restaurant->users()->attach($user->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);

            return [$user, $restaurant];
        });

        $token = $user->createToken('auth')->plainTextToken;

        return ApiResponse::created([
            'user' => new UserResource($user->load('roles')),
            'restaurant' => new RestaurantResource($restaurant),
            'token' => $token,
        ], 'Registration successful.');
    }

    /**
     * Build a URL-safe, unique slug from the restaurant name.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'restaurant';
        $slug = $base;
        $suffix = 2;

        while (Restaurant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Authenticate and issue an API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return ApiResponse::forbidden('Your account has been disabled.');
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token = $user->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ], 'Login successful.');
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load('roles'))
        );
    }
}
