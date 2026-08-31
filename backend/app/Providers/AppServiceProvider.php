<?php

namespace App\Providers;

use App\Enums\Role;
use App\Exceptions\BillingUnavailable;
use App\Services\Billing\ManualGateway;
use App\Services\Billing\PaymentGateway;
use App\Services\Billing\SandboxGateway;
use App\Services\Billing\StripeGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Payment gateways this application knows how to build, keyed by the value
     * of `billing.gateway`. Add a provider here and switch the config — no
     * caller ever names a gateway directly.
     *
     * @var array<string, callable>
     */
    private const GATEWAYS = [
        'manual' => [self::class, 'makeManualGateway'],
        'stripe' => [self::class, 'makeStripeGateway'],
        // Local development only; refuses to run elsewhere.
        'sandbox' => [self::class, 'makeSandboxGateway'],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function () {
            $name = (string) config('billing.gateway', 'manual');

            $factory = self::GATEWAYS[$name] ?? null;

            if ($factory === null) {
                throw BillingUnavailable::unknownGateway($name, array_keys(self::GATEWAYS));
            }

            return $factory();
        });
    }

    private static function makeManualGateway(): ManualGateway
    {
        return new ManualGateway(config('billing.manual_instructions'));
    }

    private static function makeSandboxGateway(): SandboxGateway
    {
        return new SandboxGateway(
            rtrim((string) config('billing.frontend_url'), '/').'/sandbox-pay',
            (string) app()->environment(),
        );
    }

    private static function makeStripeGateway(): StripeGateway
    {
        $secret = (string) config('billing.stripe.secret');

        // Failing here beats a confusing Stripe error on the first checkout.
        if ($secret === '') {
            throw BillingUnavailable::missingConfiguration(
                'BILLING_GATEWAY is "stripe" but STRIPE_SECRET is not set.',
            );
        }

        return new StripeGateway(
            new StripeClient($secret),
            (string) config('billing.stripe.success_url'),
            (string) config('billing.stripe.cancel_url'),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin bypasses all permission checks.
        Gate::before(function ($user, string $ability) {
            return $user->hasRole(Role::SuperAdmin->value) ? true : null;
        });
    }
}
