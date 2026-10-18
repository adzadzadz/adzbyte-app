<?php

namespace App\Http\Middleware;

use App\Services\Carts\CommerceOrigins;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAppCartOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            abort_unless($request->header('Origin') === CommerceOrigins::app(), 403);
        }

        return $next($request);
    }
}
