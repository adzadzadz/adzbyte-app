<?php

namespace App\Http\Responses;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class PackageCheckoutErrors
{
    public static function matches(Request $request): bool
    {
        return $request->is('api/v1/package-offers/*', 'api/v1/package-checkouts', 'api/v1/package-checkouts/*', 'api/v1/webhooks/package-qrph');
    }

    public static function render(Throwable $exception)
    {
        $status = match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 503,
        };
        $body = ['message' => match ($status) {
            401 => 'Authentication required.', 403 => 'Access denied.', 404 => 'Not found.',
            409 => 'Checkout operation or offer conflict.', 413 => 'Request too large.',
            422 => 'Invalid checkout details or event.', 429 => 'Too many requests.',
            default => 'Checkout temporarily unavailable.',
        }];
        if ($exception instanceof ValidationException) {
            $body['errors'] = $exception->errors();
        }
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        return response()->json($body, $status, $headers)
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
