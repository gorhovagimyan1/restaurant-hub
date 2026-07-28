<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to platform super-admins.
 *
 * Permission-based gating (`can:...`) is unsuitable for the platform area
 * because restaurant owners also hold every permission for their own tenant;
 * only the super-admin role should reach cross-tenant administration.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->hasRole(Role::SuperAdmin->value),
            Response::HTTP_FORBIDDEN,
            'This area is restricted to platform administrators.',
        );

        return $next($request);
    }
}
