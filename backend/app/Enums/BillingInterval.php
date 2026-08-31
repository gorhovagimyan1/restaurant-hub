<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Plan;
use Carbon\CarbonInterface;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

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
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * The price a plan charges for this interval.
     */
    public function priceOn(Plan $plan): float
    {
        return (float) match ($this) {
            self::Monthly => $plan->monthly_price,
            self::Yearly => $plan->yearly_price,
        };
    }

    /**
     * Advance a moment by one billing period.
     *
     * Calendar-aware rather than a fixed day count, so a subscription started
     * on the 31st renews on month-ends and a yearly one lands on the same date.
     */
    public function advance(CarbonInterface $from): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $from->copy()->addMonthNoOverflow(),
            self::Yearly => $from->copy()->addYear(),
        };
    }
}
