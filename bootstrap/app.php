<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureCustomerApiAccess;
use App\Http\Middleware\EnsureIdempotentApiRequest;
use App\Http\Middleware\EnsureStorefrontToken;
use App\Http\Middleware\RejectDeletedIdentity;
use App\Http\Middleware\RejectDeletedWebIdentity;
use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\PackageCheckoutErrors;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
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
        $middleware->web(append: RejectDeletedWebIdentity::class);
        $middleware->api(append: RejectDeletedIdentity::class);
        $middleware->appendToPriorityList(after: AuthenticatesRequests::class, append: RejectDeletedIdentity::class);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: RejectDeletedWebIdentity::class);
        $middleware->redirectGuestsTo(fn () => route('filament.account.auth.login'));

        $middleware->alias([
            'customer.api' => EnsureCustomerApiAccess::class,
            'idempotent' => EnsureIdempotentApiRequest::class,
            'storefront.token' => EnsureStorefrontToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if ($request->routeIs('account-activation.*')) {
                return response()->view('auth.activation-unavailable', status: 403)->header('Cache-Control', 'no-store, private');
            }

            return null;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            return PackageCheckoutErrors::matches($request) ? PackageCheckoutErrors::render($exception) : null;
        });
        $exceptions->dontReportWhen(fn (Throwable $exception) => PackageCheckoutErrors::matches(request()));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (! $request->is('api/*') || PackageCheckoutErrors::matches($request)) {
                return $response;
            }

            return ApiErrorResponse::fromException($response, $exception);
        });
    })->create();
