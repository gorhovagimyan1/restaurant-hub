<?php

namespace App\Console\Commands;

use App\Services\Stock\StockService;
use Illuminate\Console\Command;

/**
 * Refills products that have a daily quantity (e.g. "20 cheesecakes a day")
 * when a new business day starts in the restaurant's timezone. Safe to run as
 * often as you like: each product is refilled once per day.
 */
class ResetDailyStock extends Command
{
    protected $signature = 'stock:reset-daily';

    protected $description = 'Refill products with a daily stock quantity at the start of the business day';

    public function handle(StockService $stock): int
    {
        $this->info("Refilled {$stock->resetDaily()} product(s).");

        return self::SUCCESS;
    }
}
