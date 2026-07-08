<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DiningSession;
use App\Models\RestaurantTable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared guard for the public QR flow: an action (order, bill, service call)
 * is only allowed while the table has an OPEN dining session and the caller
 * presents that session's token. Once staff settle the bill the session
 * closes, so stale links are rejected and the guest must scan again.
 */
trait ResolvesDiningSession
{
    /**
     * Return the table's open session, or abort 409 telling the guest to
     * re-scan. When $sessionToken is given it must match the open session,
     * so a photographed QR from an earlier visit cannot ride the current one.
     */
    protected function requireOpenSession(RestaurantTable $table, ?string $sessionToken): DiningSession
    {
        $session = $table->openSession()->first();

        abort_if(
            $session === null,
            Response::HTTP_CONFLICT,
            'This table has no active dining session. Please scan the QR code again.',
        );

        abort_unless(
            $sessionToken !== null && hash_equals($session->session_token, $sessionToken),
            Response::HTTP_CONFLICT,
            'Your dining session has ended. Please scan the QR code again.',
        );

        // Any authorized interaction counts as activity, keeping the idle
        // auto-close job from closing a table that is still in use.
        $session->touchActivity();

        return $session;
    }
}
