<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>|stdClass  $details
     * @param  array<string, string|string[]>  $headers
     */
    public static function make(
        string $code,
        string $message,
        int $status,
        array|stdClass $details = new stdClass,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status, $headers);
    }

    public static function fromException(Response $response, Throwable $exception): JsonResponse
    {
        $status = $response->getStatusCode();
        $isUnverifiedEmail = $status === Response::HTTP_FORBIDDEN
            && $exception->getMessage() === 'Your email address is not verified.';

        $code = $isUnverifiedEmail ? 'email_unverified' : match ($status) {
            Response::HTTP_UNAUTHORIZED => 'unauthenticated',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'validation_failed',
            Response::HTTP_TOO_MANY_REQUESTS => 'rate_limited',
            default => $status >= 500 ? 'server_error' : 'request_failed',
        };

        $message = $isUnverifiedEmail ? 'Your email address must be verified.' : match ($status) {
            Response::HTTP_UNAUTHORIZED => 'Authentication is required.',
            Response::HTTP_FORBIDDEN => 'You are not authorized to perform this action.',
            Response::HTTP_NOT_FOUND => 'The requested resource was not found.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'The requested method is not supported for this resource.',
            Response::HTTP_CONFLICT => 'The request conflicts with the current resource state.',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'The request data is invalid.',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too many requests. Please try again later.',
            default => $status >= 500
                ? 'An unexpected server error occurred.'
                : 'The request could not be completed.',
        };

        $details = $exception instanceof ValidationException
            ? ['fields' => $exception->errors()]
            : new stdClass;

        $headers = $response->headers->all();
        unset($headers['content-length'], $headers['content-type']);

        return self::make($code, $message, $status, $details, $headers);
    }
}
