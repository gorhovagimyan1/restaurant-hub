<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Payments cannot be taken right now for a reason the customer can do nothing
 * about: an unknown gateway name, missing API keys, or the provider being
 * unreachable.
 *
 * Distinct from a payment being *declined*, which is the customer's business
 * and is reported by the gateway itself.
 */
class BillingUnavailable extends RuntimeException
{
    public static function unknownGateway(string $name, array $known): self
    {
        return new self(
            "Unknown billing gateway [{$name}]. Known: ".implode(', ', $known).'.',
        );
    }

    public static function missingConfiguration(string $detail): self
    {
        return new self($detail);
    }
}
