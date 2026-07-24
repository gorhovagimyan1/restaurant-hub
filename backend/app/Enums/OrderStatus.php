<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

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
     * Whether the order has reached a terminal state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Statuses this one may be moved to by staff.
     *
     * The service only ever runs forwards: an order can skip ahead (the
     * "mark everything ready" shortcut) but never go back a step, and a
     * final order cannot be reopened. Cancelling stays available right up
     * until the bill is settled.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Preparing, self::Ready, self::Served, self::Completed, self::Cancelled],
            self::Accepted => [self::Preparing, self::Ready, self::Served, self::Completed, self::Cancelled],
            self::Preparing => [self::Ready, self::Served, self::Completed, self::Cancelled],
            self::Ready => [self::Served, self::Completed, self::Cancelled],
            self::Served => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    /**
     * Whether staff may move an order from this status to $target. Re-applying
     * the current status is allowed so a double-tap is a harmless no-op.
     */
    public function canTransitionTo(self $target): bool
    {
        return $target === $this || in_array($target, $this->allowedTransitions(), true);
    }
}
