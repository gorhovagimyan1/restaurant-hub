<?php

namespace App\Console\Commands;

use App\Enums\DiningSessionStatus;
use App\Models\DiningSession;
use Illuminate\Console\Command;

/**
 * Closes dining sessions that have been idle longer than the configured
 * timeout. A session normally closes when staff settle the bill; this is the
 * safety net for tables the guests simply walked away from, so an abandoned
 * session (and its still-valid QR link) doesn't stay open indefinitely.
 *
 * It only closes the session — orders and table occupancy are left untouched
 * so staff can still settle any unpaid bill.
 */
class CloseIdleDiningSessions extends Command
{
    protected $signature = 'sessions:close-idle
        {--hours= : Override the idle timeout (hours) from config/dining.php}';

    protected $description = 'Close dining sessions with no activity past the idle timeout';

    public function handle(): int
    {
        $hours = (float) ($this->option('hours') ?? config('dining.idle_timeout_hours'));

        if ($hours <= 0) {
            $this->error('Idle timeout must be greater than zero.');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes((int) round($hours * 60));

        $closed = 0;

        DiningSession::query()
            ->where('status', DiningSessionStatus::Open->value)
            ->where('last_activity_at', '<', $cutoff)
            ->each(function (DiningSession $session) use (&$closed): void {
                $session->close();
                $closed++;
            });

        $this->info("Closed {$closed} idle dining session(s) (idle > {$hours}h).");

        return self::SUCCESS;
    }
}
