<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ISO-8601 day numbering (Monday = 1 … Sunday = 7), matching Carbon's
 * dayOfWeekIso so "is the restaurant open now?" checks line up.
 */
enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return ucfirst($this->name);
    }

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
