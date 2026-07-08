<?php

declare(strict_types=1);

namespace App\Enums;

enum DiningSessionStatus: string
{
    case Open = 'open';
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
}
