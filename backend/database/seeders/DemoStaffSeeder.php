<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Attaches a kitchen-staff and a waiter user to each restaurant so their
 * role-specific dashboards (kitchen display, orders board) have real logins.
 * Idempotent: reuses existing users and re-syncs the pivot.
 */
class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        Restaurant::query()->orderBy('id')->each(function (Restaurant $restaurant): void {
            $domain = str_replace('-', '', $restaurant->slug).'.test';

            $this->staff($restaurant, "kitchen@{$domain}", 'Kitchen', 'Staff', RoleEnum::KitchenStaff);
            $this->staff($restaurant, "waiter@{$domain}", 'Wait', 'Staff', RoleEnum::Waiter);

            $this->command?->info("Seeded staff for: {$restaurant->slug} (kitchen@{$domain}, waiter@{$domain})");
        });
    }

    private function staff(Restaurant $restaurant, string $email, string $first, string $last, RoleEnum $role): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $first,
                'last_name' => $last,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $user->syncRoles([$role->value]);

        $restaurant->users()->syncWithoutDetaching([
            $user->id => ['is_active' => true, 'joined_at' => now()],
        ]);
    }
}
