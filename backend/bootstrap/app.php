<?php

use App\Helpers\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render every exception on API/JSON requests using the standard envelope.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // Fall back to default (HTML) handling for web routes.
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::validationError($e->errors()),
                $e instanceof AuthenticationException => ApiResponse::unauthorized(),
                $e instanceof AuthorizationException => ApiResponse::forbidden(),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::notFound(),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() ?: 'HTTP error.',
                    [],
                    $e->getStatusCode()
                ),
                default => ApiResponse::error(
                    config('app.debug') ? $e->getMessage() : 'Server error.',
                    [],
                    Response::HTTP_INTERNAL_SERVER_ERROR
                ),
            };
        });
    })->create();
