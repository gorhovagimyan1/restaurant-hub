<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Currency amounts in the units payment providers expect.
 *
 * Stripe (and most gateways) take amounts in a currency's *minor* unit — cents,
 * luma, kopeks — except for a handful of currencies that have no minor unit at
 * all. Getting this wrong is not a rounding error: sending 149000 for a
 * hundred-based currency charges 1,490.00 instead of 149,000.
 */
final class Money
{
    /**
     * Currencies Stripe treats as having no minor unit, so the amount is
     * passed through unmultiplied.
     *
     * @var list<string>
     */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public static function isZeroDecimal(string $currency): bool
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true);
    }

    /**
     * A human amount (149000.00 AMD) as provider minor units (14900000).
     */
    public static function toMinorUnits(float $amount, string $currency): int
    {
        return self::isZeroDecimal($currency)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    /**
     * The inverse, for reading amounts back off a provider payload.
     */
    public static function fromMinorUnits(int $amount, string $currency): float
    {
        return self::isZeroDecimal($currency)
            ? (float) $amount
            : $amount / 100;
    }
}
