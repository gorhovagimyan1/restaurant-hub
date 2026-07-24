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

    /**
     * Statuses this one may be moved to by staff.
     *
     * Forward-only, mirroring the order-level machine. A dish can be written
     * off (cancelled) at any point — including after it was delivered, which
     * is what a comp looks like — but a cancelled line is not revivable.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Preparing, self::Ready, self::Served, self::Cancelled],
            self::Preparing => [self::Ready, self::Served, self::Cancelled],
            self::Ready => [self::Served, self::Cancelled],
            self::Served => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    /**
     * Whether staff may move an item from this status to $target. Re-applying
     * the current status is allowed so a double-tap is a harmless no-op.
     */
    public function canTransitionTo(self $target): bool
    {
        return $target === $this || in_array($target, $this->allowedTransitions(), true);
    }
}
