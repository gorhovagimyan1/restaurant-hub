<?php

namespace App\Console\Commands;

use App\Services\Billing\PaymentGateway;
use Illuminate\Console\Command;

/**
 * Reports whether billing is actually configured.
 *
 * A misconfigured gateway only shows up as "Payments are not available right
 * now" on the checkout screen, with the reason buried in the log. This puts
 * the reason where whoever is doing the configuring will look.
 */
class BillingStatus extends Command
{
    protected $signature = 'billing:status';

    protected $description = 'Show which payment gateway is configured and whether it can take payments';

    public function handle(): int
    {
        $configured = (string) config('billing.gateway');

        $this->newLine();
        $this->line("  <options=bold>Billing configuration</>");
        $this->newLine();

        $rows = [
            ['BILLING_GATEWAY', $configured ?: '<fg=red>(not set)</>'],
            ['BILLING_TRIAL_DAYS', (string) config('billing.trial_days')],
            ['Environment', (string) app()->environment()],
        ];

        if ($configured === 'stripe') {
            $rows[] = ['STRIPE_SECRET', config('billing.stripe.secret')
                ? '<fg=green>set</>'
                : '<fg=red>EMPTY — payments will fail</>'];
            $rows[] = ['STRIPE_WEBHOOK_SECRET', config('billing.stripe.webhook_secret')
                ? '<fg=green>set</>'
                : '<fg=yellow>empty — cards will charge but access will not open</>'];
        }

        $this->table(['Setting', 'Value'], $rows);

        try {
            $gateway = app(PaymentGateway::class);
        } catch (\Throwable $e) {
            $this->error('  Payments are NOT working: '.$e->getMessage());
            $this->newLine();
            $this->line('  Fix the setting above, then run <options=bold>php artisan config:clear</>.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('  Payments are working — active gateway: '.$gateway->name());

        if ($gateway->name() === 'sandbox') {
            $this->newLine();
            $this->warn('  This is the test gateway: any card is accepted and no money moves.');
            $this->line('  Set BILLING_GATEWAY=stripe (plus STRIPE_SECRET) to take real payments.');
        }

        if ($gateway->name() === 'stripe' && ! config('billing.stripe.webhook_secret')) {
            $this->newLine();
            $this->warn('  No webhook secret. Payments will be taken but subscriptions will');
            $this->line('  never activate. Run:');
            $this->line('    stripe listen --forward-to localhost:8000/api/webhooks/stripe');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
