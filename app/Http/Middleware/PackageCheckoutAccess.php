<?php

namespace App\Http\Middleware;

use App\Http\Responses\PackageCheckoutErrors;
use Closure;
use Illuminate\Http\Request;
use Throwable;

final class PackageCheckoutAccess
{
    public function handle(Request $request, Closure $next, string $kind = 'integration')
    {
        try {
            abort_unless(config('package_checkout.enabled') === true, 404);
            if ($kind === 'integration') {
                $token = config('package_checkout.api_token');
                abort_unless(is_string($token) && strlen($token) >= 32, 503);
                abort_unless(is_string($request->bearerToken()) && hash_equals($token, $request->bearerToken()), 401);
                // The API is consumed by the Next server. No browser cross-origin mutation.
                abort_if($request->headers->has('Origin'), 403);
            }
            abort_if(strlen($request->getContent()) > ($kind === 'webhook' ? 500000 : 4096), 413);
            $response = $next($request);
        } catch (Throwable $exception) {
            $response = PackageCheckoutErrors::render($exception);
        }

        return $response->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
