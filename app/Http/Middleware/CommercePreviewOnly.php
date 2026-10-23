<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CommercePreviewOnly
{
    public static function enabled(): bool
    {
        return app()->environment('production') && config('commerce.preview_in_production', true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::enabled() || in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }
        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiErrorResponse::make('under_development', 'Ecommerce is under development. Checkout is not available yet.', 503, headers: ['Cache-Control' => 'no-store, private']);
        }

        return response()->view('commerce.preview', status: 503);
    }
}
