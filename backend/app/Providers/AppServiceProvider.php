<?php

namespace App\Providers;

use App\Enums\Role;
use App\Services\Billing\ManualGateway;
use App\Services\Billing\PaymentGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
                throw new \InvalidArgumentException(
                    "Unknown billing gateway [{$name}]. Known: ".implode(', ', array_keys(self::GATEWAYS)).'.',
                );
            }

            return $factory();
        });
    }

    private static function makeManualGateway(): ManualGateway
    {
        return new ManualGateway(config('billing.manual_instructions'));
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
