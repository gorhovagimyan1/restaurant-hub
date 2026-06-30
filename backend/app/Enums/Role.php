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
}
