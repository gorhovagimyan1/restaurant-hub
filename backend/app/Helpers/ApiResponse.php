<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard envelope for every API response.
 *
 * Success: { "success": true,  "message": "...", "data": {} }
 * Error:   { "success": false, "message": "...", "errors": {} }
 */
class ApiResponse
{
    /**
     * A successful response carrying optional data.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success.',
        int $status = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * A 201 response for a newly created resource.
     */
    public static function created(
        mixed $data = null,
        string $message = 'Resource created successfully.'
    ): JsonResponse {
        return static::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * A 204-style success with no data payload.
     */
    public static function noContent(string $message = 'Done.'): JsonResponse
    {
        return static::success(null, $message, Response::HTTP_OK);
    }

    /**
     * A generic error response.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function error(
        string $message = 'Something went wrong.',
        array $errors = [],
        int $status = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            // Cast to object so an empty bag serializes as {} rather than [].
            'errors' => (object) $errors,
        ], $status);
    }

    /**
     * A 422 response for failed validation.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed.'
    ): JsonResponse {
        return static::error($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * A 404 response.
     */
    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return static::error($message, [], Response::HTTP_NOT_FOUND);
    }

    /**
     * A 401 response.
     */
    public static function unauthorized(string $message = 'Unauthenticated.'): JsonResponse
    {
        return static::error($message, [], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * A 403 response.
     */
    public static function forbidden(string $message = 'This action is unauthorized.'): JsonResponse
    {
        return static::error($message, [], Response::HTTP_FORBIDDEN);
    }
}
