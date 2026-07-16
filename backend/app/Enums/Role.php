<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super-admin';
    case RestaurantOwner = 'restaurant-owner';
    case RestaurantManager = 'restaurant-manager';
    case Waiter = 'waiter';
    case KitchenStaff = 'kitchen-staff';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::RestaurantOwner => 'Restaurant Owner',
            self::RestaurantManager => 'Restaurant Manager',
            self::Waiter => 'Waiter',
            self::KitchenStaff => 'Kitchen Staff',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Roles an owner/manager may assign to staff they invite. Deliberately
     * excludes platform (super-admin) and ownership roles, which are not
     * grantable through employee management.
     *
     * @return array<int, self>
     */
    public static function staffAssignable(): array
    {
        return [
            self::RestaurantManager,
            self::Waiter,
            self::KitchenStaff,
        ];
    }

    /**
     * The string values of {@see self::staffAssignable()}.
     *
     * @return array<int, string>
     */
    public static function staffAssignableValues(): array
    {
        return array_map(fn (self $role) => $role->value, self::staffAssignable());
    }
}
