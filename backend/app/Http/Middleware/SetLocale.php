<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language from the request's Accept-Language header
 * (the SPA sends the visitor's chosen language), so API messages and
 * validation errors come back in English, Russian or Armenian.
 */
class SetLocale
{
    /** Locales with translations in lang/. The first is the fallback. */
    public const SUPPORTED = ['en', 'ru', 'hy'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED);

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : self::SUPPORTED[0]);

        return $next($request);
    }
}
