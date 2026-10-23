<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PortalResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(strlen($request->getContent()) > 65536, 413);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->remove('ETag');
        $response->headers->remove('Last-Modified');

        return $response;
    }
}
