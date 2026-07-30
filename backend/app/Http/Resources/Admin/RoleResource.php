<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * A role with the permissions it grants, for the platform roles matrix.
 *
 * The super-admin role is "locked": it is granted every ability through a
 * Gate::before hook rather than explicit permission rows, so its permissions
 * are reported as the full set and it cannot be edited.
 *
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSuperAdmin = $this->name === RoleEnum::SuperAdmin->value;

        $roleEnum = RoleEnum::tryFrom($this->name);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $roleEnum?->label() ?? $this->name,
            'is_locked' => $isSuperAdmin,
            'users_count' => $this->whenCounted('users'),
            'permissions' => $isSuperAdmin
                ? Permission::values()
                : $this->permissions->pluck('name')->values(),
        ];
    }
}
