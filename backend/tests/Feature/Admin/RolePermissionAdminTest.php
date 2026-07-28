<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole(RoleEnum::SuperAdmin->value);
    }

    private function owner(): User
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $owner = User::factory()->create();
        $owner->assignRole(RoleEnum::RestaurantOwner->value);
        $restaurant->users()->attach($owner->id, ['is_active' => true, 'joined_at' => now()]);

        return $owner;
    }

    public function test_owner_is_forbidden_from_role_endpoints(): void
    {
        $this->actingAs($this->owner(), 'sanctum');

        $this->getJson('/api/admin/roles')->assertForbidden();
    }

    public function test_super_admin_can_read_the_role_matrix(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/roles')
            ->assertOk()
            ->assertJsonCount(count(RoleEnum::cases()), 'data.roles')
            ->assertJsonCount(count(Permission::cases()), 'data.permissions')
            ->assertJsonStructure([
                'data' => [
                    'roles' => [['id', 'name', 'label', 'is_locked', 'permissions']],
                    'permissions' => [['value', 'label', 'group']],
                ],
            ]);
    }

    public function test_super_admin_role_is_reported_as_locked_with_all_permissions(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $response = $this->getJson('/api/admin/roles')->assertOk();

        $superRole = collect($response->json('data.roles'))
            ->firstWhere('name', RoleEnum::SuperAdmin->value);

        $this->assertTrue($superRole['is_locked']);
        $this->assertEqualsCanonicalizing(Permission::values(), $superRole['permissions']);
    }

    public function test_super_admin_can_update_a_role_permissions(): void
    {
        $waiter = Role::findByName(RoleEnum::Waiter->value, 'web');

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/roles/{$waiter->id}/permissions", [
            'permissions' => [Permission::ViewOrders->value, Permission::ViewReports->value],
        ])->assertOk();

        $waiter = Role::findByName(RoleEnum::Waiter->value, 'web');
        $this->assertTrue($waiter->hasPermissionTo(Permission::ViewReports->value));
        $this->assertEqualsCanonicalizing(
            [Permission::ViewOrders->value, Permission::ViewReports->value],
            $waiter->permissions->pluck('name')->all(),
        );
    }

    public function test_super_admin_role_permissions_cannot_be_edited(): void
    {
        $super = Role::findByName(RoleEnum::SuperAdmin->value, 'web');

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/roles/{$super->id}/permissions", [
            'permissions' => [Permission::ViewOrders->value],
        ])->assertForbidden();
    }

    public function test_role_permission_update_rejects_unknown_permission(): void
    {
        $waiter = Role::findByName(RoleEnum::Waiter->value, 'web');

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/roles/{$waiter->id}/permissions", [
            'permissions' => ['orders.teleport'],
        ])->assertStatus(422);
    }

    public function test_super_admin_can_reassign_a_user_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Waiter->value);

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/users/{$user->uuid}/roles", [
            'roles' => [RoleEnum::KitchenStaff->value],
        ])
            ->assertOk()
            ->assertJsonPath('data.roles', [RoleEnum::KitchenStaff->value]);

        $user = $user->fresh();
        $this->assertTrue($user->hasRole(RoleEnum::KitchenStaff->value));
        $this->assertFalse($user->hasRole(RoleEnum::Waiter->value));
    }

    public function test_super_admin_cannot_change_their_own_roles(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/users/{$this->superAdmin->uuid}/roles", [
            'roles' => [RoleEnum::Waiter->value],
        ])->assertForbidden();
    }

    public function test_user_role_update_rejects_unknown_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->superAdmin, 'sanctum');

        $this->patchJson("/api/admin/users/{$user->uuid}/roles", [
            'roles' => ['galactic-overlord'],
        ])->assertStatus(422);
    }
}
