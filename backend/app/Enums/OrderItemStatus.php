<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of an individual line item within an order. Lets the kitchen cook and
 * a waiter deliver each dish independently, rather than the whole order at once.
 */
enum OrderItemStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
