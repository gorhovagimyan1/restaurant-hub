<?php

declare(strict_types=1);

namespace App\Enums;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Disabled = 'disabled';

    /**
     * All values as a plain array (useful for migrations / validation rules).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
