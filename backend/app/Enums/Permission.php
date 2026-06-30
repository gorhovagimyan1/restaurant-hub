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
}
