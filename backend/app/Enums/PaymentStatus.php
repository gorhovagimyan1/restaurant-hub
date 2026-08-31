<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    /** Awaiting the money — a bank transfer, or a provider's checkout. */
    case Pending = 'pending';

    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }
}
