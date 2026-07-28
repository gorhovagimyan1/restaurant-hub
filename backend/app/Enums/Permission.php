<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    case ManageRestaurant = 'restaurant.manage';
    case ManageEmployees = 'employees.manage';
    case ManageTables = 'tables.manage';
    case ManageMenus = 'menus.manage';
    case ManageCategories = 'categories.manage';
    case ManageProducts = 'products.manage';
    case ManageOrders = 'orders.manage';
    case ViewOrders = 'orders.view';
    case UpdateOrderStatus = 'orders.update-status';
    case ViewReports = 'reports.view';
    case ManageSettings = 'settings.manage';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManageRestaurant => 'Manage restaurant profile',
            self::ManageEmployees => 'Manage employees',
            self::ManageTables => 'Manage tables & QR codes',
            self::ManageMenus => 'Manage menus',
            self::ManageCategories => 'Manage categories',
            self::ManageProducts => 'Manage products',
            self::ManageOrders => 'Manage orders',
            self::ViewOrders => 'View orders',
            self::UpdateOrderStatus => 'Update order status',
            self::ViewReports => 'View reports',
            self::ManageSettings => 'Manage settings',
        };
    }

    /**
     * A coarse grouping used to lay the permission matrix out by area.
     */
    public function group(): string
    {
        return match ($this) {
            self::ManageRestaurant, self::ManageSettings, self::ManageEmployees => 'Restaurant',
            self::ManageMenus, self::ManageCategories, self::ManageProducts => 'Menu',
            self::ManageTables => 'Tables',
            self::ManageOrders, self::ViewOrders, self::UpdateOrderStatus => 'Orders',
            self::ViewReports => 'Reports',
        };
    }

    /**
     * Every permission as display metadata, in declaration order.
     *
     * @return array<int, array{value: string, label: string, group: string}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $permission) => [
            'value' => $permission->value,
            'label' => $permission->label(),
            'group' => $permission->group(),
        ], self::cases());
    }
}
