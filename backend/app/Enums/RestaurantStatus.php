<?php

declare(strict_types=1);

namespace App\Enums;

enum RestaurantStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';

    /**
     * All values as a plain array (useful for migrations / validation rules).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether the restaurant is operational.
     */
    public function isOperational(): bool
    {
        return $this === self::Active;
    }
}
