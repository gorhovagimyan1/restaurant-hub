<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\Role;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreEmployeeRequest;
use App\Http\Requests\Dashboard\UpdateEmployeeRequest;
use App\Http\Resources\Dashboard\EmployeeResource;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    use ResolvesRestaurant;

    /**
     * List the current restaurant's staff.
     */
    public function index(Request $request): JsonResponse
    {
        $employees = $this->currentRestaurant($request)
            ->users()
            ->with('roles')
            ->orderBy('users.first_name')
            ->get();

        return ApiResponse::success(
            EmployeeResource::collection($employees),
            'Employees retrieved.',
        );
    }

    /**
     * Invite a new employee: create their account, assign the chosen staff
     * role, attach them to the restaurant, and email a link to set a password.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $data = $request->validated();

        $employee = DB::transaction(function () use ($restaurant, $data) {
            $employee = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                // A placeholder password; the invitee sets their own via email.
                'password' => Hash::make(Str::random(40)),
                'is_active' => true,
            ]);

            $employee->assignRole($data['role']);

            $restaurant->users()->attach($employee->id, [
                'is_active' => true,
                'joined_at' => now(),
            ]);

            return $employee;
        });

        // Reuse the password-reset flow as a "set your password" invite link.
        Password::sendResetLink(['email' => $employee->email]);

        return ApiResponse::created(
            new EmployeeResource($employee->load('roles')),
            'Employee invited. A link to set their password has been emailed.',
        );
    }

    /**
     * Update an employee's role and active status.
     */
    public function update(UpdateEmployeeRequest $request, User $employee): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardEmployee($request, $restaurant, $employee);

        $data = $request->validated();

        DB::transaction(function () use ($restaurant, $employee, $data) {
            $employee->syncRoles([$data['role']]);

            $employee->forceFill(['is_active' => $data['is_active']])->save();
            $restaurant->users()->updateExistingPivot($employee->id, [
                'is_active' => $data['is_active'],
            ]);

            // A deactivated employee can no longer hold active sessions.
            if (! $data['is_active']) {
                $employee->tokens()->delete();
            }
        });

        return ApiResponse::success(
            new EmployeeResource($employee->fresh()->load('roles')),
            'Employee updated.',
        );
    }

    /**
     * Remove an employee from the restaurant. The account is soft-deleted once
     * it no longer belongs to any restaurant.
     */
    public function destroy(Request $request, User $employee): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardEmployee($request, $restaurant, $employee);

        DB::transaction(function () use ($restaurant, $employee) {
            $restaurant->users()->detach($employee->id);
            $employee->tokens()->delete();

            if ($employee->restaurants()->count() === 0) {
                $employee->delete();
            }
        });

        return ApiResponse::success(null, 'Employee removed.');
    }

    /**
     * Ensure the target is a manageable member of this restaurant: it must
     * belong to the tenant, must not be the acting user, and must not be an
     * owner (owners aren't managed through employee management).
     */
    private function guardEmployee(Request $request, Restaurant $restaurant, User $employee): void
    {
        abort_unless(
            $restaurant->users()->whereKey($employee->id)->exists(),
            404,
        );

        abort_if(
            $employee->id === $request->user()->id,
            403,
            'You cannot manage your own account here.',
        );

        abort_if(
            $employee->hasRole(Role::RestaurantOwner->value) || $employee->hasRole(Role::SuperAdmin->value),
            403,
            'Owners cannot be managed through employee management.',
        );
    }
}
