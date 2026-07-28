<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Http\Resources\Admin\RoleResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role & permission administration for platform super-admins: view the
 * role/permission matrix and edit which permissions each role grants.
 */
class RoleController extends Controller
{
    /**
     * The roles and the permission catalog that make up the editable matrix.
     */
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('id')
            ->get();

        $this->attachUserCounts($roles);

        return ApiResponse::success([
            'roles' => RoleResource::collection($roles),
            'permissions' => PermissionEnum::catalog(),
        ], 'Roles retrieved.');
    }

    /**
     * Replace the set of permissions a role grants. The super-admin role is
     * locked — it is granted everything via a Gate::before hook, so editing its
     * (unused) permission rows is disallowed.
     */
    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        abort_if(
            $role->name === RoleEnum::SuperAdmin->value,
            403,
            'The super-admin role has full access and cannot be edited.',
        );

        $role->syncPermissions($request->validated()['permissions']);

        // Spatie caches the permission map; clear it so the change takes effect
        // immediately for already-authenticated users.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = $role->fresh()->load('permissions');
        $this->attachUserCounts(new Collection([$role]));

        return ApiResponse::success(
            new RoleResource($role),
            'Role permissions updated.',
        );
    }

    /**
     * Populate a `users_count` attribute on each role.
     *
     * Spatie's Role::users() relation resolves its related model from the
     * role's guard, which a `withCount('users')` sub-query cannot supply — so
     * we count the pivot rows directly instead.
     *
     * @param  Collection<int, Role>  $roles
     */
    private function attachUserCounts(Collection $roles): void
    {
        $counts = DB::table(config('permission.table_names.model_has_roles'))
            ->selectRaw('role_id, count(*) as aggregate')
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id');

        $roles->each(fn (Role $role) => $role->setAttribute(
            'users_count',
            (int) ($counts[$role->id] ?? 0),
        ));
    }
}
