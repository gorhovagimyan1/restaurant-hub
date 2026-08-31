<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    /** Free evaluation period; full access, nothing paid yet. */
    case Trialing = 'trialing';

    /** Paid and inside the current billing period. */
    case Active = 'active';

    /** Payment is overdue. Reserved for real providers' dunning. */
    case PastDue = 'past_due';

    /** Owner cancelled; access continues to the end of the paid period. */
    case Cancelled = 'cancelled';

    /** Trial or paid period ran out. No access. */
    case Expired = 'expired';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether this status can grant access at all.
     *
     * Being in a permitting status is necessary but not sufficient — the
     * period end still has to be in the future. Subscription::hasAccess()
     * is the real check; this only rules out the hopeless cases.
     */
    public function mayGrantAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Payment overdue',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }
}
