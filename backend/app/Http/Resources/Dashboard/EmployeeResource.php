<?php

namespace App\Http\Resources\Dashboard;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff member of the current restaurant, for the employee-management screen.
 *
 * @mixin User
 */
class EmployeeResource extends JsonResource
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
            // Employees carry a single staff role.
            'role' => $this->whenLoaded('roles', fn () => $this->getRoleNames()->first()),
            'is_active' => $this->is_active,
            'joined_at' => $this->whenPivotLoaded('restaurant_user', fn () => $this->pivot->joined_at),
            'last_login_at' => $this->last_login_at,
        ];
    }
}
