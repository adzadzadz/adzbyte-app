<?php

use App\Http\Responses\PackageCheckoutErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        $exceptions->render(function (Throwable $exception, Request $request) {
            return PackageCheckoutErrors::matches($request)
                ? PackageCheckoutErrors::render($exception) : null;
        });
        // Route-pipeline exceptions are rendered below the access middleware.
        // Suppress their raw messages/arguments, which can contain private data.
        $exceptions->dontReportWhen(fn (Throwable $exception) => PackageCheckoutErrors::matches(request()));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
