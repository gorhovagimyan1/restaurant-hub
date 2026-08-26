<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\SubscriptionPayment;

/**
 * What a gateway hands back when checkout starts.
 *
 * Providers differ in how they finish: a hosted checkout sends the owner off
 * to a URL, a manual transfer just shows instructions. The client branches on
 * `action` rather than on which gateway is configured.
 */
final readonly class CheckoutResult
{
    private function __construct(
        public string $action,
        public SubscriptionPayment $payment,
        public ?string $redirectUrl = null,
        public ?string $instructions = null,
    ) {}

    /** Send the owner to the provider's hosted checkout. */
    public static function redirect(SubscriptionPayment $payment, string $url): self
    {
        return new self('redirect', $payment, redirectUrl: $url);
    }

    /** Show the owner how to pay out of band; someone confirms it later. */
    public static function instructions(SubscriptionPayment $payment, string $instructions): self
    {
        return new self('instructions', $payment, instructions: $instructions);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'action' => $this->action,
            'payment_id' => $this->payment->id,
            'redirect_url' => $this->redirectUrl,
            'instructions' => $this->instructions,
        ], static fn ($value) => $value !== null);
    }
}
