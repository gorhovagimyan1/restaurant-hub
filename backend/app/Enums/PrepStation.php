<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an order item is prepared, which decides the board it shows on.
 *
 * - Kitchen: cooked (food, desserts) — Kitchen Board.
 * - Bar: made by the bar or waiters (cocktails, coffee, fresh juice) — Bar Board.
 * - Ready: nothing to prepare (bottled drinks, a shot) — starts as ready, so
 *   the waiter only has to bring it; listed on the Bar Board for pick-up.
 */
enum PrepStation: string
{
    case Kitchen = 'kitchen';
    case Bar = 'bar';
    case Ready = 'ready';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The stations a staff board covers. Waiters run the Bar Board, which is
     * also where they pick up ready-to-serve items.
     *
     * @return array<int, self>
     */
    public static function forBoard(string $board): array
    {
        return match ($board) {
            'kitchen' => [self::Kitchen],
            'bar' => [self::Bar, self::Ready],
            default => [],
        };
    }
}
