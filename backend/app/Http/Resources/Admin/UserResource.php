<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user account as seen by a platform super-admin: identity, roles, and the
 * restaurant(s) they belong to.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()->values()),
            'is_active' => $this->is_active,
            'restaurants' => $this->whenLoaded('restaurants', fn () => $this->restaurants->map(fn ($r) => [
                'id' => $r->uuid,
                'name' => $r->name,
            ])->values()),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
