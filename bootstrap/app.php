<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureCustomerApiAccess;
use App\Http\Middleware\EnsureIdempotentApiRequest;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts();
        $middleware->append(AddSecurityHeaders::class);
        $middleware->statefulApi();

        $middleware->alias([
            'customer.api' => EnsureCustomerApiAccess::class,
            'idempotent' => EnsureIdempotentApiRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (! $request->is('api/*')) {
                return $response;
            }

            return ApiErrorResponse::fromException($response, $exception);
        });
    })->create();
