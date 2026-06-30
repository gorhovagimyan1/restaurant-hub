<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Start from a clean cache so re-seeding is deterministic.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create every permission.
        foreach (PermissionEnum::values() as $permission) {
            Permission::findOrCreate($permission);
        }

        // Create roles and grant their permissions.
        foreach ($this->rolePermissions() as $role => $permissions) {
            Role::findOrCreate($role)->syncPermissions($permissions);
        }
    }

    /**
     * Permission set granted to each role.
     *
     * Super Admin is intentionally omitted here — it is granted every ability
     * via a Gate::before hook (see AppServiceProvider), so it never needs
     * explicit permission rows.
     *
     * @return array<string, array<int, string>>
     */
    private function rolePermissions(): array
    {
        $all = PermissionEnum::values();

        return [
            RoleEnum::SuperAdmin->value => $all,

            RoleEnum::RestaurantOwner->value => $all,

            RoleEnum::RestaurantManager->value => [
                PermissionEnum::ManageEmployees->value,
                PermissionEnum::ManageTables->value,
                PermissionEnum::ManageMenus->value,
                PermissionEnum::ManageCategories->value,
                PermissionEnum::ManageProducts->value,
                PermissionEnum::ManageOrders->value,
                PermissionEnum::ViewOrders->value,
                PermissionEnum::UpdateOrderStatus->value,
                PermissionEnum::ViewReports->value,
            ],

            RoleEnum::Waiter->value => [
                PermissionEnum::ViewOrders->value,
                PermissionEnum::UpdateOrderStatus->value,
            ],

            RoleEnum::KitchenStaff->value => [
                PermissionEnum::ViewOrders->value,
                PermissionEnum::UpdateOrderStatus->value,
            ],
        ];
    }
}
